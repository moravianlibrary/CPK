<?php

/**
 * Service dedicated to handle institutions translations requests
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
 * @package  Controller
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Service;

use CPK\Controller\AdminController;
use Zend\Config\Writer\Ini as IniWriter;
use Zend\Config\Config;
use VuFind\Mailer\Mailer;

/**
 * An handler for handling requests from institutions admins
 * to change their translations & approval of those translations
 * by portal admin.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 *
 */
class TranslationsHandler
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
     * Object holding the configuration of email to use when a configuration change is desired by some institution admin
     *
     * @var array
     */
    protected $emailConfig;

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
     * C'tor
     *
     * @param \VuFind\Controller\AbstractBase $controller
     */
    public function __construct(AdminController $controller)
    {
        $this->ctrl = $controller;

        $this->serviceLocator = $this->ctrl->getServiceLocator();

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

        // setup email
        $this->emailConfig = $this->configLocator->get('config')['Config_Change_Mailer']->toArray();

        if ($this->emailConfig['enabled'] && (empty($this->emailConfig['from']) || empty($this->emailConfig['to']))) {
            throw new \Exception('Invalid Config_Change_Mailer configuration!');
        }

        $this->mailer = $this->serviceLocator->get('VuFind\Mailer');

        $this->institutionsBeingAdminAt = $this->ctrl->getAccessManager()->getInstitutionsWithAdminRights();
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

            $contactPerson = $this->getInstitutionContactPerson($source);

            // Is there a query for a config modification?
            if (isset($post['approved'])) {

                $result = $this->approveRequest($post);

                if ($result) {

                    $this->sendRequestApprovedMail($source, $post['message'], $contactPerson);

                    $msg = $this->translate('approval_succeeded');
                    $this->flashMessenger()->addSuccessMessage($msg);

                    $this->commitNewTranslations($source);
                } else {

                    $msg = $this->translate('approval_failed');
                    $this->flashMessenger()->addErrorMessage($msg);
                }
            } else
                if (isset($post['denied'])) {

                    $this->deleteRequestConfig([
                        'source' => $source
                    ]);

                    $this->sendRequestDeniedMail($source, $post['message'], $contactPerson);

                    $msg = $this->translate('request_successfully_denied');
                    $this->flashMessenger()->addSuccessMessage($msg);
                }
        }
    }

    /**
     * Process a configuration change request
     *
     * @param array $post
     */
    protected function processChangeRequest($post)
    {
        if (! $this->changedSomething($post)) {
            $requestUnchanged = $this->translate('request_translations_denied_unchanged');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        }

        $success = false; // TODO save request to DB

        if ($success) {

            $requestCreated = $this->translate('request_translations_created');
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
        $success = false; // TODO remove entries from DB

        if ($success) {

            $requestCancelled = $this->translate('request_translations_change_cancelled');
            $this->flashMessenger()->addSuccessMessage($requestCancelled);

            $this->sendRequestCancelledMail($post['source']);
        }
    }

    /**
     * Returns true if provided translations differs from the currently active translations
     *
     * @param array $translations
     *
     * @return boolean
     */
    protected function changedSomething($translations)
    {
        // TODO compare current translations with the one in DB
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
        // TODO Transfer tranlations from DB to real config

        return false;
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
     * Returns email of an contact person within an institution
     *
     * @param string $source
     *
     * @return string
     */
    protected function getInstitutionContactPerson($source)
    {
        $institutionCfgPath = $this->driversPath . '/' . $source;

        $institutionCfg = $this->configLocator->get($institutionCfgPath)->toArray();

        return $institutionCfg['Catalog']['contactPerson'];
    }

    /**
     * Sends an information email about a configuration request change has beed cancelled
     *
     * @param string $source
     */
    protected function sendRequestCancelledMail($source)
    {
        if ($this->emailConfig['enabled']) {

            $subject = 'Zrušení žádosti o změnu překladů u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" zrušil žádost o změnu překladů.';

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
        if ($this->emailConfig['enabled']) {

            $subject = 'Žádost o změnu překladů u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" vytvořil žádost o změnu překladů.';

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
        if ($this->emailConfig['enabled']) {

            $subject = 'Schválení žádosti o změnu překladů u instituce ' . $source;

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě jsme Vám schválili Vaši žádost o změnu překladů v instituci ' . $source . '\r\n\r\n' . $message;

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
        if ($this->emailConfig['enabled']) {

            $subject = 'Žádost o změnu překladů u instituce ' . $source . ' byla zamítnuta';

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě Vám byla Vaše žádost o změnu překladů v instituci ' . $source . ' zamítnuta.\r\n\r\n' . $message;

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
        $from = new \Zend\Mail\Address($this->emailConfig['from'], $this->emailConfig['fromName']);

        return $this->mailer->send($this->emailConfig['to'], $from, $subject, $message);
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
        $from = new \Zend\Mail\Address($this->emailConfig['from'], $this->emailConfig['fromName']);

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