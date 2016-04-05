<?php
/**
 * Admin Controller
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
namespace CPK\Controller;

use MZKCommon\Controller\ExceptionsTrait, CPK\Db\Row\User;
use Zend\Config\Writer\Ini as IniWriter;
use Zend\Config\Config;
use VuFind\Mailer\Mailer;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class AdminController extends \VuFind\Controller\AbstractBase
{
    use ExceptionsTrait;

    /**
     * Source / identifier of main portal admin
     *
     * @var string
     */
    const PORTAL_ADMIN_SOURCE = 'cpk';

    /**
     * Holds names of institutions user is admin of
     *
     * @var array
     */
    protected $institutionsBeingAdminAt = [];

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
        $this->emailConfig = $this->configLocator->get('config')['Config_Change_Mailer']->toArray();
        
        if ($this->emailConfig['enabled'] && (empty($this->emailConfig['from']) || empty($this->emailConfig['to']))) {
            throw new \Exception('Invalid Config_Change_Mailer configuration!');
        }
        
        $this->mailer = $this->serviceLocator->get('VuFind\Mailer');
    }

    /**
     * Admin home.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        if (! ($user = $this->getLoggedInUser()) instanceof User) {
            return $user; // Not logged in, returns redirection
        }
        
        $this->layout()->searchbox = false;
        
        $this->initConfigs();
        
        $this->handlePostRequestFromHome();
        
        return $this->createViewModel([
            'isPortalAdmin' => $this->isPortalAdmin(),
            'ncipTemplate' => $this->ncipTemplate,
            'alephTemplate' => $this->alephTemplate,
            'configs' => $this->getAdminConfigs(),
            'restrictedSections' => [
                'Parent_Config',
                'Definitions'
            ]
        ]);
    }

    public function approvalAction()
    {
        // Log in first!
        if (! ($user = $this->getLoggedInUser()) instanceof User) {
            return $user; // Not logged in, returns redirection
        }
        
        if (! $this->isPortalAdmin()) {
            return $this->forceLogin('You\'re not a portal admin!');
        }
        
        $this->initConfigs();
        
        $this->handlePostRequestFromApproval();
        
        return $this->createViewModel([
            'isPortalAdmin' => $this->isPortalAdmin(),
            'configs' => $this->getAllRequestConfigs()
        ]);
    }

    public function portalPagesAction()
    {
        // Log in first!
        if (! ($user = $this->getLoggedInUser()) instanceof User) {
            return $user; // Not logged in, returns redirection
        }
        
        if (! $this->isPortalAdmin()) {
            return $this->forceLogin('You\'re not a portal admin!');
        }
        // Logged In successfull
        
        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->isPortalAdmin());
        $viewModel->setVariable('user', $user);
        
        $portalPagesTable = $this->getTable("portalpages");
        
        $positions = [
            'left',
            'middle',
            'right'
        ];
        $placements = [
            'footer',
            'advanced-search'
        ];
        
        $subAction = $this->params()->fromRoute('subaction');
        if ($subAction == 'Edit') { // is edit in route?
            $pageId = (int) $this->params()->fromRoute('param');
            $page = $portalPagesTable->getPageById($pageId);
            $viewModel->setVariable('page', $page);
            
            $viewModel->setVariable('positions', $positions);
            $viewModel->setVariable('placements', $placements);
            
            $viewModel->setTemplate('admin/edit-portal-page');
        } else 
            if ($subAction == 'Save') {
                $post = $this->params()->fromPost();
                $portalPagesTable->save($post);
                return $this->forwardTo('Admin', 'PortalPages');
            } else 
                if ($subAction == 'Insert') {
                    $post = $this->params()->fromPost();
                    $portalPagesTable->insertNewPage($post);
                    return $this->forwardTo('Admin', 'PortalPages');
                } else 
                    if ($subAction == 'Delete') {
                        $pageId = $this->params()->fromRoute('param');
                        if (! empty($pageId)) {
                            $portalPagesTable->delete($pageId);
                        }
                        return $this->forwardTo('Admin', 'PortalPages');
                    } else 
                        if ($subAction == 'Create') {
                            $viewModel->setVariable('positions', $positions);
                            $viewModel->setVariable('placements', $placements);
                            $viewModel->setTemplate('admin/create-portal-page');
                        } else { // normal view
                            $allPages = $portalPagesTable->getAllPages('*', false);
                            $viewModel->setVariable('pages', $allPages);
                        }
        
        $this->layout()->searchbox = false;
        return $viewModel;
    }

    /**
     * Permissions manager
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function permissionsManagerAction()
    {
        // Log in first!
        if (! ($user = $this->getLoggedInUser()) instanceof User) {
            return $user; // Not logged in, returns redirection
        }
        
        if (! $this->isPortalAdmin()) {
            return $this->forceLogin('You\'re not a portal admin!');
        }
        // Logged In successfull
        
        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->isPortalAdmin());
        $viewModel->setVariable('user', $user);
        
        $userTable = $this->getTable('user');
        
        $subAction = $this->params()->fromRoute('subaction');
        if ($subAction == 'Save') {
            $post = $this->params()->fromPost();
            $userTable->saveUserWithPermissions($post['eppn'], $post['major']);
            return $this->forwardTo('Admin', 'PermissionsManager');
        } else 
            if ($subAction == 'RemovePermissions') {
                $eppn = $this->params()->fromRoute('param');
                $major = NULL;
                $userTable->saveUserWithPermissions($eppn, $major);
                return $this->forwardTo('Admin', 'PermissionsManager');
            } else 
                if ($subAction == 'AddUser') {
                    $viewModel->setTemplate('admin/add-user-with-permissions');
                } else 
                    if ($subAction == 'EditUser') {
                        $eppn = $this->params()->fromRoute('param');
                        $major = $this->params()->fromRoute('param2');
                        
                        $viewModel->setVariable('eppn', $eppn);
                        $viewModel->setVariable('major', $major);
                        
                        $viewModel->setTemplate('admin/edit-user-with-permissions');
                    } else { // normal view
                        $usersWithPermissions = $userTable->getUsersWithPermissions();
                        $viewModel->setVariable('usersWithPermissions', $usersWithPermissions);
                        $viewModel->setTemplate('admin/permissions-manager');
                    }
        
        $this->layout()->searchbox = false;
        return $viewModel;
    }

    /**
     * Checks if logged in user is a portal admin
     *
     * @return boolean
     */
    protected function isPortalAdmin()
    {
        if (isset($this->__isPortalAdmin))
            return $this->__isPortalAdmin;
        
        foreach ($this->institutionsBeingAdminAt as $adminSource) {
            if (strtolower($adminSource) === self::PORTAL_ADMIN_SOURCE) {
                
                $this->__isPortalAdmin = true;
                return $this->__isPortalAdmin;
            }
        }
        
        $this->__isPortalAdmin = false;
        return $this->__isPortalAdmin;
    }

    /**
     * Get logged in user & set the $this->institutionsBeingAdminAt array
     *
     * @return \CPK\Db\Row\User|Array
     */
    protected function getLoggedInUser()
    {
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }
        
        if (! empty($user->major)) {
            
            $sources = explode(',', $user->major);
            
            $this->institutionsBeingAdminAt = $sources;
        }
        
        foreach ($user->getLibraryCards(true) as $libCard) {
            
            if (! empty($libCard->major)) {
                
                $sources = explode(',', $libCard->major);
                
                $this->institutionsBeingAdminAt = array_merge($this->institutionsBeingAdminAt, $sources);
            }
        }
        
        if (empty($this->institutionsBeingAdminAt)) {
            $this->flashMessenger()->addErrorMessage('You\'re not an admin!');
            $this->flashExceptions($this->flashMessenger());
            return $this->forwardTo('MyResearch', 'Home');
        }
        
        // Trim all elements
        $this->institutionsBeingAdminAt = array_map('trim', $this->institutionsBeingAdminAt);
        
        // Remove possible duplicates
        $this->institutionsBeingAdminAt = array_unique($this->institutionsBeingAdminAt);
        
        return $user;
    }

    /**
     * Handles POST request from a home action
     *
     * It basically processess any config change desired
     */
    protected function handlePostRequestFromHome()
    {
        // Do we have some POST?
        if (! empty($post = $this->params()->fromPost())) {
            
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
    protected function handlePostRequestFromApproval()
    {
        // Do we have some POST?
        if (! empty($post = $this->params()->fromPost())) {
            
            // Is there a query for a config modification?
            if (isset($post['source'])) {
                
                $source = $post['source'];
                
                $result = $this->approveRequest($source);
                
                if ($result) {
                    
                    $this->sendRequestApprovedMail($source);
                    
                    $msg = $this->translate('approval_succeeded');
                    $this->flashMessenger()->addSuccessMessage($msg);
                    
                    $this->commitNewConfig($source);
                } else {
                    
                    $msg = $this->translate('approval_failed');
                    $this->flashMessenger()->addErrorMessage($msg);
                    
                    $suggestion = "Try to execute 'sudo chown -R www-data \"$this->driversAbsolutePath\"'";
                    
                    $this->flashMessenger()->addErrorMessage($suggestion);
                }
            }
        }
    }

    /**
     * Approves an configuration request made by institution admin
     *
     * @param string $source            
     *
     * @return boolean $result
     */
    protected function approveRequest($source)
    {
        $filename = $this->driversAbsolutePath . 'requests/' . $source . '.ini';
        
        $isCopied = copy($filename, $this->driversAbsolutePath . $source . '.ini');
        
        if (! $isCopied)
            return $isCopied;
        
        $isDeleted = $this->deleteRequestConfig([
            'source' => $source
        ]);
        
        return $isDeleted;
    }

    /**
     * Process a cancel for a configuration change
     *
     * @param array $post            
     */
    protected function processCancelChangeRequest($post)
    {
        $success = $this->deleteRequestConfig($post);
        
        if ($success) {
            
            $requestCancelled = $this->translate('request_config_change_cancelled');
            $this->flashMessenger()->addSuccessMessage($requestCancelled);
            
            $this->sendRequestCancelledMail($post['source']);
        }
    }

    /**
     * Deletes request configuration from the requests dir
     *
     * @param array $config            
     * @throws \Exception
     *
     * @return boolean
     */
    protected function deleteRequestConfig($config)
    {
        $source = $config['source'];
        
        if (empty($source))
            return false;
        
        unset($config['source']);
        
        if (! in_array($source, $this->institutionsBeingAdminAt) && ! $this->isPortalAdmin()) {
            throw new \Exception('You don\'t have permissions to change config of ' . $source . '!');
        }
        
        $filename = $this->driversAbsolutePath . 'requests/' . $source . '.ini';
        
        return unlink($filename);
    }

    /**
     * Process a configuration change request
     *
     * @param array $post            
     */
    protected function processChangeRequest($post)
    {
        $success = $this->createNewRequestConfig($post);
        
        if ($success) {
            
            $requestCreated = $this->translate('request_config_created');
            $this->flashMessenger()->addSuccessMessage($requestCreated);
            
            $this->sendNewRequestMail($post['source']);
        }
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
        
        $config = $this->parseConfigSections($config);
        
        if (isset($config['Availability']['source'])) {
            $config['Availability']['source'] = $source;
        }
        
        $requestsPath = $this->driversAbsolutePath . 'requests/';
        
        $filename = $requestsPath . $source . '.ini';
        
        $dirStatus = is_dir($requestsPath) || mkdir($requestsPath, 0777, true);
        
        if (! $dirStatus) {
            throw new \Exception("Cannot create '$requestsPath' directory. Please fix the permissions by running: 'sudo mkdir $requestsPath && sudo chown -R www-data $requestsPath'");
        }
        
        $config = $this->cleanData($config);
        $config = new Config($config, false);
        
        try {
            (new IniWriter())->toFile($filename, $config);
        } catch (\Exception $e) {
            throw new \Exception("Cannot write to file '$filename'. Please fix the permissions by running: 'sudo chown -R www-data $requestsPath'");
        }
        
        return $config;
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
     * Uses git to version config changes
     *
     * @param string $source            
     */
    protected function commitNewConfig($source)
    {
        exec('dpkg -l | egrep "ii\s+git\s"', $grepInstalled);
        if (empty($grepInstalled))
            throw new \Exception('You have to first install git');
        
        if (strpos($result[0], 'fatal') === 0) {
            throw new \Exception("$this->driversAbsolutePath is not a git repository !");
        }
        
        exec("cd $this->driversAbsolutePath && git status --porcelain", $gitStatus);
        
        $file = $source . '.ini';
        
        // Do we already have this config added to git tracked files?
        foreach ($gitStatus as $fileStatus) {
            
            if (strpos('?? ' .$fileStatus, $file) !== false) {
                
                // Now add the file to the tracked files
                exec("cd $this->driversAbsolutePath && git add $file", $gitStatus);
            }
        }
        
        $commitMessage = "\"Approved config change for $source\"";
        
        exec("cd $this->driversAbsolutePath && git commit \"$file\" -m $commitMessage", $commitResult);
        
        $this->flashMessenger()->addWarningMessage("Added commit $commitMessage, but changes are not pushed yet");
        // git push should be called by cron
        
        return true;
    }

    /**
     * Parses config from the POST.
     *
     * Note that it cuts out the configuration which is not included within the template
     *
     * @param array $config            
     */
    protected function parseConfigSections($config)
    {
        $isAleph = isset($config['Catalog']['dlfport']);
        
        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;
        
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
                
                // Set new configuration or default if not provided
                $parsedCfg[$section][$key] = isset($config[$section][$key]) ? $config[$section][$key] : $value;
            }
        }
        
        return $parsedCfg;
    }

    /**
     * Returns all configs associated with current admin
     *
     * return @array
     */
    protected function getAdminConfigs()
    {
        $configs = [];
        
        // Fetch all configs
        foreach ($this->institutionsBeingAdminAt as $adminSource) {
            
            // Exclude portal configs as they doesn't exist
            if (strtolower($adminSource) !== self::PORTAL_ADMIN_SOURCE) {
                
                $configs[$adminSource] = $this->getInstitutionConfig($adminSource);
            }
        }
        
        return $configs;
    }

    /**
     * Returns all configs being requested
     *
     * return @array
     */
    protected function getAllRequestConfigs()
    {
        $configs = [];
        
        $requestsPath = $this->driversAbsolutePath . 'requests/';
        
        $files = scandir($requestsPath, SCANDIR_SORT_NONE);
        
        foreach ($files as $file) {
            if (substr($file, - 4) === '.ini') {
                
                $source = substr($file, 0, - 4);
                
                $config = $this->getInstitutionConfig($source);
                
                $configs[$source] = $config;
            }
        }
        
        return $configs;
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
    protected function getInstitutionConfig($source)
    {
        $activeCfg = $this->configLocator->get($this->driversPath . '/' . $source)->toArray();
        
        $requestCfgPath = $this->driversPath . '/requests/' . $source;
        
        try {
            $requestCfg = $this->configLocator->get($requestCfgPath)->toArray();
        } catch (\Exception $e) {
            
            // There is probably a parent config definition without the parent config
            $missingParent = $this->getMissingParentConfigName($source);
            
            if (! $missingParent)
                throw $e;
                
                // So create one dummy parent config
            $this->createMissingParent($missingParent);
            
            // Now try it again
            $requestCfg = $this->configLocator->get($requestCfgPath)->toArray();
        }
        
        return [
            'active' => $activeCfg,
            'requested' => $requestCfg
        ];
    }

    /**
     * Returns filename of missing parent config within a requests' config
     *
     * @return false|string
     */
    protected function getMissingParentConfigName($source)
    {
        $filename = $this->driversAbsolutePath . 'requests/' . $source . '.ini';
        
        $missingParent = false;
        
        $fh = fopen($filename, 'r') or die($php_errormsg);
        while (! feof($fh)) {
            $line = fgets($fh, 4096);
            if (preg_match('/relative_path/', $line)) {
                
                $missingParent = explode('"', $line)[1];
                break;
            }
        }
        fclose($fh);
        
        return $path . $missingParent;
    }

    /**
     * Creates empty
     *
     * @param string $filename            
     * @throws \Exception
     */
    protected function createMissingParent($filename)
    {
        try {
            file_put_contents($filename, '');
        } catch (\Exception $e) {
            throw new \Exception("Cannot write to file '$filename'. Please fix the permissions by running: 'sudo chown -R www-data $path'");
        }
    }

    /**
     * Sends an information email about a configuration request change has beed cancelled
     */
    protected function sendRequestCancelledMail($source)
    {
        if ($this->emailConfig['enabled']) {
            
            $subject = 'Zrušení žádosti o změnu konfigurace u instituce ' . $source;
            
            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" zrušil žádost o změnu konfigurace.';
            
            return $this->sendMail($subject, $message);
        }
        
        return false;
    }

    protected function sendNewRequestMail($source)
    {
        if ($this->emailConfig['enabled']) {
            
            $subject = 'Žádost o změnu konfigurace u instituce ' . $source;
            
            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" vytvořil žádost o změnu konfigurace.';
            
            return $this->sendMail($subject, $message);
        }
        
        return false;
    }

    protected function sendRequestApprovedMail($source)
    {
        if ($this->emailConfig['enabled']) {
            
            $subject = 'Schválení žádosti o změnu konfigurace u instituce ' . $source;
            
            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\n\n právě jsme Vám schválili Vaši žádost o změnu konfigurace v instituci ' . $source;
            
            return $this->sendMail($subject, $message);
        }
        
        return false;
    }

    /**
     * Sends an email as defined within a config at section named Config_Change_Mailer
     *
     * @param string $subject            
     * @param string $message            
     */
    protected function sendMail($subject, $message)
    {
        $from = new \Zend\Mail\Address($this->emailConfig['from'], $this->emailConfig['fromName']);
        
        return $this->mailer->send($this->emailConfig['to'], $from, $subject, $message);
    }
}
