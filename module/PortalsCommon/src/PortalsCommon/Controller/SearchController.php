<?php

namespace PortalsCommon\Controller;

use MZKCommon\Controller\SearchController as SearchControllerBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 */
class SearchController extends SearchControllerBase
{

    /**
     * Adds export configuration to parent view
     */
    protected function createEmailViewModel($params = null)
    {
        $view = parent::createEmailViewModel($params);
        $exportIDs = $this->params()->fromQuery('exportID',  array());
        $view->ids = $exportIDs;
        
        $exportConf = $this->getServiceLocator()->get('VuFind\Config')->get('config')->Export;
        $exportArray = array('As links' =>'As links' , 'As search URL' => 'As search URL');
        if ($exportConf) {
            $exportArray = array_merge($exportArray, $exportConf->toArray());
        }
        $view->export = $exportArray;
        return $view;
    }

    /**
     * overrides parent email action, adds exporting record support to email
     */
    public function emailAction()
    {
        // If a URL was explicitly passed in, use that; otherwise, try to
        // find the HTTP referrer.
        $view = $this->createEmailViewModel();
        $view->url = $this->params()->fromPost(
            'url', $this->params()->fromQuery(
                'url', $this->getRequest()->getServer()->get('HTTP_REFERER')
            )
        );

        // Force login if necessary:
        $config = $this->getConfig();
        if ((!isset($config->Mail->require_login) || $config->Mail->require_login)
            && !$this->getUser()
        ) {
            return $this->forceLogin(null, array('emailurl' => $view->url));
        }

        // Check if we have a URL in login followup data:
        $followup = $this->followup()->retrieve();
        if (isset($followup->emailurl)) {
            $view->url = $followup->emailurl;
            unset($followup->emailurl);
        }

        // Fail if we can't figure out a URL to share:
        if (empty($view->url)) {
            throw new \Exception('Cannot determine URL to share.');
        }

        // Process form submission:
        if ($this->formWasSubmitted('submit')) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $format = $this->params()->fromPost('export_format');
                $content = $this->recordsToString($view->ids, $format);
                // If we got this far, we're ready to send the email:
                $this->getServiceLocator()->get('VuFind\Mailer')->sendLink(
                    $view->to, $view->from, $view->message . PHP_EOL . PHP_EOL . $content,
                    $view->url, $this->getViewRenderer()
                );
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('email_success');
                return $this->redirect()->toUrl($view->url);
            } catch (MailException $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }
        return $view;
    }

    /**
     * creates records string representation according to given format
     */
    protected function recordsToString($ids, $format)
    {
        if (empty($ids) || !is_array($ids)) {
            return '';
        }
        $ids = array_map(function ($arg) { return 'VuFind|' . $arg; }, $ids);
        $records = $this->getRecordLoader()->loadBatch($ids);
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $parts = array();
        foreach ($records as $record) {
            $part = $recordHelper($record)->getExport($format);
            if ($part) $parts[] = $part;
        }
        return implode (PHP_EOL . PHP_EOL, $parts);
    }
    
    /**
     *  returns export service
     */
    protected function getExport()
    {
        return $this->getServiceLocator()->get('VuFind\Export');
    }

}
