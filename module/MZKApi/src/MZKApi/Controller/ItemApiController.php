<?php
/**
 * Item API Controller
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

use CPK\ILS\Driver\MultiBackend;
use VuFindApi\Controller\ApiInterface;
use VuFindApi\Controller\ApiTrait;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search API Controller
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Jiri Kozlovsky <Jiri.Kozlovsky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ItemApiController extends \VuFind\Controller\AbstractSearch
    implements ApiInterface
{
    use ApiTrait;

    const ERR_MSG_NOT_FOUND = 'This item identifier was not found';

    /**
     * Default record fields to return if a request does not define the fields
     *
     * @var array
     */
    protected $defaultItemFields = [];

    /**
     * Permission required for the item endpoint
     *
     * @var string
     */
    protected $itemAccessPermission = 'access.api.Item';

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
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
            'recordFields' => $this->itemFormatter->getRecordFieldSpec(),
            'defaultFields' => $this->defaultItemFields,
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
                'Access-Control-Allow-Methods', 'GET, POST, OPTIONS'
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
    public function itemAction()
    {
        // Disable session writes
        $this->disableSessionWrites();

        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->itemAccessPermission)) {
            return $result;
        }

        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['id'])) {
            return $this->errorResponse('Missing id', 400);
        }

        $ils = $this->getILS();

        $driver = $ils->getDriver();

        if (!$driver instanceof MultiBackend) {
            return $this->errorResponse('Configuration error.', 500);
        }

        /**
         * Parse input ID in this format:
         *
         * SIGLA.ITEM_ID
         *
         * Note: In case of Aleph we need also the bibId, so the ID looks like this:
         *
         * SIGLA.BIB_ID.ITEM_ID
         */

        $original_id = $request['id'];

        # Check there is at least one dot (separating SIGLA)
        if (!strpos($original_id, '.', 1))
            return $this->errorResponse(self::ERR_MSG_NOT_FOUND);

        list ($sigla, $item_id) = explode('.', $original_id, 2);

        $source = $driver->siglaToSource($sigla);

        if ($source === null)
            return $this->errorResponse(self::ERR_MSG_NOT_FOUND);

        $driverName = $driver->getDriverName($source);

        if ($driverName === null)
            return $this->errorResponse(self::ERR_MSG_NOT_FOUND);

        if ($driverName === 'Aleph') {

            # Check there is at least one dot (separating bib id from item id)
            if (!strpos($item_id, '.', 1))
                return $this->errorResponse(self::ERR_MSG_NOT_FOUND);

            list ($bib_id, $item_id) = explode('.', $item_id, 2);

            $statuses = $driver->getStatuses([$item_id], $source . '.' . $bib_id);

            // Assert something returned ..
            if (count($statuses) == 0 || !isset($statuses[0]['label']))
                return $this->errorResponse(self::ERR_MSG_NOT_FOUND);

            $status = reset($statuses);


        } else {

            // Fetch the status
            $status = $driver->getStatus($source . '.' . $item_id);
        }


        if (!isset($status['label'])) {
            return $this->errorResponse(self::ERR_MSG_NOT_FOUND);
        }

        $availability = [
            'label-success' => 'available',
            'label-warning' => 'circulating',
            'label-danger' => 'unavailable',
            'label-unknown' => 'unknown'
        ];

        $response = [
            'id' => $original_id,
            'availability' => $availability[$status['label']],
            'availability_note' => isset($status['availability']) ? $status['availability'] : null
        ];

        if (isset($request['ext'])) {
            $response['ext'] = [
                'duedate' => isset($status['duedate']) ? $status['duedate'] : null,
                'opac_status' => isset($status['status']) ? $status['status'] : null,
                'location' => isset($status['location']) ? $status['location'] : null,
                'department' => isset($status['department']) ? $status['department'] : null,
                'collection' => isset($status['collection']) ? $status['collection'] : null
            ];
        }

        return $this->output($response, self::STATUS_OK);
    }

}
