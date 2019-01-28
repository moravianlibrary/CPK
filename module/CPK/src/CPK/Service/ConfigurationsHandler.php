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
use Zend\Config\Writer\Ini as IniWriter;
use Zend\Config\Config;
use VuFind\Mailer\Mailer;

/**
 * An handler for handling requests from institutions admins
 * to change their configurations & approval of those configurations
 * by portal admin.
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
     * Relative path to institutions configurations
     *
     * @var array
     */
    protected $driversPath;

    /**
     * Absolute path to institutions configurations
     *
     * @var array
     */
    protected $driversAbsolutePath;

    /**
     * Object containing NCIP driver config template
     *
     * @var array
     */
    protected $ncipTemplate;

    /**
     * Object containing Aleph driver config template
     *
     * @var array
     */
    protected $alephTemplate;

    /**
     * Object containing NCIP types such as Verbis, Clavius etc.
     *
     * @var
     */
    protected $ncipTypes;

    /**
     * Object holding the configuration of email to use
     * when a configuration change is desired by some institution admin
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
     * Array of institution sources where is current user an admin
     *
     * @var array
     */
    protected $institutionsBeingAdminAt;

    /**
     * Array of source driver type definitions
     *
     * @var string[]
     */
    protected $sourceTypes;

    /**
     * C'tor
     *
     * @param \VuFind\Controller\AbstractBase $controller
     */
    public function __construct(AdminController $controller)
    {
        $this->ctrl = $controller;

        $this->serviceLocator = $this->ctrl->getServiceLocator();

        $this->instConfigsTable = $this->serviceLocator->get('VuFind\DbTablePluginManager')->get('inst_configs');

        $this->initConfigs();
    }

    /**
     * Initialize configurations
     *
     * @return void
     */
    protected function initConfigs()
    {
        $this->configLocator = $this->serviceLocator->get('VuFind\Config');

        $multibackend = $this->configLocator->get('MultiBackend')->toArray();

        // get the drivers path
        $this->driversPath = empty($multibackend['General']['drivers_path']) ? '.' : $multibackend['General']['drivers_path'];

        // we need it to be an absolute path ..
        $this->driversAbsolutePath = $_SERVER['VUFIND_LOCAL_DIR'] . '/config/vufind/' . $this->driversPath . '/';

        // get the templates
        $this->ncipTemplate = $this->configLocator->get('xcncip2_template')->toArray();
        $this->alephTemplate = $this->configLocator->get('aleph_template')->toArray();

        // setup email
        $this->approvalConfig = $this->configLocator->get('config')['Approval']->toArray();

        if (! isset($this->approvalConfig['emailEnabled']))
            $this->approvalConfig['emailEnabled'] = false;

        if ($this->approvalConfig['emailEnabled'] && (empty($this->approvalConfig['emailFrom']) || empty($this->approvalConfig['emailTo']))) {
            throw new \Exception('Invalid Approval configuration!');
        }

        $this->mailer = $this->serviceLocator->get('VuFind\Mailer');

        $this->institutionsBeingAdminAt = $this->ctrl->getAccessManager()->getInstitutionsWithAdminRights();

        $this->sourceTypes = $this->configLocator->get('MultiBackend')->toArray()['Drivers'];
        $this->ncipTypes = $this->configLocator->get('MultiBackend')->toArray()['NCIPTypes'];

        if ($this->ctrl->getAccessManager()->isPortalAdmin()) {
            foreach ($this->sourceTypes as $key => $value) {
                if ($key == "Dummy") continue;
                $this->institutionsBeingAdminAt[] = $key;
            }
            $this->institutionsBeingAdminAt = array_unique($this->institutionsBeingAdminAt);
        }
    }

    /**
     * Handles POST request from a home action
     *
     * It basically processess any config change desired
     *
     * @param array $post
     */
    public function handlePostRequestFromHome()
    {
        // Do we have some POST?
        if (! empty($post = $this->ctrl->params()->fromPost())) {

            // Is there a query for a config modification?
            if (isset($post['requestChange'])) {

                unset($post['requestChange']);

                $this->processChangeRequest($post);
            } else
                if (isset($post['requestChangeCancel'])) {
                    // Or there is query for cancelling a config modification?

                    unset($post['requestChangeCancel']);

                    $this->processCancelChangeRequest($post);
                }
        }
    }

    /**
     * Handles POST request from an approval action
     */
    public function handlePostRequestFromApproval()
    {
        // Do we have some POST?
        if (! empty($post = $this->ctrl->params()->fromPost())) {

            if (! isset($post['source']))
                return;

            $source = $post['source'];

            $contactPerson = $post['Catalog']['contactPerson'];

            // Is there a query for a config modification?
            if (isset($post['approved'])) {

                unset($post['approved']);

                $result = $this->approveRequest($post);

                if ($result) {

                    $this->sendRequestApprovedMail($source, $post['message'], $contactPerson);

                    $msg = $this->translate('approval_succeeded');
                    $this->flashMessenger()->addSuccessMessage($msg);

                    $this->commitNewConfig($source);
                } else {

                    $msg = $this->translate('approval_failed');
                    $this->flashMessenger()->addErrorMessage($msg);
                }
            } else
                if (isset($post['denied'])) {

                    $this->deleteRequestConfig($source);

                    $this->sendRequestDeniedMail($source, $post['message'], $contactPerson);

                    $msg = $this->translate('request_successfully_denied');
                    $this->flashMessenger()->addSuccessMessage($msg);
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
        $configs = [];

        // Fetch all configs
        foreach ($this->institutionsBeingAdminAt as $adminSource) {

            $configs[$adminSource] = $this->getInstitutionConfig($adminSource);
        }

        return $configs;
    }

    /**
     * Returns an NCIP template configuration file
     *
     * @return array
     */
    public function getNcipTemplate()
    {
        return $this->ncipTemplate;
    }

    /**
     * Returns an Aleph template configuration file
     *
     * @return array
     */
    public function getAlephTemplate()
    {
        return $this->alephTemplate;
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
     * Returns all configs being requested
     *
     * @return array
     */
    public function getAllRequestConfigsWithActive()
    {
        return $this->instConfigsTable->getAllRequestConfigsWithActive();
    }

    /**
     * Process a configuration change request
     *
     * @param array $post
     */
    protected function processChangeRequest($post)
    {
        if (! $this->changedSomethingComapredToActive($post)) {
            $requestUnchanged = $this->translate('request_config_denied_unchanged');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        } elseif ($this->changedHiddenConfiguration($post)) {
            $requestUnchanged = $this->translate('request_config_denied_unauthorized');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        }

        $success = $this->createNewRequestConfig($post);

        if ($success) {

            $requestCreated = $this->translate('request_config_created');
            $this->flashMessenger()->addSuccessMessage($requestCreated);

            $this->sendNewRequestMail($post['source']);
        }
    }

    /**
     * Process a cancel for a configuration change
     *
     * @param array $post
     */
    protected function processCancelChangeRequest($post)
    {
        $success = $this->deleteRequestConfig($post['source']);

        if ($success) {

            $requestCancelled = $this->translate('request_config_change_cancelled');
            $this->flashMessenger()->addSuccessMessage($requestCancelled);

            $this->sendRequestCancelledMail($post['source']);
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
        $isAleph = isset($config['Catalog']['dlfport']);

        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;

        $defs = $template['Definitions'];

        $hidden = $defs['hidden'];

        $currentActive = $this->instConfigsTable->getApprovedConfig($config['source']);

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
        unset($config['source']);

        $isAleph = isset($config['Catalog']['dlfport']);

        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;

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
     * Approves an configuration request made by institution admin
     *
     * @param string $source
     *
     * @return boolean $result
     */
    protected function approveRequest($post)
    {
        $source = $post['source'];

        unset($post['source']);
        unset($post['message']);

        $succeeded = $this->instConfigsTable->approveConfig($post, $source);

        return $succeeded;
    }

    /**
     * Deletes request configuration from the requests dir
     *
     * @param string $source
     * @throws \Exception
     *
     * @return boolean
     */
    protected function deleteRequestConfig($source)
    {
        if (empty($source))
            return false;

        if (! in_array($source, $this->institutionsBeingAdminAt) && ! $this->ctrl->getAccessManager()->isPortalAdmin()) {
            throw new \Exception('You don\'t have permissions to change config of ' . $source . '!');
        }

        return $this->instConfigsTable->deleteLastRequestConfig($source);
    }

    /**
     * Saves new configuration
     *
     * @param array $config
     */
    protected function createNewRequestConfig($config)
    {
        $source = $config['source'];

        if (empty($source))
            return false;

        unset($config['source']);

        if (! in_array($source, $this->institutionsBeingAdminAt)) {
            throw new \Exception('You don\'t have permissions to change config of ' . $source . '!');
        }

        $config = $this->parseConfigSections($config, $source);

        if (! isset($config['Availability']['source']) || empty($config['Availability']['source'])) {
            $config['Availability']['source'] = $source;
        }

        if (! isset($config['IdResolver']['prefix']) || empty($config['IdResolver']['prefix'])) {
            $config['IdResolver']['prefix'] = $source;
        }

        $newConfRequest = $this->instConfigsTable->createNewConfig($source, $config);

        return $newConfRequest;
    }

    /**
     * Clean data
     * Cleanup: Remove double quotes
     *
     * @param Array $data
     *            Data
     *
     * @return Array
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
     * @return array $filteredConfig
     */
    protected function filterHiddenParameters($config)
    {
        $isAleph = isset($config['Catalog']['dlfport']);

        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;

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
     * Uses db to version config changes
     *
     * @param string $source
     */
    protected function commitNewConfig($source)
    {
        $config = $this->instConfigsTable->getRequestedConfig($source);

        if ($config === false)
            $config = [];

        return $this->instConfigsTable->createNewConfig($source, $config);
    }

    /**
     * Parses config from the POST.
     *
     * Note that it cuts out the configuration which is not included within the template.
     *
     * FIXME: Setup the default value from template
     *
     * @param array $config
     * @param string $source
     */
    protected function parseConfigSections($config, $source)
    {
        $isAleph = isset($config['Catalog']['dlfport']);

        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;

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
     * @return array
     */
    protected function getInstitutionConfig($source, $filterHidden = true)
    {
        $activeCfg = $this->instConfigsTable->getApprovedConfig($source);

        if ($activeCfg === false)
            $activeCfg = [];

        if ($this->sourceTypes[$source] === 'Aleph' && ! isset($activeCfg['Catalog']['dlfport'])) {
            $activeCfg['Catalog']['dlfport'] = '';
        }

        $requestCfg = $this->instConfigsTable->getRequestedConfig($source);

        if ($requestCfg === false)
            $requestCfg = [];

        if ($filterHidden)
            return [
                'active' => $this->filterHiddenParameters($activeCfg),
                'requested' => $this->filterHiddenParameters($requestCfg)
            ];
        else
            return [
                'active' => $activeCfg,
                'requested' => $requestCfg
            ];
    }

    /**
     * Sends an information email about a configuration request change has beed cancelled
     *
     * @param string $source
     */
    protected function sendRequestCancelledMail($source)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Zrušení žádosti o změnu konfigurace u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" zrušil žádost o změnu konfigurace.';

            return $this->sendMailToPortalAdmin($subject, $message);
        }

        return false;
    }

    /**
     * Sends an information email about a new configuration request
     *
     * @param string $source
     */
    protected function sendNewRequestMail($source)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Žádost o změnu konfigurace u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" vytvořil žádost o změnu konfigurace.';

            return $this->sendMailToPortalAdmin($subject, $message);
        }

        return false;
    }

    /**
     * Sends an information email about a configuration request has been approved
     *
     * @param string $source
     * @param string $message
     * @param string $to
     */
    protected function sendRequestApprovedMail($source, $message, $to)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Schválení žádosti o změnu konfigurace u instituce ' . $source;

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě jsme Vám schválili Vaši žádost o změnu konfigurace v instituci ' . $source . '\r\n\r\n' . $message;

            return $this->sendMailToContactPerson($subject, $message, $to);
        }

        return false;
    }

    /**
     * Sends an information email about a configuration request has been denied
     *
     * @param string $source
     * @param string $message
     * @param string $to
     */
    protected function sendRequestDeniedMail($source, $message, $to)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Žádost o změnu konfigurace u instituce ' . $source . ' byla zamítnuta';

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě Vám byla Vaše žádost o změnu konfigurace v instituci ' . $source . ' zamítnuta.\r\n\r\n' . $message;

            return $this->sendMailToContactPerson($subject, $message, $to);
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
        $from = new \Zend\Mail\Address($this->approvalConfig['emailFrom'], $this->approvalConfig['emailFromName']);

        return $this->mailer->send($this->approvalConfig['emailTo'], $from, $subject, $message);
    }

    /**
     * Sends an email to a contact person
     *
     * @param string $subject
     * @param string $message
     * @param string $to
     */
    protected function sendMailToContactPerson($subject, $message, $to)
    {
        $from = new \Zend\Mail\Address($this->approvalConfig['emailFrom'], $this->approvalConfig['emailFromName']);

        return $this->mailer->send($to, $from, $subject, $message);
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