<?php
/**
 * Record Controller
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
 * @author   Martin Kravec <Martin.Kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace CPK\Controller;

use CPK\Ziskej\Ziskej;
use Mzk\ZiskejApi\RequestModel\Reader;
use Mzk\ZiskejApi\RequestModel\Ticket;
use VuFind\Controller\RecordController as RecordControllerBase;
use VuFind\Controller\HoldsTrait as HoldsTraitBase;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Db\Row\User as UserRow;
use VuFind\Log\LoggerAwareTrait;
use Zend\Http\PhpEnvironment\Request;
use Zend\Json\Json;
use Zend\Log\LoggerAwareInterface;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package Controller
 * @author Martin Kravec <Martin.Kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class RecordController extends RecordControllerBase implements LoggerAwareInterface
{

    use LoginTrait, HoldsTrait, HoldsTraitBase, LoggerAwareTrait {
        HoldsTrait::holdAction insteadof HoldsTraitBase;
    }

    protected $recordLoader = null;

    /**
     * Should we log statistics?
     *
     * @var bool
     */
    protected $logStatistics = false;

    protected function createViewModel($params = null)
    {
        $this->layout()->librarySearch = ($this->driver instanceof \CPK\RecordDriver\SolrLibrary);
        return parent::createViewModel($params);
    }

    /**
     * Returns data for facebook meta tags
     *
     * @return array
     */
    protected function getDataForMetaTags()
    {
        $obalkyUrl = 'https://cache.obalkyknih.cz/api/cover';
        $sigla = '';
        if ( isset($this->config->ObalkyKnih->sigla) ) {
            $sigla = $this->config->ObalkyKnih->sigla;
        }
        $bibinfo = rawurlencode(json_encode($this->driver->getBibinfoForObalkyKnihV3(), JSON_HEX_QUOT | JSON_HEX_TAG));
        $keyword = rawurlencode(sprintf('advert%s record', $sigla));
        $ImgSrc = sprintf('%s?multi=%s&type=medium&keywords=%s', $obalkyUrl, $bibinfo, $keyword);
        $Title = $this->driver->getTitle();
        $Author = $this->driver->getDeduplicatedAuthors()[ 'main' ];
        //metadata passed to the view
        $metadata = [
            'og:image' => $ImgSrc,
            'og:title' => $Title,
        ];
        return $metadata;
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab Name of tab to display
     * @param bool $ajax Are we in AJAX mode?
     * @return array|bool|mixed|\Zend\Http\Response|\Zend\View\Model\ViewModel
     *
     * @throws \Http\Client\Exception
     */
    protected function showTab($tab, $ajax = false)
    {
        /* @var $request Request */
        $request = $this->getRequest();

        if ($this->params()->fromQuery('getXml')) {
            return $this->getXml();
        }

        // Special case -- handle login request (currently needed for holdings
        // tab when driver-based holds mode is enabled, but may also be useful
        // in other circumstances):
        if ($this->params()->fromQuery('login', 'false') == 'true' &&
             ! $this->getUser()) {
            return $this->forceLogin(null);
        } else
            if ($this->params()->fromQuery('catalogLogin', 'false') == 'true' &&
                 ! is_array($patron = $this->catalogLogin())) {
                return $patron;
            }

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());
        $view->records = $view->tabs['DedupedRecords']->getRecordsInGroup();

        // Set up next/previous record links (if appropriate)
        if ($this->resultScrollerActive()) {
            $driver = $this->loadRecord();
            $view->scrollData = $this->resultScroller()->getScrollData($driver);
        }

        // get 856links
        $linksFrom856 = $this->get856Links();
        if ($linksFrom856 !== false)

            $view->linksFrom856 = $linksFrom856;


        // get number of links
        $field866 = $this->get866Data();
        $noLinksFrom856 = $linksFrom856 === false ? 0 : count($linksFrom856);
        $noLinksFrom866 = $field866 === false ? 0 : count($field866);
        $view->eVersionLinksCount = $noLinksFrom856 + $noLinksFrom866;

        $fieldsOf7xx = explode(",", $this->getConfig()->Record->fields_in_core);
        $subfieldsOf733 = [
            't',
            'd',
            'x',
            'g',
            'q',
            '9',
            'z'
        ];
        foreach ($fieldsOf7xx as $field) {
            $field7xx = $this->driver->get7xxField($field, $subfieldsOf733);
            if ($field7xx !== false) {
                $varName = 'field' . $field;
                $view->$varName = $field7xx;
            }
        }

        $view->offlineFavoritesEnabled = false;

        if ($this->getConfig()->Site['offlineFavoritesEnabled'] !== null) {
            $view->offlineFavoritesEnabled = (bool) $this->getConfig()->Site['offlineFavoritesEnabled'];
        }

        /* Citation style fieldset */
        $citationStyleTable = $this->getTable('citationstyle');
        $availableCitationStyles = $citationStyleTable->getAllStyles();

        $defaultCitationStyleValue = $this->getConfig()->Record->default_citation_style;

        foreach ($availableCitationStyles as $style) {
            if ($style['value'] === $defaultCitationStyleValue) {
                $defaultCitationStyle = $style['value'];
                break;
            }
        }

        /** @var UserRow|bool $user */
        $user = $this->getUser();

        $view->isLoggedIn = $user ? true : false;

        if ($user) {
            $userSettingsTable = $this->getTable("usersettings");
            $preferedCitationStyle = $userSettingsTable->getUserCitationStyle($user);
        }

        $selectedCitationStyle = (!empty($preferedCitationStyle))
            ? $preferedCitationStyle
            : $defaultCitationStyle;

        $view->selectedCitationStyle = $selectedCitationStyle;

        $view->availableCitationStyles = $availableCitationStyles;

        $config = $this->getConfig();
        $view->config = $config;
        $view->maxSubjectsInCore = $config['Record']['max_subjects_in_core'];

        /* Handle view template */
        if (!empty($this->params()->fromQuery('searchTypeTemplate'))) {
            $view->searchTypeTemplate = $this->params()->fromQuery('searchTypeTemplate');
        } else {
            $view->searchTypeTemplate = 'basic';
        }

        //set username for comments if user have come from social network and don`t have firstname and lastname
        if($this->getUser()
            && $this->getUser()->isSocialUser()
            && !$this->getUser()->firstname
            && !$this->getUser()->lastname
        ) {
            $view->socialUser = $this->getUser()->getSource().'_user';
        }

        $view->user = $this->getUser();
        $view->setTemplate($ajax ? 'record/ajaxtab' : 'record/view');

        $referer = $this->params()->fromQuery('referer', false);
        if ($referer) {
            $view->referer = $referer;
            $view->refererUrl = $this->base64url_decode($referer);
        }

        $view->apikey= (isset($this->getConfig()->GoogleMaps->apikey) && ! empty($this->getConfig()->GoogleMaps->apikey)) ? $this->getConfig()->GoogleMaps->apikey : null;

        $this->layout()->recordView = true;

        /* Get Library ID */
        $multiBackendConfig = $this->getConfig('MultiBackend');
        $recordSource = explode(".", $this->driver->getUniqueId())[0];
        try {
            $view->libraryID = $multiBackendConfig->LibraryIDMapping->$recordSource;
        } catch (\Exception $e){}

        $searchesConfig = $this->getConfig('searches');
        // If user have preferred limit and sort settings
        if ($user) {
            $userSettingsTable = $this->getTable("usersettings");
            $preferredRecordsPerPage = $userSettingsTable->getRecordsPerPage($user);
            $preferredSorting = $userSettingsTable->getSorting($user);

            if ($preferredRecordsPerPage) {
                $this->layout()->limit = $preferredRecordsPerPage;
            } else {
                $this->layout()->limit = $searchesConfig->General->default_limit;
            }

            if ($preferredSorting) {
                $this->layout()->sort = $preferredSorting;
            } else {
                $this->layout()->sort = $searchesConfig->General->default_sort;
            }
        } else {
            $this->layout()->limit = $searchesConfig->General->default_limit;
            $this->layout()->sort = $searchesConfig->General->default_sort;
        }


        // ziskej
        /** @var \CPK\Ziskej\Ziskej $cpkZiskej */
        $cpkZiskej = $this->serviceLocator->get('CPK\Ziskej');

        // ziskej tab
        if (strtolower($tab) === 'ziskej') {

            /** @var string|null $ziskejApiUrl */
            $ziskejApiUrl = null;
            if ($cpkZiskej->isEnabled()) {
                if (isset($config->Ziskej) && !empty($cpkZiskej->getCurrentUrl())) {
                    $ziskejApiUrl = $cpkZiskej->getCurrentUrl();
                }
            }
            $view->ziskejApiUrl = $ziskejApiUrl;

            if ($cpkZiskej->isEnabled()) {
                /** @var string|null ziskejMinUrl */
                $view->ziskejMinUrl = $cpkZiskej->getZiskejTechlibUrl();

                /** @var string|null $ziskejTechlibFrontUrl */
                $view->ziskejTechlibFrontUrl = $cpkZiskej->getCurrentZiskejTechlibFrontUrl();

                if ($ziskejApiUrl && $user) {
                    try {
                        /** @var \Mzk\ZiskejApi\Api $ziskejApi */
                        $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');
                        $ziskejActive = true;

                        /** @var \CPK\ILS\Driver\MultiBackend $ilsDriver */
                        $ilsDriver = $this->getILS()->getDriver();

                        // list of ziskej libraries sigla
                        $ziskejLibs = $ziskejApi->getLibraries()->getAll();

                        /** @var array $ziskejLibsIds */
                        $ziskejLibsIds = [];
                        foreach ($ziskejLibs as $ziskejLib) {
                            $id = $ilsDriver->siglaToSource($ziskejLib->getSigla());
                            if (!empty($id)) {
                                $ziskejLibsIds[] = $id;
                            }
                        }
                        $view->ziskejLibsIds = $ziskejLibsIds;

                        if ($user) {
                            /** @var array $connectedLibs */
                            $connectedLibs = [];
                            /** @var \VuFind\Db\Row\UserCard $userCard */
                            foreach ($user->getLibraryCards() as $userCard) {
                                //@todo refactor to array_filter
                                if (!empty($userCard->home_library)) {
                                    if (in_array($userCard->home_library, $ziskejLibsIds)) {
                                        $connectedLibs[$userCard->home_library]['userCard'] = $userCard;
                                        $connectedLibs[$userCard->home_library]['ziskejReader'] = $ziskejApi->getReader($userCard->eppn);
                                    }
                                }
                            }
                            $view->connectedLibs = $connectedLibs;
                        }

                    } catch (\Exception $e) {
                        $ziskejActive = false;
                    }
                    $view->ziskejActive = $ziskejActive;
                }
            }
        }

        $view->serverName = $request->getServer()->SERVER_NAME;
        $view->entityId = $request->getServer('Shib-Identity-Provider');

        $defaultAskedDate = new \DateTime();
        $defaultAskedDate->add(new \DateInterval('P1M'));
        $view->defaultAskedDate = $defaultAskedDate;

        $_SESSION['VuFind\Search\Solr\Options']['lastLimit'] = $this->layout()->limit;
        $_SESSION['VuFind\Search\Solr\Options']['lastSort']  = $this->layout()->sort;

        return $view;
    }

    public function mvsFormAction()
    {
        try {
            /** @var \Mzk\ZiskejApi\Api $ziskejApi */
            $ziskejApi = $this->serviceLocator->get('Mzk\ZiskejApi\Api');

            /** @var \VuFind\ILS\Connection $ils */
            $ils = $this->getILS();

            /** @var \CPK\ILS\Driver\MultiBackend $driver */
            $driver = $ils->getDriver();

            /** @var \CPK\Db\Row\User $user */
            $user = $this->getUser();

            /** @var array $params */
            $params = $this->params()->fromPost();

            /** @var string $eppn */
            $eppn = $params['eppn'];

            /** @var string $email */
            $email = $params['email'];
            //@todo test email format and !null

            if (!$params['is_conditions']) {
                $this->flashMessenger()->addMessage('ziskej_error_is_conditions', 'error');
                return $this->redirectToRecord('', 'Ziskej');
            }

            if (!$params['is_price']) {
                $this->flashMessenger()->addMessage('ziskej_error_is_price', 'error');
                return $this->redirectToRecord('', 'Ziskej');
            }

            $responseReader = new Reader(
                $user->firstname,
                $user->lastname,
                $email,
                $driver->sourceToSigla($user->home_library),
                true,
                true,
                !empty($user->cat_username) ? $user->cat_username : null
            );

            if ($ziskejApi->getReader($eppn)) {
                $reader = $ziskejApi->updateReader($eppn, $responseReader);
            } else {
                $reader = $ziskejApi->createReader($eppn, $responseReader);
            }

            if (!$reader->isActive()) {
                $this->flashMessenger()->addMessage('ziskej_error_account_not_active', 'warning');
                //@todo next step
                return $this->redirectToRecord('', 'Ziskej');
            }

            $ticketNew = new Ticket($params['doc_id']);
            $ticketNew->setDocumentAltIds($params['doc_alt_ids']);
            $ticketNew->setNote($params['text']);

            $ticket = $ziskejApi->createTicket($eppn, $ticketNew);

            $this->flashMessenger()->addMessage('ziskej_success_order_finished', 'success');
            $this->flashMessenger()->addMessage('Objednávka nyní čeká na úhradu.', 'warning');

            return $this->redirect()->toRoute('MyResearch-ziskejTicket', [
                'eppn_domain' => substr(strrchr($eppn, "@"), 1),
                'ticket_id' => $ticket->getId(),
            ]);

        } catch (\Exception $ex) {
            //$this->flashMessenger()->addMessage('ziskej_warning_api_disconnected code 813', 'warning');
            $this->flashMessenger()->addMessage($ex->getMessage(), 'error');
        }

        return $this->redirectToRecord('', 'Ziskej');
    }

    public function getContent(\Zend\Http\Response $response)
    {
        $responseContent = [];
        if (!empty($response)) {
            $responseContent = Json::decode($response->getContent(), true);
        }

        return $responseContent;
    }

    protected function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Returns links from SOLR indexed from 856
     *
     * @return  string
     */
    protected function get856Links()
    {
        return $this->getParentRecordDriver()->get856Links();
    }

    /**
     * Returns data from SOLR representing links and metadata to access SFX
     *
     * @return  array
     */
    protected function get866Data()
    {
        return $this->getParentRecordDriver()->get866Data();
    }

    /**
     * Support method to load tab information from the RecordTabPluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        parent::loadTabDetails();

        if ($this->driver instanceof \CPK\RecordDriver\SolrLibrary) return;

        if (empty($this->driver->getRealTimeHoldings())) {

            // If there is no real holding to display, than show EVersion tab if
            // there is something ..
            if (count($this->get856Links()) || count($this->get866Data()))
                $this->defaultTab = 'EVersion';
        }
    }

    protected function getXml()
    {
        $recordID = $this->driver->getUniqueID();
        $parentRecordDriver = $this->getParentRecordDriver();

        $format = $parentRecordDriver->getRecordType();
        if ($format === 'marc')
            $format .= '21';
        $recordXml = $parentRecordDriver->getXml($format);

        session_regenerate_id();
        $sessionId = session_id();

        $hasControlfield002 = strpos($recordXml, 'controlfield tag="002"');
        if ($hasControlfield002 === false) { // there is no controlfield 002
            $afterTag = '</leader>';
            $pos = strpos($recordXml, $afterTag);
            $format = $parentRecordDriver->getCitationRecordType();
            $newElement = "\n  <controlfield tag=\"002\">"
                .$format
                ."</controlfield>";
            $recordXml = substr_replace(
                $recordXml,
                $newElement,
                $pos+strlen($afterTag),
                0
            );
        }

        $xml = '<?xml version = "1.0" encoding = "UTF-8"?>
<publish-avail>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
 http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
<ListRecords>
<record>
<header>
<identifier>cpk-record-id:'.$recordID.'</identifier>
</header>
<metadata>'.$recordXml.'</metadata>
</record>
</ListRecords>
</OAI-PMH>
<session-id>'.$sessionId.'</session-id>
</publish-avail>';

        $response = new \Zend\Http\Response();
        $response->getHeaders()->addHeaderLine(
            'Content-Type',
            'text/xml; charset=utf-8'
        );
        $response->setContent($xml);
        return $response;
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function emailAction()
    {
        // Force login if necessary:
        $config = $this->getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
            && !$this->getUser()
        ) {
            return $this->forceLogin();
        }

        // Retrieve the record driver:
        $database = $this->params()->fromPost('source');
        if ($database == 'EDS') {
            $driver = $this->loadEdsRecord();
        } else {
            $driver = $this->loadRecord();
        }

        // Create view
        $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
        $view = $this->createEmailViewModel(
            null, $mailer->getDefaultRecordSubject($driver)
            );
        $mailer->setMaxRecipients($view->maxRecipients);

        // Set up reCaptcha
        $view->useRecaptcha = $this->recaptcha()->active('email');
        // Process form submission:
        if ($this->formWasSubmitted('submit', $view->useRecaptcha)) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $subject = $driver->getTitle();
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                ? $view->from : null;
                $sender = new \Zend\Mail\Address(
                    $view->from,
                    $this->translate('Central Library Portal')
                );
                $mailer->sendRecord(
                    $view->to,
                    $sender,
                    $view->message,
                    $driver,
                    $this->getViewRenderer(),
                    $subject,
                    $cc
                );
                $this->flashMessenger()->addMessage('email_success', 'success');
                return $this->redirectToRecord();
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage($e->getMessage(), 'error');
            }
        }
        return $this->redirectToRecord();
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @return AbstractRecordDriver
     */
    protected function loadEdsRecord()
    {
        // Only load the record if it has not already been loaded.  Note that
        // when determining record ID, we check both the route match (the most
        // common scenario) and the GET parameters (a fallback used by some
        // legacy routes).
        if (!is_object($this->driver)) {
            $recordLoader = $this->getRecordLoader();
            $cacheContext = $this->getRequest()->getQuery()->get('cacheContext');
            if (isset($cacheContext)) {
                $recordLoader->setCacheContext($cacheContext);
            }
            $this->driver = $recordLoader->load(
                $this->params()->fromRoute('id', $this->params()->fromQuery('id')),
                'EDS',
                false
            );
        }
        return $this->driver;
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processSave()
    {
        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Perform the save operation:
        $driver = $this->loadRecord();
        $post = $this->getRequest()->getPost()->toArray();
        $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
        $post['mytags'] = (isset($post['mytags'])) ? $tagParser->parse($post['mytags']) : [];
        $results = $driver->saveToFavorites($post, $user);

        // Display a success status message:
        $listUrl = $this->url()->fromRoute('userList', ['id' => $results['listId']]);
        $message = [
            'html' => true,
            'msg' => $this->translate('bulk_save_success') . '. '
            . '<a href="' . $listUrl . '" class="gotolist">'
            . $this->translate('go_to_list') . '</a>.'
        ];
        $this->flashMessenger()->addMessage($message, 'success');

        // redirect to followup url saved in saveAction
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();
            return $this->redirect()->toUrl($url);
        }

        // No followup info found?  Send back to record view:
        return $this->redirectToRecord();
    }

    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Save statistics:
        if ($this->logStatistics) {
            $this->getServiceLocator()->get('VuFind\RecordStats')
                ->log($this->loadRecord(), $this->getRequest());
        }

        try {
            return $this->showTab(
                $this->params()->fromRoute('tab', $this->getDefaultTab())
            );
        } catch (RecordMissingException $e) {
            return $this->notFoundAction();
        }
    }

	/**
     * Add a tag
     *
     * @return mixed
     */
    public function addtagAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save tags, if any:
        if ($this->params()->fromPost('submit')) {
            $tags = $this->params()->fromPost('tag');
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            $driver->addTags($user, $tagParser->parse($tags));
            return $this->redirectToRecord('', 'TagsAndComments');
        }

        // Display the "add tag" form:
        $view = $this->createViewModel();
        $view->setTemplate('record/addtag');
        return $view;
    }

    public function shortLoanAction()
    {
        $driver = $this->loadRecord();

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction("Holds", $driver->getUniqueID());
        if (!$checkHolds) {
            return $this->forwardTo('Record', 'Home');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        $showLinks = false;

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            $slots = $this->params()->fromPost('slot');
            if (!$slots) {
                $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_no_slot_selected_error');
            } else {
                $numOfFailures = 0;
                sort($slots);
                foreach ($slots as $slot) {
                    $details = array();
                    $details['patron'] = $patron;
                    $details['id'] = $driver->getUniqueID();
                    $details['item_id'] = $this->params()->fromQuery('item_id');
                    $details['slot'] = $slot;
                    try {
                        $result = $catalog->placeShortLoanRequest($details);
                        if (!$result['success']) {
                            $numOfFailures++;
                        }
                    } catch (\Exception $ex) {
                        $numOfFailures++;
                    }
                }
                if ($numOfFailures == count($slots)) { // All requests failed
                    $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_request_error_text');
                    $showLinks = true;
                } else if ($numOfFailures > 0) {
                    $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_request_partial_error_text');
                    $showLinks = true;
                } else {
                    $this->flashMessenger()->setNamespace('success')->addMessage('short_loan_ok_text');
                    return $this->redirectToRecord();
                }
            }
        }

        $shortLoanInfo = $catalog->getHoldingInfoForItem($patron['id'],
            $driver->getUniqueID(), $this->params()->fromQuery('item_id'));

        $slotsByDate = array();
        foreach ($shortLoanInfo['slots'] as $id => $slot) {
            $start_date = $slot['start_date'];
            $start_time = $slot['start_time'];
            $slotsByDate[$start_date][$start_time] = $slot;
            $slotsByDate[$start_date][$start_time]['id'] = $id;
            $slotsByDate[$start_date][$start_time]['available'] = true;
        }

        static $positions = array(
            '0830' => 0,
            '0900' => 0,
            '1000' => 1,
            '1100' => 1,
            '1200' => 1,
            '1400' => 2,
            '1600' => 3,
            '1700' => 3,
            '2000' => 4,
        );

        $results = array();
        foreach ($slotsByDate as $date => $slotsInDate) {
            $result = array_fill(0, 7, array('available' => false));
            foreach ($slotsInDate as $start_time => $slot) {
                $start_time = $slot['start_time'];
                $slot['start_time'] = substr($start_time, 0, 2) . ':' . substr($start_time, 2, 2);
                $end_time = $slot['end_time'];
                $slot['end_time'] = substr($end_time, 0, 2) . ':' . substr($end_time, 2, 2);
                $result[$positions[$start_time]] = $slot;
            }
            $date = date_parse_from_format('Ymd', $date);
            $date =  $date['day'] . '. ' . $date['month'] . '.';
            $results[$date] = $result;
        }

        $view = $this->createViewModel(
            array(
                'showLinks'  => $showLinks,
                'slots'      => $results,
                'callnumber' => $shortLoanInfo['callnumber']
            )
        );
        $view->setTemplate('record/shortloan');
        return $view;
    }

    protected function getParentRecordDriver()
    {
        return $this->driver->getParentRecordDriver();
    }

}
