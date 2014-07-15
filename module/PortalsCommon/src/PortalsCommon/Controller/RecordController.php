<?php

namespace PortalsCommon\Controller;

use MZKCommon\Controller\RecordController as RecordControllerBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 */
class RecordController extends RecordControllerBase
{
    
    protected function createEmailViewModel($params = null)
    {
        $driver = $this->loadRecord();
        $view = parent::createEmailViewModel($params);
        $view->id = $driver->getId();

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
     * overrides parent email action, adds exporting record support to email
     */
    public function emailAction()
    {
        // If a URL was explicitly passed in, use that; otherwise, try to
        // find the HTTP referrer.
        $view = $this->createEmailViewModel();

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

        // Process form submission:
        if ($this->formWasSubmitted('submit', $this->useRecaptcha())) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                $format = $this->params()->fromPost('export_format');
                $content = $this->recordToString($view->id, $this->getExport(), $format);
                // If we got this far, we're ready to send the email:
                $this->getServiceLocator()->get('VuFind\Mailer')->sendLink(
                    $view->to, $view->from, $view->message . PHP_EOL . PHP_EOL . $content,
                    $view->url, $this->getViewRenderer()
                );
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('email_success');
                 return $this->redirectToRecord();
            } catch (Exception $e) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }
        return $view;
    }

    protected function recordToString($id, $export, $format)
    {
        if (empty($id)) {
            return '';
        }
        $id = 'VuFind|' . $id;
        $records = $this->getRecordLoader()->loadBatch(array($id));
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $parts = array();
        foreach ($records as $record) {
            $part = $recordHelper($record)->getExport($format);
            if ($part) $parts[] = $part;
        }
        return implode (PHP_EOL . PHP_EOL, $parts);
    }

    protected function getExport()
    {
        return $this->getServiceLocator()->get('VuFind\Export');
    }

    protected function useRecaptcha() {
        $authM = $this->getServiceLocator()->get('VuFind\AuthManager');
        return $authM ? !$authM->isLoggedIn() : true;
    }

    /**
     * overriden implementation, submit field is never sent for some reason???
     */
    protected function formWasSubmitted($submitElement = 'submit',
        $useRecaptcha = false) {

        $submited = $this->params()->fromPost($submitElement, false);
        if (!$submited) {
        $submited = 
          $this->params()->fromPost('id', false) &&
          $this->params()->fromPost('source', false) &&
          $this->params()->fromPost('from', false) &&
          $this->params()->fromPost('to', false) &&
          $this->params()->fromPost('export_format', false);
        }

        // Fail if the expected submission element was missing from the POST:
        // Form was submitted; if CAPTCHA is expected, validate it now.
        return $submited && (!$useRecaptcha || $this->recaptcha()->validate());
    }
}
