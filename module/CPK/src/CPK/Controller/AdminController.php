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
     * Path to institutions configurations
     *
     * @var array
     */
    protected $driversPath;

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
     * Initialize configurations
     *
     * @return void
     */
    protected function initConfigs()
    {
        $this->configLocator = $this->serviceLocator->get('VuFind\Config');
        
        $multibackend = $this->configLocator->get('MultiBackend')->toArray();
        
        $this->driversPath = empty($multibackend['General']['drivers_path']) ? '.' : $multibackend['General']['drivers_path'];
        
        $this->ncipTemplate = $this->configLocator->get('xcncip2_template')->toArray();
        $this->alephTemplate = $this->configLocator->get('aleph_template')->toArray();
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
        
        $configs = [];
        
        // Fetch all configs
        foreach ($this->institutionsBeingAdminAt as $adminSource) {
            
            // Exclude portal configs as they doesn't exist
            if (strtolower($adminSource) !== self::PORTAL_ADMIN_SOURCE) {
                
                list ($configs[$adminSource]['active'], $configs[$adminSource]['requested']) = $this->getInstitutionConfig($adminSource);
            }
        }
        
        return $this->createViewModel([
            'user' => $user,
            'ncipTemplate' => $this->ncipTemplate,
            'alephTemplate' => $this->alephTemplate,
            'configs' => $configs
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
        foreach ($this->institutionsBeingAdminAt as $adminSource) {
            if (strtolower($adminSource) === self::PORTAL_ADMIN_SOURCE)
                return true;
        }
        
        return false;
    }

    /**
     * Get logged in user
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
            
            // TODO send an email about cancelling desired change
        }
    }

    protected function deleteRequestConfig($config)
    {
        $source = $config['source'];
        
        if (empty($source))
            return false;
        
        unset($config['source']);
        
        if (! in_array($source, $this->institutionsBeingAdminAt)) {
            throw new \Exception('You don\'t have permissions to change config of ' . $source . '!');
        }
        
        $basePath = $_SERVER['VUFIND_LOCAL_DIR'] . '/config/vufind/';
        
        $path = $basePath . $this->driversPath . '/requests/';
        
        $filename = $path . $source . '.ini';
        
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
            
            // TODO send an email about new change desired
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
        
        $basePath = $_SERVER['VUFIND_LOCAL_DIR'] . '/config/vufind/';
        
        $path = $basePath . $this->driversPath . '/requests/';
        
        $filename = $path . $source . '.ini';
        
        $dirStatus = is_dir($path) || mkdir($path, 0777, true);
        
        if (! $dirStatus) {
            throw new \Exception("Cannot create '$path' directory. Please fix the permissions by running: 'sudo mkdir $path && sudo chown -R www-data $path'");
        }
        
        $config = $this->cleanData($config);
        $config = new Config($config, false);
        
        try {
            (new IniWriter())->toFile($filename, $config);
        } catch (\Exception $e) {
            throw new \Exception("Cannot write to file '$filename'. Please fix the permissions by running: 'sudo chown -R www-data $path'");
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
     * Parses config from the POST.
     *
     * Note that it cuts out the configuration which is not included within the template
     *
     * @param array $config            
     */
    protected function parseConfigSections($config)
    {
        $newCfg = [];
        
        foreach ($config as $key => $value) {
            
            list ($section, $key) = explode('/', $key);
            
            $newCfg[$section][$key] = $value;
        }
        
        $isAleph = isset($newCfg['Catalog']['restdlf']);
        
        $template = $isAleph ? $this->alephTemplate : $this->ncipTemplate;
        
        // Prepare template for effective iteration
        unset($template['Definitions']);
        
        $parsedCfg = [];
        
        foreach ($template as $section => $keys) {
            foreach ($keys as $key => $value) {
                
                // Set new configuration or default if not provided
                $parsedCfg[$section][$key] = isset($newCfg[$section][$key]) ? $newCfg[$section][$key] : $value;
            }
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
    protected function getInstitutionConfig($source)
    {
        return [
            $this->configLocator->get($this->driversPath . '/' . $source)->toArray(),
            $this->configLocator->get($this->driversPath . '/requests/' . $source)->toArray()
        ];
    }
}
