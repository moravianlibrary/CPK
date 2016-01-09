<?php

/**
 * Global Notification view Helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace CPK\View\Helper\CPK;

use Zend\Config\Config;
use VuFind\View\Helper\Root\TransEsc;

/**
 * Global Notifications view Helper
 *
 * @category VuFind2
 * @package View_Helpers
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class GlobalNotifications extends \Zend\View\Helper\AbstractHelper {
    
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;
    
    /**
     * Active User's language
     *
     * @var string
     */
    protected $lang;
    
    /**
     * Translation helper
     *
     * @var TransEsc
     */
    protected $translator;
    
    /**
     * If we have anything to notify
     *
     * @var boolean
     */
    protected $enabled;
    
    /**
     * Associative array holding global elements
     *
     * @var array
     */
    protected $classes;
    
    /**
     * Associative array holding supported languages
     *
     * @var array
     */
    protected $supportedLanguages;
    
    /**
     * Name of the config's variable holding the array of the messages to print
     *
     * @var string
     */
    protected $messagesVariableName;

    /**
     * Constructor
     *
     * @param
     *            \Zend\Config\Config VuFind configuration
     */
    public function __construct(Config $config, $lang, TransEsc $translator) {
        $this->config = $config;
        $this->translator = $translator;
        
        $this->lang = explode( '-', $lang )[0];
        
        if ($this->config['Global']['enabled'] !== null) {
            $this->enabled = $this->config['Global']['enabled'];
        } else {
            $this->enabled = false;
        }
        
        if ($this->config['Global']['classes'] !== null) {
            $this->classes = $this->config['Global']['classes']->toArray();
        } else {
            $this->classes = [];
        }
        
        if ($this->config['Global']['messagesLangDefinition'] !== null) {
            $this->supportedLanguages = $this->config['Global']['messagesLangDefinition']->toArray();
            
            if ($this->supportedLanguages[$this->lang] !== null)
                $this->messagesVariableName = $this->supportedLanguages[$this->lang];
            else
                $this->messagesVariableName = false;
        } else {
            $this->supportedLanguages = [];
            $this->messagesVariableName = false;
        }
    }
    
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * Returns URL of the institution's logo specified by the source.
     *
     * @param string $source            
     */
    public function renderAll() {
        if ($this->enabled) {
            $html = '';
            
            if ($this->messagesVariableName === false) {
                $errMsg = "Could not load the notifications.ini properly. (Couldn't find definition of ${$this->lang} language's messages)";
                return $this->createNotification( $errMsg, 'danger' );
            }
            
            foreach ( $this->classes as $class ) {
                $html .= $this->parseMessages( $class );
            }
            
            return $html;
        } else {
            $message = $this->translator->__invoke( 'without_notifications' );
            return $this->createNotification( $message, 'success without-notifs', false );
        }
        return '';
    }

    protected function parseMessages($class) {
        if ($this->config[$class][$this->messagesVariableName] !== null) {
            
            $messages = $this->config[$class][$this->messagesVariableName];
            
            $html = '';
            
            foreach ( $messages as $message ) {
                
                $html .= $this->createNotification( $message, $class );
            }
            
            return $html;
        }
        
        return '';
    }

    protected function createNotification($message, $class = 'default', $shouldIncrementTheCounter = true) {
        return '<div class="notif-' . $class . ($shouldIncrementTheCounter ? '' : ' counter-ignore') . '">' . htmlspecialchars( 
                $message ) . '</div>';
    }
}
