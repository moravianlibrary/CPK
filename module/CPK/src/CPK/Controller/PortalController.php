<?php
/**
 * Portal Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2015.
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
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use VuFind\Controller\AbstractBase;
use WebDriver\Exception;

/**
 * PortalController
 *
 * @author  Martin Kravec <martin.kravec@mzk.cz>
 * @license http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class PortalController extends AbstractBase
{
    use LoginTrait;

    /**
     * Content from google recaptcha api
     */
    const RECAPTCHA_CONTENT = 'https://www.google.com/recaptcha/api/siteverify?secret=%secretKey%&response=%captchaResponse%';

	/**
	 * View page
	 *
	 * @return mixed
	 */
	public function pageAction()
	{
	    $prettyUrl = $this->params()->fromRoute('subaction');
	    $portalPagesTable = $this->getTable("portalpages");

	    if (! empty($this->params()->fromPost('mylang'))) {
	        $languageCode = $this->params()->fromPost('mylang');
	    } else if (! empty($_COOKIE['language'])) {
	        $languageCode = $_COOKIE['language'];
	    } else {
	        $config = $this->getConfig();
	        $languageCode = $config->Site->language;
	    }

	    $page = $portalPagesTable->getPage($prettyUrl, $languageCode);

	    $view = $this->createViewModel([
	       'page' => $page,
	    ]);

	    $view->setTemplate('portal/page');

	    if (! $page) $view->setTemplate('error/404');

	    if ($page['published'] != '1') {
	        $view->setTemplate('error/404');
	        $displayToken = $this->params()->fromQuery('displayToken');
	        if (! empty($displayToken)) {
	            /* @todo Rewrite next line with permissions control,
	            when method permissionsManagerAction will be finished */
    	        $randomToken = '94752eedb5baaf2896e35b4a76d9575c';
        	    if ($displayToken === $randomToken) {
        	        $view->setTemplate('portal/page');
        	    }
	        }
	    }

	    return $view;
	}

    /**
     * View feedback
     *
     * @return mixed
     */
    public function feedbackAction()
    {
        //vars to view
        $vars = [];
        $config = $this->getConfig();
        $template = 'portal/feedback';

        $post = $this->params()->fromPost();

        $secretKey = $config->Captcha->secretKey;
        $siteKey = $config->Captcha->siteKey;
        $vars['siteKey'] = $siteKey;

        $captchaContentLink = str_replace([
            '%secretKey%',
            '%captchaResponse%'
        ], [
            $secretKey ? $secretKey : 'false',
            $post['g-recaptcha-response'] ? $post['g-recaptcha-response'] : 'false',
        ], $this::RECAPTCHA_CONTENT);

        //flag to detect captcha server error
        $captchaServerError = false;

        $captchaContent = file_get_contents($captchaContentLink);
        if ($captchaContent === false) {
            $captchaServerError = true;
        }

        if ($post['submitted']) {
            try {
                $recaptcha = json_decode($captchaContent);
            } catch (Exception $e) {
                $captchaServerError = true;
            }
        }

        if ($captchaServerError) {
            $vars['captchaError'] = 'captcha_server_error_occurred';
            $view = $this->createViewModel($vars);
            $view->setTemplate($template);
            return $view;
        }

        //show error (if exists) just if sent form ($post['submitted'])
        if (isset($recaptcha) && !$recaptcha->{'success'} && $recaptcha->{'error-codes'} && isset($post['submitted'])) {
            $vars['captchaError'] = 'captcha_authentication_error_occurred';

            //Error log for debuging if something wrong with captcha
            $vars['captchaErrorLog'] = $this->getCaptchaErrorLog($recaptcha->{'error-codes'}[0]);

            //Return filled fields to user for set into inputs
            $vars['email'] = $post['email'];
            $vars['text'] = $post['text'];
            $vars['name'] = $post['name'];
        }

        if (isset($recaptcha) && $recaptcha->{'success'}) {
            $systemTable = $this->getTable("system");
            $lastHelpId = $systemTable->getAmountOfSentHelps();
            $helpId = $lastHelpId + 1;

            $message = $post['text']
                . "\r\n\r\n"
                . $this->translate('feedback_mail_page_path')
                . " "
                . $post['page_path'];

            $recipients = explode(",", $config->Feedback->RequestHelpRecipients);

            $this->sendMailToPersons(
                $this->translate('feedback_mail_theme', ['%helpId%' => $helpId]),
                $message,
                $recipients,
                $post['email'],
                $post['name']
            );

            $systemTable->setAmountOfSentHelps($helpId);
            $template = 'portal/feedback-sent';
        }

        $view = $this->createViewModel($vars);
        $view->setTemplate($template);

        if ($user = $this->getAuthManager()->isLoggedIn()) {
            $view->setVariable('userFullName', trim($user['firstname'] . ' ' . $user['lastname']));
            $view->setVariable('userEmail', $user['email']);
        }

        return $view;
    }

    /**
     * Gets error log from captcha and returns error message
     *
     * @param $errorCode
     * @return string
     */
    protected function getCaptchaErrorLog($errorCode)
    {
        switch ($errorCode) {
            case 'missing-input-secret':
                return $this->translate('feedback-missing-input-secret');
            case 'invalid-input-secret':
                return $this->translate('feedback-invalid-input-secret');
            case 'missing-input-response':
                return $this->translate('feedback-missing-input-response');
            case 'invalid-input-response':
                return $this->translate('feedback-invalid-input-response');
            default:
                return false;
        }
    }

	/**
	 * Sends an email to a contact person
	 *
	 * @param string $subject
	 * @param string $message
	 * @param array  $recipients
	 * @param string $fromEmail
	 * @param string $fromName
	 */
	protected function sendMailToPersons($subject, $message, $recipients, $fromEmail, $fromName)
	{
	    $from = new \Zend\Mail\Address($fromEmail, $fromName);
	    $mailer = $this->serviceLocator->get('VuFind\Mailer');

	    foreach($recipients as $person) {
	        $mailer->send($person, $from, $subject, $message);
	    }

	    return;
	}
}