<?php
/**
 * Consolidation API Controller
 *
 * PHP Version 7
 *
 * Copyright (C) Moravian Library in Brno 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Jiri Kozlovsky <Jiri.Kozlovsky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace MZKApi\Controller;

use CPK\Auth\ShibbolethIdentityManager;
use CPK\ILS\Driver\MultiBackend;
use VuFindApi\Controller\ApiInterface;
use VuFindApi\Controller\ApiTrait;
use Zend\ServiceManager\ServiceManager;

/**
 * Consolidation API Controller
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Jiri Kozlovsky <Jiri.Kozlovsky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ConsolidationApiController extends \VuFind\Controller\AbstractSearch
    implements ApiInterface
{
    use ApiTrait;

    const USER_NOT_FOUND = 'User not found';

    /**
     * Default record fields to return if a request does not define the fields
     *
     * @var array
     */
    protected $defaultConsolidationFields = [];

    /**
     * Permission required for the consolidation endpoint
     *
     * @var string
     */
    protected $consolidationAccessPermission = 'access.api.Consolidation';

    /**
     * Holds User tableGateway to store and retrieve data from there.
     *
     * @var \CPK\Db\Table\User $userTableGateway
     */
    protected $userTableGateway = null;

    /**
     * Service locator to retrieve services dynamically on-demand
     *
     * @var ServiceManager serviceLocator
     */
    protected $serviceLocator = null;

    /**
     * This is a standalone file with filename shibboleth.ini in localconfig/config/vufind directory
     *
     * @var \Zend\Config\Config shibbolethConfig
     */
    protected $shibbolethConfig = null;

    /**
     * Constructor
     *
     * @param ServiceManager $sm Service manager
     */
    public function __construct(ServiceManager $sm)
    {
        parent::__construct($sm);
        $this->serviceLocator = $sm->getServiceLocator();
        $this->userTableGateway = $this->serviceLocator->get('VuFind\DbTablePluginManager')->get('user');
        $configLoader = $this->serviceLocator->get('VuFind\Config');
        $this->shibbolethConfig = $configLoader->get(ShibbolethIdentityManager::CONFIG_FILE_NAME);
    }

    /**
     * Returns error response
     *
     * @param string $message
     * @param int $http_status
     * @return \Zend\Http\Response
     */
    protected function errorResponse($message, $http_status = 404)
    {
        return $this->output([], self::STATUS_ERROR, $http_status, $message);
    }

    /**
     * Get Swagger specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getSwaggerSpecFragment()
    {
        $config = $this->getConfig();
        $results = $this->getResultsManager()->get($this->searchClassId);
        $options = $results->getOptions();
        $params = $results->getParams();
        $params->activateAllFacets();

        $viewParams = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
            'searchTypes' => $options->getBasicHandlers(),
            'defaultSearchType' => $options->getDefaultHandler(),
            'defaultFields' => $this->defaultConsolidationFields,
            'facetConfig' => $params->getFacetConfig(),
            'sortOptions' => $options->getSortOptions(),
            'defaultSort' => $options->getDefaultSortByHandler()
        ];
        $json = $this->getViewRenderer()->render(
            'searchapi/swagger', $viewParams
        );
        return $json;
    }

    /**
     * Execute the request
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $headers->addHeaderLine(
                'Access-Control-Allow-Methods', 'GET'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        if (null !== $request->getQuery('swagger')) {
            return $this->createSwaggerSpec();
        }
        return parent::onDispatch($e);
    }

    /**
     * Record action
     *
     * @return \Zend\Http\Response
     */
    public function consolidationAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->consolidationAccessPermission)) {
            return $result;
        }

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        $mustBeSet = ['user_id', 'entity_id'];

        foreach ($mustBeSet as $paramName) {
            if (! isset($request[$paramName])) {
                return $this->errorResponse('Missing ' . $paramName, 400);
            }
        }

        $eppn = $request['user_id'];
        $entityId = $request['entity_id'];
        $user = $this->userTableGateway->getUserRowByEppn($eppn);
        if (!user) {
            return $this->errorResponse(self::USER_NOT_FOUND);
        }
        $userEntityId = $this->homeLibraryToEntityId($user['home_library']);
        if ($entityId !== $userEntityId) {
            return $this->errorResponse(self::USER_NOT_FOUND);
        }

        $consolidated_identities = [];
        $libraryCards = $user->getLibraryCards()->toArray();

        foreach ($libraryCards as $libraryCard) {
            $cardEntityId = $this->homeLibraryToEntityId($libraryCard['home_library']);
            $cardUserId = $libraryCard['eppn'];
            if ($cardUserId == $eppn && $entityId == $cardEntityId) {
                continue;
            }

            $consolidated_identities[] = array(
                'user_id' => $cardUserId,
                'entity_id' => $cardEntityId
            );
        }

        $response = [
            'user_id' => $eppn,
            'entity_id' => $request['entity_id'],
            'consolidated_identities' => $consolidated_identities
        ];

        return $this->output($response, self::STATUS_OK);
    }

    protected function homeLibraryToEntityId($home_library)
    {
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($home_library == $name) {
                return $configuration['entityId'];
            }
        }
        return null;
    }

}
