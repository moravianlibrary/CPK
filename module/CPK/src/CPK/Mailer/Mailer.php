<?php
/**
 * CPK Mailer Class
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
 * @package  Mailer
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\Mailer;

use \VuFind\Mailer\Mailer as BaseMailer;
use VuFind\Exception\Mail as MailException;
use \Zend\View\Renderer\PhpRenderer;
use \Zend\Config\Config;
use \Zend\Mail\Transport\TransportInterface;
use CPK\Db\Table\EmailDelayer;
use CPK\Db\Table\EmailTypes;

/**
 * CPK Mailer Class
 *
 * @category VuFind2
 * @package Mailer
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Mailer extends BaseMailer
{
    /**
     * The mail portal uses for sending warnings to idps
     *
     * @var string $idpMail
     */
    protected $idpMail = null;

    /**
     * The mail portal uses for sending emails to driver configuration contacts
     *
     * @var string portalMail
     */
    protected $portalMail = null;

    /**
     * DB Table instance EmailDelayer
     *
     * @var EmailDelayer
     */
    protected $emailDelayer = null;

    public function __construct(TransportInterface $transport, Config $config, EmailDelayer $emailDelayer)
    {
        $instance = parent::__construct($transport, $config);

        if (isset($config->Mail->idpMail))
            $this->idpMail = $config->Mail->idpMail;

        if (isset($config->Mail->portalMail))
            $this->portalMail = $config->Mail->portalMail;

        $this->emailDelayer = $emailDelayer;

        return $instance;
    }

    /**
     * Sends an warning email message to all technical contacts, delimited by semicolon.
     *
     * @param string $technicalContacts
     * @param string $source
     * @param PhpRenderer $renderer
     * @param array $templateVars
     * @throws MailException
     */
    public function sendEppnMissing($technicalContacts, $source, PhpRenderer $renderer, array $templateVars = [])
    {
        if ($this->emailDelayer->canSendEmailTypeTo($technicalContacts, $source, EmailTypes::IDP_NO_EPPN)) {

            if (! isset($templateVars['failureTimes'])) {
                $templateVars['failureTimes'] = $this->emailDelayer->getSendAttemptsCount($technicalContacts, $source, EmailTypes::IDP_NO_EPPN);
            }

            $templateName = $this->getTemplateNameFor(EmailTypes::IDP_NO_EPPN);
            $body = $renderer->partial($templateName, $templateVars);

            $body .= "\n You might consider contacting technical contacts: " . $technicalContacts;

            if (isset($this->idpMail))
                $this->send($this->idpMail, $this->idpMail, $this->translate('eppn_missing_mail_subject'), $body, $this->idpMail);
            else
                throw MailException('Missing Mail->idpMail configuration');
        }
    }

    /**
     * Sends an warning email message to all administration contacts, delimited by semicolon.
     *
     * @param string $technicalContacts
     * @param string $source
     * @param PhpRenderer $renderer
     * @param array $templateVars
     * @throws MailException
     */
    public function sendApiNotAvailable($institutionAdminMail, $source, $message, PhpRenderer $renderer, array $templateVars = [])
    {
        if ($this->emailDelayer->canSendEmailTypeTo($institutionAdminMail, $source, EmailTypes::ILS_API_NOT_AVAILABLE)) {

            if (! isset($templateVars['failureTimes'])) {
                $templateVars['failureTimes'] = $this->emailDelayer->getSendAttemptsCount($institutionAdminMail, $source, EmailTypes::ILS_API_NOT_AVAILABLE);
            }

            $templateVars['source'] = $source;

            $templateVars['message'] = $message;

            $templateName = $this->getTemplateNameFor(EmailTypes::ILS_API_NOT_AVAILABLE);
            $body = $renderer->partial($templateName, $templateVars);

            if (isset($this->portalMail))
                $this->send($institutionAdminMail, $this->portalMail, $this->translate('api_not_available_mail_subject'), $body, $this->portalMail);
            else
                throw new MailException('Missing Mail->portalMail configuration');
        }
    }

    /**
     * Creates an string defining template name based on email type key
     *
     * @param string $emailTypeKey
     */
    protected function getTemplateNameFor($emailTypeKey)
    {
        return 'Email/' . $emailTypeKey . '.phtml';
    }
}
