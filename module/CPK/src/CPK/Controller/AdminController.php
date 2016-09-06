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
use VuFind\Exception\Auth as AuthException;
use Zend\Mvc\MvcEvent;
use CPK\Service\ConfigurationsHandler;
use CPK\Service\TranslationsHandler;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <martin.kravec@mzk.cz>, Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class AdminController extends \VuFind\Controller\AbstractBase
{
    use ExceptionsTrait, LoginTrait;

    /**
     * Access manager instance
     *
     * @var AccessManager
     */
    protected $accessManager;

    /**
     * Initializes access manager & continues choosing an action as defined by parent
     *
     * {@inheritDoc}
     *
     * @see \Zend\Mvc\Controller\AbstractActionController::onDispatch()
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->accessManager = new AccessManager($this->getAuthManager());

        return parent::onDispatch($e);
    }

    /**
     * Returns current instance of access manager
     *
     * @return \CPK\Controller\AccessManager
     */
    public function getAccessManager()
    {
        return $this->accessManager;
    }

    /**
     * Admin home.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        return $this->redirect()->toRoute('admin-configurations');
    }

    /**
     * Action for requesting tranlations changes
     *
     * @return mixed|\Zend\Http\Response|\Zend\View\Model\ViewModel
     */
    public function configurationsAction()
    {
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

        $configHandler = new ConfigurationsHandler($this);

        $configHandler->handlePostRequestFromHome();

        return $this->createViewModel([
            'isPortalAdmin' => $this->accessManager->isPortalAdmin(),
            'ncipTemplate' => $configHandler->getNcipTemplate(),
            'alephTemplate' => $configHandler->getAlephTemplate(),
            'configs' => $configHandler->getAdminConfigs()
        ], 'admin/configurations/main.phtml');
    }

    /**
     * Action for approval of configuration change requests
     *
     * @return mixed|\Zend\Http\Response|\Zend\View\Model\ViewModel
     */
    public function configurationsApprovalAction()
    {
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

            // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $configHandler = new ConfigurationsHandler($this);

        $configHandler->handlePostRequestFromApproval();

        return $this->createViewModel([
            'isPortalAdmin' => $this->accessManager->isPortalAdmin(),
            'ncipTemplate' => $configHandler->getNcipTemplate(),
            'alephTemplate' => $configHandler->getAlephTemplate(),
            'configs' => $configHandler->getAllRequestConfigsWithActive()
        ], 'admin/configurations/approval.phtml');
    }

    /**
     * Action for requesting tranlations changes
     *
     * @return mixed|\Zend\Http\Response|\Zend\View\Model\ViewModel
     */
    public function translationsAction()
    {
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

        $translationsHandler = new TranslationsHandler($this);

        $translationsHandler->handlePostRequestFromHome();

        return $this->createViewModel([
            'isPortalAdmin' => $this->accessManager->isPortalAdmin(),
            'sourcesBeingAdmin' => $this->accessManager->getInstitutionsWithAdminRights(),
            'translations' => $translationsHandler->getAdminTranslations(),
            'supportedLanguages' => $translationsHandler::SUPPORTED_TRANSLATIONS
        ], 'admin/translations/main.phtml');
    }

    /**
     * Action for approval of translations change requests
     *
     * @return mixed|\Zend\Http\Response|\Zend\View\Model\ViewModel
     */
    public function translationsApprovalAction()
    {
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

            // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $translationsHandler = new TranslationsHandler($this);

        $translationsHandler->handlePostRequestFromApproval();

        return $this->createViewModel([
            'isPortalAdmin' => $this->accessManager->isPortalAdmin(),
            'translations' => $translationsHandler->getAllTranslations(),
            'supportedLanguages' => $translationsHandler::SUPPORTED_TRANSLATIONS
        ],'admin/translations/approval.phtml');
    }

    public function portalPagesAction()
    {
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

            // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $user = $this->accessManager->getUser();

        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
        $viewModel->setVariable('user', $user);

        $portalPagesTable = $this->getTable("portalpages");

        $positions = [
            'left',
            'middle-left',
            'middle-right',
            'right'
        ];
        $placements = [
            'footer',
            'advanced-search',
            'modal',
        ];

        $subAction = $this->params()->fromRoute('subaction');
        if ($subAction == 'Edit') { // is edit in route?
            $pageId = (int) $this->params()->fromRoute('param');
            $page = $portalPagesTable->getPageById($pageId);
            $viewModel->setVariable('page', $page);

            $viewModel->setVariable('positions', $positions);
            $viewModel->setVariable('placements', $placements);

            $viewModel->setTemplate('admin/edit-portal-page');
        } else if ($subAction == 'Save') {
            $post = $this->params()->fromPost();
            $portalPagesTable->save($post);
            return $this->forwardTo('Admin', 'PortalPages');
         } else if ($subAction == 'SaveSpecificContents') {
                $post = $this->params()->fromPost();
                $portalPagesTable->saveSpecifiContents($post);
                return $this->forwardTo('Admin', 'PortalPages');
        } else if ($subAction == 'Insert') {
            $post = $this->params()->fromPost();
            $portalPagesTable->insertNewPage($post);
            return $this->forwardTo('Admin', 'PortalPages');
        } else if ($subAction == 'Delete') {
            $pageId = $this->params()->fromRoute('param');
            if (! empty($pageId)) {
                $portalPagesTable->delete($pageId);
            }
            return $this->forwardTo('Admin', 'PortalPages');
        } else if ($subAction == 'Create') {
            $viewModel->setVariable('positions', $positions);
            $viewModel->setVariable('placements', $placements);
            $viewModel->setTemplate('admin/create-portal-page');
        } else if ($subAction == 'EditSpecificContents') {
            $pageId = (int) $this->params()->fromRoute('param');
            $page = $portalPagesTable->getPageById($pageId);
            $viewModel->setVariable('page', $page);

            $multiBackendConfig = $this->getConfig('MultiBackend');
            $sources = [];
            foreach($multiBackendConfig->Drivers as $source => $value) {
                if ($source !== 'Dummy') {
                    $sources[] = $source;
                }
            }
            $viewModel->setVariable('sources', $sources);
            $specificContentsResults = $portalPagesTable->getSpecificContents($page['language_code'], $page['group']);
            $specificContents = [];
            foreach ($specificContentsResults as $row) {
                $specificContents[$row['source']] = $row['content'];
            }
            $viewModel->setVariable('specificContents', $specificContents);

            $viewModel->setTemplate('admin/edit-specific-contents');
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
        if (! $this->accessManager->isLoggedIn())
            return $this->forceLogin();

            // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $user = $this->accessManager->getUser();

        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
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
     * Widgets manager
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function widgetsAction()
    {
        if (! $this->accessManager->isLoggedIn()) {
            return $this->forceLogin();
        }

        // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $user = $this->accessManager->getUser();

        $this->layout()->searchbox = false;

        /*
         * Handle subactions
         * */
        $subAction = $this->params()->fromRoute('subaction');
        if ($subAction == 'SaveFrontendWidgets') {
            $post = $this->params()->fromPost();

            $data = [];
            $data['first_homepage_widget']  = $post['first-homepage-widget'];
            $data['second_homepage_widget'] = $post['second-homepage-widget'];
            $data['third_homepage_widget']  = $post['third-homepage-widget'];

            $data['first_inspiration_widget']  = $post['first-inspiration-widget'];
            $data['second_inspiration_widget'] = $post['second-inspiration-widget'];
            $data['third_inspiration_widget']  = $post['third-inspiration-widget'];
            $data['fourth_inspiration_widget']  = $post['fourth-inspiration-widget'];
            $data['fifth_inspiration_widget'] = $post['fifth-inspiration-widget'];
            $data['sixth_inspiration_widget']  = $post['sixth-inspiration-widget'];

            $frontendTable = $this->getTable('frontend');
            $frontendTable->saveFrontendWidgets($data);

            $data['status'] = 'OK';

            $viewModel = new JsonModel($data);

            return $viewModel;
        }

        if ($subAction == 'CreateWidget') {
            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setTemplate('admin/widgets/create-widget');

            return $viewModel;
        }

        if ($subAction == 'InsertWidget') {
            $post = $this->params()->fromPost();

            $widgetsTable = $this->getTable('widget');

            $widget = new \CPK\Widgets\Widget();
            $widget->setName($post['name']);
            $widget->setDisplay($post['display']);
            $widget->setTitleCs($post['title_cs']);
            $widget->setTitleEn($post['title_en']);
            $widget->setShowAllRecordsLink(isset($post['show_all_records_link']) ? 1 : 0);
            $widget->setShownRecordsNumber($post['shown_records_number']);
            $widget->setShowCover(isset($post['show_cover']) ? 1 : 0);
            $widget->setDescription($post['description']);

            $widgetsTable->addWidget($widget);

            return $this->forwardTo('Admin', 'Widgets');
        }

        if ($subAction == 'EditWidget') {
            $widgetId = $this->params()->fromRoute('param');

            $widgetsTable = $this->getTable('widget');
            $widget = $widgetsTable->getWidgetById($widgetId);

            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setVariable('widget', $widget);
            $viewModel->setTemplate('admin/widgets/edit-widget');

            return $viewModel;
        }

        if ($subAction == 'SaveWidget') {
            $post = $this->params()->fromPost();

            $widgetsTable = $this->getTable('widget');

            $widget = new \CPK\Widgets\Widget();
            $widget->setId($post['id']);
            $widget->setName($post['name']);
            $widget->setDisplay($post['display']);
            $widget->setTitleCs($post['title_cs']);
            $widget->setTitleEn($post['title_en']);
            $widget->setShowAllRecordsLink(isset($post['show_all_records_link']) ? 1 : 0);
            $widget->setShownRecordsNumber($post['shown_records_number']);
            $widget->setShowCover(isset($post['show_cover']) ? 1 : 0);
            $widget->setDescription($post['description']);

            $widgetsTable->saveWidget($widget);

            return $this->forwardTo('Admin', 'Widgets');
        }

        if ($subAction == 'RemoveWidget') {
            $widgetId = $this->params()->fromRoute('param');

            $widgetsTable = $this->getTable('widget');
            $widget = $widgetsTable->getWidgetById($widgetId);
            $widgetsTable->removeWidget($widget);

            return $this->forwardTo('Admin', 'Widgets');
        }

        $user = $this->accessManager->getUser();

        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
        $viewModel->setVariable('user', $user);
        $viewModel->setTemplate('admin/widgets/main');

        $frontendTable = $this->getTable('frontend');
        $viewModel->setVariable('homePageWidgets', $frontendTable->getHomepageWidgets());
        $viewModel->setVariable('inspirationWidgets', $frontendTable->getInspirationWidgets());

        $widgetsTable = $this->getTable('widget');
        $widgets = $widgetsTable->getWidgets();

        $viewModel->setVariable('widgets', $widgets);

        return $viewModel;
    }

    /**
     * Infobox manager
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function infoboxAction()
    {
        if (! $this->accessManager->isLoggedIn()) {
            return $this->forceLogin();
        }

        // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        /*
         * Handle subactions
         * */
        $subAction = $this->params()->fromRoute('subaction');
        if ($subAction == 'CreateItem') {
            $user = $this->accessManager->getUser();

            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setTemplate('admin/widgets/infobox/create-item');
            $this->layout()->searchbox = false;

            return $viewModel;
        }

        if ($subAction == 'EditItem') {
            $user = $this->accessManager->getUser();

            $id = $this->params()->fromRoute('param');

            $infoboxTable = $this->getTable('infobox');
            $infoboxItem = new \CPK\Widgets\InfoboxItem();
            $infoboxItem->setId($id);
            $item = $infoboxTable->getItem($infoboxItem);

            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setVariable('item', $item);
            $viewModel->setTemplate('admin/widgets/infobox/edit-item');
            $this->layout()->searchbox = false;

            return $viewModel;
        }

        if ($subAction == 'AddItem') {
            $post = $this->params()->fromPost();

            $infoboxItem = new \CPK\Widgets\InfoboxItem();
            $infoboxItem->setTitleCs($post['title_cs']);
            $infoboxItem->setTitleEn($post['title_en']);
            $infoboxItem->setTextCs($post['text_en']);
            $infoboxItem->setTextEn($post['text_en']);
            $infoboxItem->setDateFrom($post['date_from'].' 00:00:00');
            $infoboxItem->setDateTo($post['date_to'].' 00:00:00');

            $infoboxTable = $this->getTable('infobox');
            $infoboxTable->addItem($infoboxItem);
        }

        if ($subAction == 'SaveItem') {
            $post = $this->params()->fromPost();

            $infoboxItem = new \CPK\Widgets\InfoboxItem();
            $infoboxItem->setId($post['id']);
            $infoboxItem->setTitleCs($post['title_cs']);
            $infoboxItem->setTitleEn($post['title_en']);
            $infoboxItem->setTextCs($post['text_cs']);
            $infoboxItem->setTextEn($post['text_en']);
            $infoboxItem->setDateFrom($post['date_from'].' 00:00:00');
            $infoboxItem->setDateTo($post['date_to'].' 00:00:00');

            $infoboxTable = $this->getTable('infobox');
            $infoboxTable->saveItem($infoboxItem);
        }

        if ($subAction == 'RemoveItem') {
            $id = $this->params()->fromRoute('param');

            $infoboxTable = $this->getTable('infobox');

            $infoboxItem = new \CPK\Widgets\InfoboxItem();
            $infoboxItem->setId($id);

            $infoboxTable->removeItem($infoboxItem);
        }

        $user = $this->accessManager->getUser();

        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
        $viewModel->setVariable('user', $user);
        $viewModel->setTemplate('admin/widgets/infobox/list');

        $this->layout()->searchbox = false;

        $infoboxTable = $this->getTable('infobox');
        $infobox = $infoboxTable->getItems();

        $viewModel->setVariable('infobox', $infobox);

        return $viewModel;
    }

    /**
     * Overriden createViewModel which accepts template as the 2nd arg.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * {@inheritDoc}
     * @see \VuFind\Controller\AbstractBase::createViewModel()
     *
     * @return ViewModel
     */
    protected function createViewModel($params = null, $template = null)
    {
        // We must call widgets in avery action, to load widgets to menu
        $params['widgetsForMenu'] = $this->getWidgetsForMenu();

        $vm = parent::createViewModel($params);

        if (isset($template))
            $vm->setTemplate($template);

        return $vm;
    }

    /**
     * Get widgets for menu
     *
     * @return array
     * */
    protected function getWidgetsForMenu() {
        $widgetTable = $this->getTable('Widget');
        return $widgetTable->getWidgets();
    }

    /**
     * Widgets manager
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function widgetAction()
    {
        if (! $this->accessManager->isLoggedIn()) {
            return $this->forceLogin();
        }

        // Must be an portal admin ..
        $this->accessManager->assertIsPortalAdmin();

        $widgetId = $this->params()->fromRoute('subaction');

        $widgetContentTable = $this->getTable('widgetcontent');
        $widgetTable = $this->getTable('Widget');
        $widget = $widgetTable->getWidgetById($widgetId);
        $widgetName = $widget->getName();

        /*
         * Handle subactions
         * */
        $action = $this->params()->fromRoute('param');
        if ($action == 'CreateItem') {
            $user = $this->accessManager->getUser();

            $widgetTable = $this->getTable('widget');
            $widget = $widgetTable->getWidgetById($widgetId);

            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setVariable('widgetId', $widgetId);
            $viewModel->setVariable('widgetTitle', $widgetName);
            $viewModel->setTemplate('admin/widgets/widget/create-item');
            $viewModel->setVariable('widget', $widget);
            $this->layout()->searchbox = false;

            return $viewModel;
        }

        if ($action == 'EditItem') {
            $user = $this->accessManager->getUser();

            $id = $this->params()->fromRoute('param2');

            $widgetContentTable = $this->getTable('widgetcontent');
            $widgetContent = new \CPK\Widgets\WidgetContent();
            $widgetContent->setId($id);
            $widgetContent = $widgetContentTable->getContentById($widgetContent);

            $viewModel = $this->createViewModel();
            $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
            $viewModel->setVariable('user', $user);
            $viewModel->setVariable('widgetContent', $widgetContent);
            $viewModel->setVariable('widgetId', $widgetId);
            $viewModel->setTemplate('admin/widgets/widget/edit-item');
            $viewModel->setVariable('widgetTitle', $widgetName);
            $viewModel->setVariable('widget', $widget);
            $this->layout()->searchbox = false;

            return $viewModel;
        }

        if ($action == 'AddItem') {
            $post = $this->params()->fromPost();

            $widgetContentTable = $this->getTable('widgetcontent');

            $widgetContent = new \CPK\Widgets\WidgetContent();
            $widgetContent->setWidgetId($post['widget_id']);
            $widgetContent->setValue($post['value']);
            $widgetContent->setPreferredValue(isset($post['preferred_value']) ? $post['preferred_value'] : 0);
            $widgetContent->setDescriptionCs(isset($post['description_cs']) ? $post['description_cs'] : null);
            $widgetContent->setDescriptionEn(isset($post['description_en']) ? $post['description_en'] : null);

            $widgetContentTable->addWidgetContent($widgetContent);
        }

        if ($action == 'SaveItem') {
            $post = $this->params()->fromPost();

            $widgetContentTable = $this->getTable('widgetcontent');

            $widgetContent = new \CPK\Widgets\WidgetContent();
            $widgetContent->setId($post['id']);
            $widgetContent->setWidgetId($post['widget_id']);
            $widgetContent->setValue($post['value']);
            $widgetContent->setPreferredValue(isset($post['preferred_value']) ? $post['preferred_value'] : 0);
            $widgetContent->setDescriptionCs(isset($post['description_cs']) ? $post['description_cs'] : null);
            $widgetContent->setDescriptionEn(isset($post['description_en']) ? $post['description_en'] : null);

            $widgetContentTable->saveWidgetContent($widgetContent);
        }

        if ($action == 'RemoveItem') {
            $id = $this->params()->fromRoute('param2');

            $widgetContentTable = $this->getTable('widgetcontent');

            $widgetContent = new \CPK\Widgets\WidgetContent();
            $widgetContent->setId($id);

            $widgetContentTable->removeWidgetContent($widgetContent);
        }

        $user = $this->accessManager->getUser();

        $viewModel = $this->createViewModel();
        $viewModel->setVariable('isPortalAdmin', $this->accessManager->isPortalAdmin());
        $viewModel->setVariable('user', $user);
        $viewModel->setVariable('widgetId', $widgetId);
        $viewModel->setVariable('widgetTitle', $widgetName);
        $viewModel->setTemplate('admin/widgets/widget/list');

        $this->layout()->searchbox = false;

        $widgetsContents = $widgetContentTable->getContentsByName($widgetName, false, false, true);

        $viewModel->setVariable('widgetsContents', $widgetsContents);

        return $viewModel;
    }

}

/**
 * An Access Manager serving only to Admin Controller
 * in order to have full control over accessing pages
 * dedicated to adminitrators.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
class AccessManager
{

    /**
     * Source / identifier of main portal admin
     *
     * @var string
     */
    const PORTAL_ADMIN_SOURCE = 'cpk';

    /**
     * User
     *
     * @var \CPK\Db\Row\User
     */
    protected $user;

    /**
     * Holds names of institutions user is admin of
     *
     * @var array
     */
    protected $institutionsBeingAdminAt = [];

    /**
     * Auth Manager
     *
     * @var \CPK\Auth\Manager
     */
    protected $authManager;

    /**
     * Holds info about user being portal admin
     *
     * @var bool
     */
    protected $isPortalAdmin;

    /**
     * C'tor
     *
     * Throws AuthException only if logged in user is not admin
     * in any institution he has connected.
     *
     * @param \CPK\Auth\Manager $authManager
     *
     * @throws AuthException
     */
    public function __construct(\CPK\Auth\Manager $authManager)
    {
        $this->authManager = $authManager;

        $this->init();
    }

    /**
     * Initializes institutions where is logged in user admin and
     * throws an AuthException when user is not admin in any institution
     *
     * @throws AuthException
     */
    protected function init()
    {
        $this->user = $this->authManager->isLoggedIn();

        if (! $this->user) {
            $this->isPortalAdmin = false;
            return;
        }

        if (! empty($this->user->major)) {

            $sources = explode(',', $this->user->major);

            $this->institutionsBeingAdminAt = $sources;
        }

        $libCards = $this->user->getLibraryCards(true);
        foreach ($libCards as $libCard) {

            if (! empty($libCard->major)) {

                $sources = explode(',', $libCard->major);

                $this->institutionsBeingAdminAt = array_merge($this->institutionsBeingAdminAt, $sources);
            }
        }

        if (empty($this->institutionsBeingAdminAt)) {
            throw new AuthException('You\'re not an admin!');
        }

        // Trim all elements
        $this->institutionsBeingAdminAt = array_unique(array_map('trim', $this->institutionsBeingAdminAt));

        // Is portal admin ?
        $this->isPortalAdmin = false;
        foreach ($this->institutionsBeingAdminAt as $key => $adminSource) {
            if (strtolower($adminSource) === self::PORTAL_ADMIN_SOURCE) {

                $this->isPortalAdmin = true;
                unset($this->institutionsBeingAdminAt[$key]); // Do not break because it can be defined more than one way ..
            }
        }
    }

    /**
     * Returns bool whether current user is logged in
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        if (! $this->user)
            return false;

        return true;
    }

    /**
     * Returns current User
     *
     * @return \CPK\Db\Row\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * If current user is not portal admin, it throws an \VuFind\Exception\Auth
     *
     * @throws AuthException
     */
    public function assertIsPortalAdmin()
    {
        if ($this->isPortalAdmin() === false)
            throw new AuthException('You\'re not a portal admin!');
    }

    /**
     * Returns bool whether current user is an portal admin or is not
     *
     * @return boolean
     */
    public function isPortalAdmin()
    {
        return $this->isPortalAdmin;
    }

    /**
     * Returns array of institution sources where is current
     * logged in user an admin
     *
     * @return array
     */
    public function getInstitutionsWithAdminRights()
    {
        return $this->institutionsBeingAdminAt;
    }
}
