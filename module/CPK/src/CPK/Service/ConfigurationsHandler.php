<?php

/**
 * Service dedicated to handle institutions configuration change requests
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Service
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Service;

use CPK\Controller\AdminController;
use VuFind\Mailer\Mailer;
use \Zend\Mail\Address;

/**
 * An handler for change institution configurations by portal admin.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 *
 */
class ConfigurationsHandler
{

    /**
     * Controller which spawned this instance.
     *
     * @var AdminController
     */
    protected $ctrl;

    /**
     * Service locator
     *
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Db Table for institution configs
     *
     * @var \CPK\Db\Table\InstConfigs
     */
    protected $instConfigsTable;

    /**
     * Config Locator
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLocator;

    /**
     * Object holding the configuration of email to use when a configuration change is desired by some institution admin
     *
     * @var array
     */
    protected $approvalConfig;

    /**
     * Mailer to notify about changes made by institutions admins
     *
     * @var Mailer
     */
    protected $mailer;

    /**
     * Array of source driver type definitions
     *
     * @var array
     */
    protected $sourceTypes;

    /**
     * Array of multibackend configurations.
     *
     * @var array
     */
    private $multiBackend = [];

    /**
     * Template configuration for each drivers.
     *
     * @var
     */
    private $driversTemplate;

    /**
     * Default driver template. Sets if source have driver whit do not have template
     *
     * @var string
     */
    private $defaultDriverTemplate = 'xcncip2_template';

    /**
     * Object containing NCIP types such as Verbis, Clavius etc.
     *
     * @var
     */
    protected $ncipTypes;

    /**
     * C'tor
     *
     * @param AdminController|\VuFind\Controller\AbstractBase $controller
     */
    public function __construct(AdminController $controller)
    {
        $this->ctrl = $controller;

        $this->serviceLocator = $this->ctrl->getServiceLocator();

        $this->instConfigsTable = $this->serviceLocator->get('VuFind\DbTablePluginManager')->get('inst_configs');
        $this->configLocator = $this->serviceLocator->get('VuFind\Config');

        $this->initConfigs();
    }

    /**
     * Initialize configurations
     * @return void
     * @throws \Exception
     */
    protected function initConfigs()
    {
        $this->multiBackend = $this->configLocator->get('MultiBackend')->toArray();
        $this->ncipTypes = $this->multiBackend['NCIPTypes'];
        $this->multiBackend['Drivers'] = $this->excludeNotNeededSources();

        $this->initDriverTemplatesConfigs();

        // setup email
        $this->approvalConfig = $this->configLocator->get('config')['Approval']->toArray();

        if (! isset($this->approvalConfig['emailEnabled']))
            $this->approvalConfig['emailEnabled'] = false;

        if ($this->approvalConfig['emailEnabled'] && (empty($this->approvalConfig['emailFrom']) || empty($this->approvalConfig['emailTo']))) {
            throw new \Exception('Invalid Approval configuration!');
        }

        $this->mailer = $this->serviceLocator->get('VuFind\Mailer');

        $this->sourceTypes = [];

        if ($this->ctrl->getAccessManager()->isPortalAdmin()) {
            foreach ($this->multiBackend['Drivers'] as $key => $driver) {
                $this->sourceTypes[$key]['template'] = $this->driversTemplate[$driver];
            }
        }
    }

    /**
     * Exclude sources which do not need configuration in admin panel.
     *
     * @return array
     */
    public function excludeNotNeededSources() {
        return array_diff_assoc($this->multiBackend['Drivers'], $this->multiBackend['SourcesNotNeedConfiguration']);
    }

    /**
     * Init all templates for each drivers
     */
    public function initDriverTemplatesConfigs() {
        $existDrivers = array_unique(array_values($this->multiBackend['Drivers']));

        foreach ($existDrivers as $driver) {
            if (!$this->multiBackend['DriversTemplate'][$driver]) {
                $this->driversTemplate[$driver] = $this->defaultDriverTemplate;
                continue;
            }
            $this->driversTemplate[$driver] = $this->driversTemplate[$driver] = $this->configLocator
                ->get($this->multiBackend['DriversTemplate'][$driver])
                ->toArray();
        }
    }

    /**
     * Returns collection of existing NCIP types
     *
     * @return mixed
     */
    public function getNCIPTypes()
    {
        return $this->ncipTypes;
    }

    /**
     * Handles POST request from a home action
     *
     * It basically processess any config change desired
     *
     * @internal param array $post
     */
    public function handlePostRequestFromHome()
    {
        // Do we have some POST?
        if (! empty($post = $this->ctrl->params()->fromPost())) {

            // Is there a query for a config modification?
            if (isset($post['requestChange'])) {

                unset($post['requestChange']);

                $this->processChangeRequest($post);
            }
        }
    }

    /**
     * Returns all configs associated with current admin
     *
     * @return array
     */
    public function getAdminConfigs()
    {
        foreach ($this->sourceTypes as $source => $driverName) {
            $this->sourceTypes[$source]['data'] = $this->getInstitutionConfig($source);
        }

        return $this->sourceTypes;
    }

    /**
     * Process a configuration change request
     *
     * @param array $post
     */
    protected function processChangeRequest($post)
    {
        if (! $this->changedSomethingComapredToActive($post)) {
            $requestUnchanged = $this->translate('config_change_denied_unchanged');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        } elseif ($this->changedHiddenConfiguration($post)) {
            $requestUnchanged = $this->translate('config_change_denied_unauthorized');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        }

        $success = $this->createNewConfig($post);

        if ($success) {

            $requestCreated = $this->translate('config_created');
            $this->flashMessenger()->addSuccessMessage($requestCreated);

            $this->sendNewRequestMail($post['source']);
        }
    }

    /**
     * Returns true if provided configuration differs from the activeOne
     *
     * @param array $config
     *
     * @return boolean
     */
    protected function changedSomethingComapredToActive($config)
    {
        $source = $config['source'];
        $template = $this->sourceTypes[$source]['template'];

        $defs = $template['Definitions'];

        $hidden = $defs['hidden'];

        $currentActive = $this->instConfigsTable->getConfig($source);

        if (! $currentActive && ! empty($config['Catalog'])) {

            // There is no active config yet, so if not empty, it is changed indeed

            return true;
        }

        // Has the request changed something?
        foreach ($currentActive as $section => $keys) {

            if (array_search($section, $hidden) !== false)
                continue;

            foreach ($keys as $key => $value) {
                if (array_search($section . ':' . $key, $hidden) !== false)
                    continue;

                if ($defs[$section][$key] === 'checkbox') {
                    $config[$section][$key] = isset($config[$section][$key]) ? '1' : '0';
                }

                $newValue = $config[$section][$key];
                unset($config[$section][$key]);
                if ($value != trim($newValue)) {
                    return true;
                }
            }

            // Clear empty values
            foreach ($config[$section] as $key => $value) {
                if ($value === '')
                    unset($config[$section][$key]);
            }

            if (isset($config[$section]) && empty($config[$section]))
                unset($config[$section]);
            else
                return true; // Something new added
        }

        return false;
    }

    /**
     * Returns true if provided configuration has hidden parameters present within it.
     *
     * It should prevent curious institution administrators from changing values they're not supposed to change.
     *
     * @param array $config
     *
     * @return boolean
     */
    protected function changedHiddenConfiguration($config)
    {
        $template = $this->sourceTypes[$config['source']]['template'];

        unset($config['source']);

        $defs = $template['Definitions'];

        $hidden = $defs['hidden'];

        // Has the request changed something?
        foreach ($config as $section => $keys) {
            foreach ($keys as $key => $value) {
                if (array_search($section . ':' . $key, $hidden) !== false)
                    return true;
            }
        }

        return false;
    }

    /**
     * Saves new configuration
     *
     * @param array $config
     * @return bool|int
     * @throws \Exception
     */
    protected function createNewConfig($config)
    {
        $source = $config['source'];

        if (empty($source))
            return false;

        if (! $this->ctrl->getAccessManager()->isPortalAdmin()) {
            throw new \Exception('You don\'t have permissions to change config of ' . $source . '!');
        }

        $config = $this->parseConfigSections($config, $source);

        if (! isset($config['Availability']['source']) || empty($config['Availability']['source'])) {
            $config['Availability']['source'] = $source;
        }

        if (! isset($config['IdResolver']['prefix']) || empty($config['IdResolver']['prefix'])) {
            $config['IdResolver']['prefix'] = $source;
        }

        return $this->instConfigsTable->setNewConfig($source, $config);
    }

    /**
     * Clean data
     * Cleanup: Remove double quotes
     *
     * @param array $data
     *            Data
     *
     * @return array
     */
    protected function cleanData(array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanData($value);
            } else {
                $data[$key] = str_replace('"', '', $value);
            }
        }
        return $data;
    }

    /**
     * Unsets all the keys within a config that matches the template's Definition's hidden array
     *
     * @param array $config
     *
     * @param $source
     * @return array $filteredConfig
     */
    protected function filterHiddenParameters($config, $source)
    {
        $template = $this->sourceTypes[$source]['template'];

        $hidden = $template['Definitions']['hidden'];

        if (empty($hidden))
            return $config;

        $filteredConfig = [];

        foreach ($config as $section => $keys) {

            // There may be hidden whole sections
            if (array_search($section, $hidden) !== false)
                continue;

            foreach ($keys as $key => $value) {

                // Is hidden current key?
                if (array_search($section . ':' . $key, $hidden) === false) {
                    $filteredConfig[$section][$key] = $value;
                }
            }
        }

        return $filteredConfig;
    }

    /**
     * Parses config from the POST.
     *
     * Note that it cuts out the configuration which is not included within the template.
     *
     * @param array $config
     * @param string $source
     * @return array
     */
    protected function parseConfigSections($config, $source)
    {
        $template = $this->sourceTypes[$source]['template'];

        $defs = $template['Definitions'];

        // Prepare template for effective iteration
        unset($template['Definitions']);

        $parsedCfg = [];

        // Rename 'relative_path_template' to 'relative_path'
        if (isset($template['Parent_Config']['relative_path_template'])) {

            $template['Parent_Config']['relative_path'] = $template['Parent_Config']['relative_path_template'];

            unset($template['Parent_Config']['relative_path_template']);
        }

        foreach ($template as $section => $keys) {
            foreach ($keys as $key => $value) {

                $definitionExists = isset($defs[$section][$key]);

                if ($definitionExists && $defs[$section][$key] === 'checkbox') {
                    $parsedCfg[$section][$key] = isset($config[$section][$key]) ? '1' : '0';

                } elseif ($definitionExists && $defs[$section][$key] === 'number') {

                    // Set new configuration or default if not provided
                    $parsedCfg[$section][$key] = !empty($config[$section][$key]) ? $config[$section][$key] : $value;

                } else {

                    // Set new configuration or default if not provided
                    $parsedCfg[$section][$key] = isset($config[$section][$key]) ? $config[$section][$key] : $value;
                }
            }
        }

        // Add prefix for IdResolver
        $parsedCfg['IdResolver']['prefix'] = $source;

        // Auth username cannot be empty, so value 'false' is used when empty.
        if (isset($parsedCfg['Catalog']['username']) && empty($parsedCfg['Catalog']['username'])) {
            $parsedCfg['Catalog']['username'] = 'false';
        }

        return $parsedCfg;
    }

    /**
     * Returns an associative array of institution configuration.
     *
     * If was configuration not found, then is returned empty array.
     *
     * @param string $source
     *
     * @param bool $filterHidden
     * @return array
     */
    protected function getInstitutionConfig($source, $filterHidden = true)
    {
        $activeCfg = $this->instConfigsTable->getConfig($source);

        if ($activeCfg === false) {
            $activeCfg = [];
        }

        if ($this->sourceTypes[$source] === 'Aleph' && ! isset($activeCfg['Catalog']['dlfport'])) {
            $activeCfg['Catalog']['dlfport'] = '';
        }

        if ($filterHidden)
            return $this->filterHiddenParameters($activeCfg, $source);
        else
            return $activeCfg;
    }

    /**
     * Sends an information email about a new configuration request
     *
     * @param string $source
     * @return bool
     */
    protected function sendNewRequestMail($source)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Změna konfigurace u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" změnil konfigurace.';

            $this->sendMailToPortalAdmin($subject, $message);
        }

        return false;
    }

    /**
     * Sends an email as defined within a config at section named Config_Change_Mailer
     *
     * @param string $subject
     * @param string $message
     */
    protected function sendMailToPortalAdmin($subject, $message)
    {
        $from = new Address($this->approvalConfig['emailFrom'], $this->approvalConfig['emailFromName']);
        $this->mailer->send($this->approvalConfig['emailTo'], $from, $subject, $message);
    }

    private function translate($msg, $tokens = [], $default = null)
    {
        return $this->ctrl->translate($msg, $tokens, $default);
    }

    private function flashMessenger()
    {
        return $this->ctrl->flashMessenger();
    }
}