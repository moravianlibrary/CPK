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
	    $vars = [];

	    $subAction = $this->params()->fromRoute('subaction');
	    $post = $this->params()->fromPost();

	    $config = $this->getConfig();

	    $template = 'portal/feedback';

	    if ($subAction == 'RequestHelp') {
	        $secretKey = $config->Captcha->secretKey;

	        $recaptcha = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secretKey.'&response=' . $post['g-recaptcha-response']));

	        if ($recaptcha->{'success'} == 'false') {
	            $systemTable = $this->getTable("system");
    	        $lastHelpId = $systemTable->getAmountOfSentHelps();
    	        $helpId = $lastHelpId + 1;

    	        $vars['status'] = 'Request for help was sent';
    	        $recipients = explode(",", $config->Feedback->RequestHelpRecipients);
    	        $this->sendMailToPersons('CPK feedback: žádost o pomoc [č. '.$helpId.']', $post['text'], $recipients, $post['email'], $post['name']);
    	        $systemTable->setAmountOfSentHelps($helpId);
    	        $template = 'portal/feedback-sent';
	        } else {
	            if ($recaptcha->{'error-codes'}) {
	                $vars['captchaError'] = 'captcha_authentication_error_occurred';
	                if ($recaptcha->{'error-codes'}[0]  == 'missing-input-secret') {
	                   $vars['captchaErrorLog'] = 'Secret kód nebyl serveru předán';
	                } elseif ($recaptcha->{'error-codes'}[0]  == 'invalid-input-secret') {
	                    $vars['captchaErrorLog'] = 'Secret kód je neplatný';
	                } elseif ($recaptcha->{'error-codes'}[0]  == 'missing-input-response') {
	                    $vars['captchaErrorLog'] = 'Odpověď klienta nebyla serveru předána';
	                } elseif ($recaptcha->{'error-codes'}[0]  == 'invalid-input-response') {
	                    $vars['captchaErrorLog'] = 'Odpověď klienta je neplatná';
	                }
	            }
	            $vars['email'] = $post['email'];
	            $vars['text'] = $post['text'];
	            $vars['name'] = $post['name'];
	            $vars['subAction'] = 'RequestHelp';
	            $vars['activeTab'] = 'help';
	        }
	    }

	    if ($subAction == 'ReportBug') {
			$secretKey = $config->Captcha->secretKey;

	        $recaptcha = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$secretKey.'&response=' . $post['g-recaptcha-response']));

			if ($recaptcha->{'success'} == 'true') {
			    $systemTable = $this->getTable("system");
    	        $lastHelpId = $systemTable->getAmountOfSentHelps();
    	        $helpId = $lastHelpId + 1;

    	        $vars['status'] = 'Bug was reported';
    	        $recipients = explode(",", $config->Feedback->ReportBugRecipients);
    	        $this->sendMailToPersons('CPK feedback: ohlášení chyby [č. '.$helpId.']', $post['text'], $recipients, $post['email'], $post['name']);
    	        $systemTable->setAmountOfSentHelps($helpId);
    			$template = 'portal/feedback-sent';
			} else {
			    if ($recaptcha->{'error-codes'}) {
	                $vars['captchaError'] = 'captcha_authentication_error_occurred';
	                if ($recaptcha->{'error-codes'}  == 'missing-input-secret') {
	                   $vars['captchaErrorLog'] = 'Secret kód nebyl serveru předán';
	                } elseif ($recaptcha->{'error-codes'}  == 'invalid-input-secret') {
	                    $vars['captchaErrorLog'] = 'Secret kód je neplatný';
	                } elseif ($recaptcha->{'error-codes'}  == 'missing-input-response') {
	                    $vars['captchaErrorLog'] = 'Odpověď klienta nebyla serveru předána';
	                } elseif ($recaptcha->{'error-codes'}  == 'invalid-input-response') {
	                    $vars['captchaErrorLog'] = 'Odpověď klienta je neplatná';
	                }
	            }
	            $vars['email'] = $post['email'];
	            $vars['text'] = $post['text'];
	            $vars['name'] = $post['name'];
	            $vars['subAction'] = 'ReportBug';
	            $vars['activeTab'] = 'bugreport';
			}
	    }

	    $config = $this->getConfig();
        $siteKey = $config->Captcha->siteKey;
        $vars['siteKey'] = $siteKey;

	    $view = $this->createViewModel($vars);
	    $view->setTemplate($template);

	    if ($user = $this->getAuthManager()->isLoggedIn()) {
	        $view->setVariable('userFullName', trim($user['firstname'].' '.$user['lastname']));
	        $view->setVariable('userEmail', $user['email']);
	    }

	    return $view;
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