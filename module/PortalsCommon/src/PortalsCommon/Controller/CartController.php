<?php

namespace PortalsCommon\Controller;
use VuFind\Exception\Mail as MailException,
    Zend\Session\Container as SessionContainer,
    VuFind\Controller\CartController as ParentController;

/**
 * Book Bag / Bulk Action Controller
 *
 */

class CartController extends ParentController
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Overriden parent method
     *
     * @return mixed
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

        $ids = is_null($this->params()->fromPost('selectAll'))
            ? $this->params()->fromPost('ids')
            : $this->params()->fromPost('idsAll');
        if (!is_array($ids) || empty($ids)) {
            return $this->redirectToSource('error', 'bulk_noitems_advice');
        }
        $view = $this->createEmailViewModel();
        $view->records = $this->getRecordLoader()->loadBatch($ids);

        // Process form submission:
        if ($this->formWasSubmitted('submit', $this->useRecaptcha())) {
            // Build the URL to share:
            $params = array();
            foreach ($ids as $current) {
                $params[] = urlencode('id[]') . '=' . urlencode($current);
            }
            $url = $this->getServerUrl('records-home') . '?' . implode('&', $params);
            // add content of mail
            $content = $this->recordsToString($ids, $this->params()->fromPost('export_format'));
            
            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $this->getServiceLocator()->get('VuFind\Mailer')->sendLink(
                    $view->to, $view->from, $view->message,
                    $url . PHP_EOL . PHP_EOL . $content, $this->getViewRenderer(), 'bulk_email_title'
                );
                return $this->redirectToSource('info', 'email_success');
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        return $view;
    }

    /**
     * Overriden implementation of parent method
     */
    protected function createEmailViewModel($params = null)
    {
        $view = parent::createEmailViewModel($params);
        //load export settings and add links + url
        $exportConf = $this->getServiceLocator()->get('VuFind\Config')->get('config')->Export;
        $exportArray = array('As links' =>'As links' , 'As search URL' => 'As search URL');
        if ($exportConf) {
            $exportArray = array_merge($exportArray, $exportConf->toArray());
        }
        $view->export = $exportArray;
        $view->useRecaptcha = $this->useRecaptcha();
        return $view;
    }

    /**
     *  Convert records to string in given format
     *  @param ids array of ids
     *  @param $format string 
     */
    protected function recordsToString($ids, $format)
    {
        if (empty($ids) || !is_array($ids)) {
            return '';
        }

        $records = $this->getRecordLoader()->loadBatch($ids);
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $parts = array();
        foreach ($records as $record) {
            $part = $recordHelper($record)->getExport($format);
            if ($part) $parts[] = $part;
        }
        return implode (PHP_EOL . PHP_EOL, $parts);
    }
    
    protected function useRecaptcha() {
        $authM = $this->getServiceLocator()->get('VuFind\AuthManager');
        return $authM ? !$authM->isLoggedIn() : true;
    }

}
