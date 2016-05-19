<?php

/**
 * Service dedicated to handle institutions translations requests
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
 * @package  Service
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Service;

use CPK\Controller\AdminController;
use Zend\Config\Writer\Ini as IniWriter;
use Zend\Config\Config;
use VuFind\Mailer\Mailer;
use CPK\Db\Table\InstTranslations;

/**
 * An handler for handling requests from institutions admins
 * to change their translations & approval of those translations
 * by portal admin.
 *
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 *
 */
class TranslationsHandler
{

    const SUPPORTED_TRANSLATIONS = [
        'cs',
        'en'
    ];

    /**
     * Controller which spawned this instance.
     *
     * @var AdminController
     */
    protected $ctrl;

    /**
     * Service locator
     *
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Table for Institutions Translations
     *
     * @var InstTranslations
     */
    protected $translationsTable;

    /**
     * Translator
     *
     * @var \Debug\Service\Translator
     */
    protected $translator;

    /**
     * Mini-cache for processed translations of an institution
     *
     * @var array
     */
    protected $instTranslations;

    /**
     * Config Locator
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configLocator;

    /**
     * Relative path to institutions configurations
     *
     * @var array
     */
    protected $driversPath;

    /**
     * Object holding the configuration of email to use when a configuration change is desired by some institution admin
     *
     * @var array
     */
    protected $approvalConfig;

    /**
     * Mailer to notify about changes made by institutions admins
     *
     * @var Mailer
     */
    protected $mailer;

    /**
     * Array of institution sources where is current user an admin
     *
     * @var array
     */
    protected $institutionsBeingAdminAt;

    /**
     * Object with full paths of a czech & english language files
     *
     * @var array
     */
    protected $translationsFilename = [];

    /**
     * C'tor
     *
     * @param AdminController $controller
     *
     * @throws \Exception
     */
    public function __construct(AdminController $controller)
    {
        $this->ctrl = $controller;

        $this->serviceLocator = $this->ctrl->getServiceLocator();

        $this->translationsTable = $this->serviceLocator->get('VuFind\DbTablePluginManager')->get('inst_translations');

        $this->translator = $this->serviceLocator->get('VuFind\Translator');

        $filename = $this->translator->getLocale() . '.ini';

        $mustContain = '-institutions.ini';
        $found = strrpos($filename, $mustContain, - strlen($mustContain));

        if ($found === false) {
            throw new \Exception('Language files must end with "-institutions.ini" to support institutions translations');
        }

        $languagesFullPath = $_SERVER['VUFIND_LOCAL_DIR'] . '/languages/';

        $currLang = substr($filename, 0, 2);

        foreach (self::SUPPORTED_TRANSLATIONS as $tran) {
            $this->translationsFilename[$tran] = $languagesFullPath . str_replace($currLang, $tran, $filename);

            if (! is_writable($this->translationsFilename[$tran])) {
                throw new \Exception('Either doesnt exist or cannot write to ' . $this->translationsFilename[$tran] . ' file.');
            }
        }

        $this->initConfigs();
    }

    /**
     * Initialize configurations
     *
     * @return void
     */
    protected function initConfigs()
    {
        $this->configLocator = $this->serviceLocator->get('VuFind\Config');

        $this->commonTranslationsConfig = $this->configLocator->get('');

        $multibackend = $this->configLocator->get('MultiBackend')->toArray();

        // get the drivers path
        $this->driversPath = empty($multibackend['General']['drivers_path']) ? '.' : $multibackend['General']['drivers_path'];

        // setup email
        $this->approvalConfig = $this->configLocator->get('config')['Approval']->toArray();

        if (! isset($this->approvalConfig['emailEnabled']))
            $this->approvalConfig['emailEnabled'] = false;

        if ($this->approvalConfig['emailEnabled'] && (empty($this->approvalConfig['emailFrom']) || empty($this->approvalConfig['emailTo']))) {
            throw new \Exception('Invalid Approval configuration!');
        }

        $this->mailer = $this->serviceLocator->get('VuFind\Mailer');

        $this->institutionsBeingAdminAt = $this->ctrl->getAccessManager()->getInstitutionsWithAdminRights();
    }

    /**
     * Handles POST request from a home action
     *
     * It basically processess any config change desired
     *
     * @param array $post
     */
    public function handlePostRequestFromHome()
    {
        // Do we have some POST?
        if (! empty($post = $this->ctrl->params()->fromPost())) {

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
    }

    /**
     * Handles POST request from an approval action
     */
    public function handlePostRequestFromApproval()
    {
        // Do we have some POST?
        if (! empty($post = $this->ctrl->params()->fromPost())) {

            if (! isset($post['source']))
                return;

            $source = $post['source'];

            $contactPerson = $this->getInstitutionContactPerson($source);

            // Is there a query for a config modification?
            if (isset($post['approved'])) {

                unset($post['approved']);

                $result = $this->approveRequest($post);

                if ($result) {

                    $this->sendRequestApprovedMail($source, isset($post['message']) ? $post['message'] : '', $contactPerson);

                    $msg = $this->translate('approval_succeeded');
                    $this->flashMessenger()->addSuccessMessage($msg);
                } else {

                    $msg = $this->translate('approval_failed');
                    $this->flashMessenger()->addErrorMessage($msg);
                }
            } else
                if (isset($post['denied'])) {

                    $this->transferActiveToRequested($source);

                    $this->sendRequestDeniedMail($source, $post['message'], $contactPerson);

                    $msg = $this->translate('request_successfully_denied');
                    $this->flashMessenger()->addSuccessMessage($msg);
                }
        }
    }

    /**
     * Retrieves institution translations for current admin
     *
     * @return array
     */
    public function getAdminTranslations()
    {
        $translations = [];

        $requested = [];

        $active = [];

        foreach ($this->institutionsBeingAdminAt as $source) {
            $requested[$source] = $this->getAdminRequestedTranslations($source);

            $active[$source] = $this->getSourceSpecificActiveTranslations($source);
        }

        $translations = $this->getTranslationSourceDiff($requested, $active);

        foreach ($translations as $source => $keys) {

            if (! (isset($keys['hasRequested']) && $keys['hasRequested'])) {
                $hasDiff = false;
                foreach ($keys as $key => $langTrans) {
                    foreach ($langTrans as $lang => $value) {
                        if (isset($value['diff'])) {
                            $hasDiff = true;
                            $translations[$source]['hasRequested'] = $hasDiff;
                            break;
                        }
                    }

                    if ($hasDiff)
                        break;
                }
            }
        }

        return $translations;
    }

    /**
     * Rerieves all institution translations (requested & active together).
     *
     * Returns empty active translations if there are no requested as it's
     * an unnecessary load.
     *
     * @return array
     */
    public function getAllTranslations()
    {
        $requested = $this->getAllRequestTranslations();

        $active = $this->getAllActiveTranslations();

        $translations = $this->getTranslationSourceDiff($requested, $active);

        foreach ($translations as $source => $keys) {

            if (! (isset($keys['hasRequested']) && $keys['hasRequested'])) {
                $hasDiff = false;
                foreach ($keys as $key => $langTrans) {
                    foreach ($langTrans as $lang => $value) {
                        if (isset($value['diff'])) {
                            $hasDiff = true;
                            break;
                        }
                    }
                    if ($hasDiff)
                        break;
                }
            } else {
                $hasDiff = true;
            }

            if (! $hasDiff)
                unset($translations[$source]);
        }

        return $translations;
    }

    /**
     * Retrieves all the active translations for institutions
     *
     * @return array
     */
    protected function getAllActiveTranslations()
    {
        $activeTranslations = [];

        foreach (self::SUPPORTED_TRANSLATIONS as $lang) {
            $activeTranslations[] = $this->getTranslations($lang);
        }

        $aggregatedActiveTranslations = [];

        foreach ($activeTranslations as $activeTranslation) {

            $lang = substr($activeTranslation['@parent_ini'], 0, 2);

            unset($activeTranslation['@parent_ini']);

            foreach ($activeTranslation as $translationKey => $value) {
                list ($source, $key) = explode('_', $translationKey, 2);

                $aggregatedActiveTranslations[$source][$key][$lang] = $value;
            }
        }

        return $aggregatedActiveTranslations;
    }

    /**
     * Retrieves all translations pending approval
     *
     * @return array
     */
    protected function getAllRequestTranslations()
    {
        $requestTranslations = $this->translationsTable->getAllTranslations();

        $aggregatedReqTrans = [];

        foreach ($requestTranslations as $translation) {

            unset($translation['id']);

            $source = $translation['source'];
            $key = $translation['key'];

            unset($translation['source']);
            unset($translation['key']);

            foreach ($translation as $langKey => $value) {
                $lang = reset(explode('_', $langKey));

                $aggregatedReqTrans[$source][$key][$lang] = $value;
            }
        }

        return $aggregatedReqTrans;
    }

    /**
     * Retrieves only requested translations associated with current admin & with the institution desired
     *
     * @param string $source
     *
     * @return array
     */
    protected function getAdminRequestedTranslations($source)
    {
        $requested = [];

        $rows = $this->translationsTable->getInstitutionTranslations($source);

        foreach ($rows as $row) {

            $key = $row->key;

            $requested[$key]['cs'] = $row->cs_translated;
            $requested[$key]['en'] = $row->en_translated;
        }

        return $requested;
    }

    /**
     * Gets the translations starting with $source_RESTOFKEY from all the languages
     *
     * @param string $source
     *
     * @return array
     */
    protected function getSourceSpecificActiveTranslations($source)
    {
        if (isset($this->instTranslations[$source]))
            return $this->instTranslations[$source];

        $sourceTrans = [];

        $sourceLength = strlen($source);

        foreach (self::SUPPORTED_TRANSLATIONS as $lang) {

            $translations = $this->getTranslations($lang);
            $sourceTran = array_filter($translations, function ($key) use ($source, $sourceLength) {
                return substr($key, 0, $sourceLength) === $source;
            }, ARRAY_FILTER_USE_KEY);

            if (! empty($sourceTran)) {

                // Remove the source prefix

                $sourceTranUnprefixed = [];
                foreach ($sourceTran as $key => $val) {

                    $newKey = substr($key, $sourceLength + 1);

                    if ($newKey !== '')
                        $sourceTranUnprefixed[$newKey] = $val;
                }

                foreach ($sourceTranUnprefixed as $key => $value) {
                    $sourceTrans[$key][$lang] = $value;
                }
            }
        }

        $this->instTranslations[$source] = $sourceTrans;

        return $sourceTrans;
    }

    /**
     * Retrieves joined translations keys typically from within the requested & the active translations objects.
     *
     * Note that it joins the translations only on a institution scope.
     *
     * @param array $transA
     * @param array $transB
     *
     * @return array
     */
    protected function getJoinedTranslationsKeys($transA, $transB)
    {
        $merged = array_merge_recursive($transA, $transB);

        $joinedKeys = [];

        foreach ($merged as $lang => $keyVals) {

            $joinedKeys[$lang] = [];

            foreach ($keyVals as $key => $val) {
                if (array_search($key, $joinedKeys) === false) {
                    $joinedKeys[$lang][] = $key;
                }
            }
        }

        return $joinedKeys;
    }

    /**
     * Returns translation as an php object
     *
     * @param string $lang
     *
     * @return array
     */
    protected function getTranslations($lang)
    {
        if (! isset($this->__translations)) {
            $this->__translations = [];
        }

        if (isset($this->__translations[$lang])) {
            return $this->__translations[$lang];
        }

        if (! isset($this->translationsFilename[$lang]))
            throw new \Exception('Unknown language file requested "' . $lang . '"');

        $this->__translations[$lang] = parse_ini_file($this->translationsFilename[$lang]);

        return $this->__translations[$lang];
    }

    /**
     * Returns the translations diff from requested translations & active translations
     *
     * @param array $requested
     * @param array $active
     */
    protected function getTranslationSourceDiff(array $requested, array $active)
    {
        $translations = [];

        $deleteAllTranslationsRequested = [];

        foreach ($requested as $source => $sourceTranslation) {

            $deleteAllTranslationsRequested[$source] = empty($sourceTranslation);
            foreach ($sourceTranslation as $key => $langTranslations) {
                foreach ($langTranslations as $lang => $value) {

                    if (! isset($active[$source][$key][$lang])) {

                        $translations[$source][$key][$lang]['diff']['new'] = $value;
                    } else
                        if ($active[$source][$key][$lang] !== $value) {

                            $translations[$source][$key][$lang]['diff']['new'] = $value;
                            $translations[$source][$key][$lang]['diff']['old'] = $active[$source][$key][$lang];
                            unset($active[$source][$key][$lang]);
                        } else {

                            $translations[$source][$key][$lang] = $value;
                            unset($active[$source][$key][$lang]);
                        }
                }

                if (isset($active[$source][$key]) && empty($active[$source][$key]))
                    unset($active[$source][$key]);
            }

            if (isset($active[$source]) && empty($active[$source]))
                unset($active[$source]);
        }

        foreach ($active as $source => $sourceTranslation) {

            // We need to know when admin wants to delete all his translations
            $translations[$source]['hasRequested'] = ! isset($requested[$source]) || (empty($requested[$source]) && ! empty($active[$source]));


                foreach ($sourceTranslation as $key => $langTranslations) {

                    // Now we need to know if there was at least one translation removed
                    if (! isset($requested[$source][$key])) {
                        $translations[$source]['hasRequested'] = true;

                        $json = [
                            'source' => $source,
                            'key' => $key
                        ];

                        $json = array_merge($json, $langTranslations);

                        $translations[$source][$key]['deleted']['JSON'] = json_encode($json);

                        $translations[$source][$key]['deleted'] = array_merge($translations[$source][$key]['deleted'], $langTranslations);

                        break;
                    }
                }
        }

        return $translations;
    }

    /**
     * Process a configuration change request
     *
     * @param array $post
     */
    protected function processChangeRequest($post)
    {
        if (! $this->changedSomething($post)) {
            $requestUnchanged = $this->translate('request_translations_denied_unchanged');
            $this->flashMessenger()->addErrorMessage($requestUnchanged);
            return;
        }

        $success = $this->createNewRequestTranslations($post);

        if ($success) {

            $requestCreated = $this->translate('request_translations_created');
            $this->flashMessenger()->addSuccessMessage($requestCreated);

            $this->sendNewRequestMail($post['source']);
        }
    }

    /**
     * Process a cancel for a configuration change
     *
     * @param array $post
     */
    protected function processCancelChangeRequest($post)
    {
        $source = $post['source'];

        if (! in_array($source, $this->institutionsBeingAdminAt)) {
            throw new \Exception("You don't have permissions to cancel requested translations of $source!");
        }

        $this->transferActiveToRequested($source);

        $requestCancelled = $this->translate('request_translations_change_cancelled');
        $this->flashMessenger()->addSuccessMessage($requestCancelled);

        $this->sendRequestCancelledMail($post['source']);
    }

    protected function transferActiveToRequested($source)
    {
        $this->translationsTable->deleteInstitutionTranslations($source);

        $active = $this->getSourceSpecificActiveTranslations($source);

        foreach ($active as $key => $langTrans) {
            $this->translationsTable->createNewTranslation($source, $key, $langTrans);
        }
    }

    protected function createNewRequestTranslations($translations)
    {
        $source = $translations['source'];
        unset($translations['source']);

        if (! in_array($source, $this->institutionsBeingAdminAt)) {
            throw new \Exception("You don't have permissions to change translations of $source!");
        }

        $this->translationsTable->deleteInstitutionTranslations($source);

        foreach ($translations as $key => $translationValues) {
            $this->translationsTable->createNewTranslation($source, $key, $translationValues);
        }

        return true;
    }

    /**
     * Returns true if provided translations differs from the currently active translations
     *
     * @param array $requested
     *
     * @return boolean
     */
    protected function changedSomething($requested)
    {
        $source = $requested['source'];

        unset($requested['source']);

        $active = $this->getSourceSpecificActiveTranslations($source);

        if (empty($active)) {

            return ! empty($requested);
        } else
            if (empty($requested)) {

                return ! empty($active);
            } else
                foreach ($active as $key => $langTrans) {

                    if (isset($requested[$key])) {
                        foreach ($langTrans as $lang => $oldValue) {
                            if (isset($requested[$key][$lang])) {

                                $newValue = $requested[$key][$lang];

                                if ($newValue !== $oldValue) {
                                    return true;
                                }

                                unset($requested[$key][$lang]);
                            } else {
                                // A language definition was deleted
                                return true;
                            }
                        }

                        if (! empty($requested[$key])) {
                            // New language definition was added
                            return true;
                        }
                    } else {
                        // There was deleted a key
                        return true;
                    }
                }

            // Return true if there was not unset any key (usually new key)
        return ! empty($requested);
    }

    /**
     * Approves an configuration request made by institution admin
     *
     * @param string $source
     *
     * @return boolean $result
     */
    protected function approveRequest($translations)
    {
        $source = $translations['source'];

        unset($translations['source']);

        $sourceLength = strlen($source);

        $aggregatedTranslations = [];

        // Aggregate the languages
        foreach (self::SUPPORTED_TRANSLATIONS as $language) {

            foreach ($translations as $key => $langTrans) {

                if (isset($langTrans[$language]))
                    $aggregatedTranslations[$language][$key] = $langTrans[$language];
            }
        }

        foreach (self::SUPPORTED_TRANSLATIONS as $language) {

            $currentActiveLangTranslations = $this->getTranslations($language);

            if (empty($aggregatedTranslations)) {
                // Delete all occurrences of the source

                // Iterate over all translations within this language
                foreach ($currentActiveLangTranslations as $key => $value) {
                    if (substr($key, 0, $sourceLength) === $source) {
                        // We found a translation of this institution, so remove it
                        unset($currentActiveLangTranslations[$key]);
                    }
                }
            } else {
                // Apply the changes of the source

                $langTranslations = $aggregatedTranslations[$language];

                // Iterate over all translations within this language
                foreach ($currentActiveLangTranslations as $key => $value) {
                    if (substr($key, 0, $sourceLength) === $source) {
                        // We found a translation of this institution

                        $shortKey = substr($key, $sourceLength + 1);

                        if (! isset($langTranslations[$shortKey])) {
                            unset($currentActiveLangTranslations[$key]);
                        } elseif ($langTranslations[$shortKey] != $value) {
                            $currentActiveLangTranslations[$key] = $langTranslations[$shortKey];
                        }
                        unset($langTranslations[$shortKey]);
                    }
                }

                // And finally add new values
                foreach ($langTranslations as $newKey => $newValue) {
                    $currentActiveLangTranslations[$source . '_' . $newKey] = $newValue;
                }
            }

            $currentActiveLangTranslations = $this->cleanData($currentActiveLangTranslations);

            // Actualize the cache
            $this->instTranslations[$source] = $translations;
            $this->__translations[$language] = $currentActiveLangTranslations;

            $currentActiveLangTranslations = new Config($currentActiveLangTranslations, false);

            try {
                (new IniWriter())->toFile($this->translationsFilename[$language], $currentActiveLangTranslations);
            } catch (\Exception $e) {
                throw new \Exception("Cannot write to file '$this->translationsFilename[$language]'. Please fix the permissions by running: 'sudo chown www-data $this->translationsFilename[$language]'");
            }
        }

        $this->transferActiveToRequested($source);

        return true;
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
     * Returns email of an contact person within an institution
     *
     * @param string $source
     *
     * @return string
     */
    protected function getInstitutionContactPerson($source)
    {
        $institutionCfgPath = $this->driversPath . '/' . $source;

        $institutionCfg = $this->configLocator->get($institutionCfgPath)->toArray();

        return $institutionCfg['Catalog']['contactPerson'];
    }

    /**
     * Sends an information email about a translations request change has beed cancelled
     *
     * @param string $source
     */
    protected function sendRequestCancelledMail($source)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Zrušení žádosti o změnu překladů u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" zrušil žádost o změnu překladů.';

            return $this->sendMailToPortalAdmin($subject, $message);
        }

        return false;
    }

    /**
     * Sends an information email about a new translations request
     *
     * @param string $source
     */
    protected function sendNewRequestMail($source)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Žádost o změnu překladů u instituce ' . $source;

            $message = 'Administrátor č. ' . $_SESSION['Account']['userId'] . ' instituce "' . $source . '" vytvořil žádost o změnu překladů.';

            return $this->sendMailToPortalAdmin($subject, $message);
        }

        return false;
    }

    /**
     * Sends an information email about a translations request has been approved
     *
     * @param string $source
     * @param string $message
     * @param string $to
     */
    protected function sendRequestApprovedMail($source, $message, $to)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Schválení žádosti o změnu překladů u instituce ' . $source;

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě jsme Vám schválili Vaši žádost o změnu překladů v instituci ' . $source . '\r\n\r\n' . $message;

            return $this->sendMailToContactPerson($subject, $message, $to);
        }

        return false;
    }

    /**
     * Sends an information email about a translations request has been denied
     *
     * @param string $source
     * @param string $message
     * @param string $to
     */
    protected function sendRequestDeniedMail($source, $message, $to)
    {
        if ($this->approvalConfig['emailEnabled']) {

            $subject = 'Žádost o změnu překladů u instituce ' . $source . ' byla zamítnuta';

            $message = 'Vážený administrátore č. ' . $_SESSION['Account']['userId'] . ',\r\n\r\n právě Vám byla Vaše žádost o změnu překladů v instituci ' . $source . ' zamítnuta.\r\n\r\n' . $message;

            return $this->sendMailToContactPerson($subject, $message, $to);
        }

        return false;
    }

    /**
     * Sends an email as defined within a config at section named Config_Change_Mailer
     *
     * @param string $subject
     * @param string $message
     */
    protected function sendMailToPortalAdmin($subject, $message)
    {
        $from = new \Zend\Mail\Address($this->approvalConfig['emailFrom'], $this->approvalConfig['emailFromName']);

        return $this->mailer->send($this->approvalConfig['emailTo'], $from, $subject, $message);
    }

    /**
     * Sends an email to a contact person
     *
     * @param string $subject
     * @param string $message
     * @param string $to
     */
    protected function sendMailToContactPerson($subject, $message, $to)
    {
        $from = new \Zend\Mail\Address($this->approvalConfig['emailFrom'], $this->approvalConfig['emailFromName']);

        return $this->mailer->send($to, $from, $subject, $message);
    }

    private function translate($msg, $tokens = [], $default = null)
    {
        return $this->ctrl->translate($msg, $tokens, $default);
    }

    private function flashMessenger()
    {
        return $this->ctrl->flashMessenger();
    }
}