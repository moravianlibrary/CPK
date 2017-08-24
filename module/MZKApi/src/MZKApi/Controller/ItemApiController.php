<?php
/**
 * Search API Controller
 *
 * PHP Version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace MZKApi\Controller;

use VuFindApi\Controller\ApiInterface;
use VuFindApi\Controller\ApiTrait;
use MZKApi\Formatter\ItemFormatter;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search API Controller
 *
 * Controls the Search API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ItemApiController extends \VuFind\Controller\AbstractSearch
    implements ApiInterface
{
    use ApiTrait;

    /**
     * Record formatter
     *
     * @var ItemFormatter
     */
    protected $itemFormatter;

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
     * @param ItemFormatter $if
     */
    public function __construct(ServiceLocatorInterface $sm, ItemFormatter $if)
    {
        parent::__construct($sm);
        $this->itemFormatter = $if;
        foreach ($if->getItemFields() as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['vufind.default'])) {
                $this->defaultItemFields[] = $fieldName;
            }
        }
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
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        $ils = $this->getILS();
        $driver = $ils->getDriver();

        /**
         * Parse input ID in this format:
         *
         * SIGLA.ITEM_ID
         *
         * Note: In case of Aleph we need also the bibId, so the ID looks like this:
         *
         * SIGLA.BIB_ID.ITEM_ID
         */

        list ($sigla, $item_id) = explode('.', $request['id'], 2);

        // TODO: is sigla Aleph? Parse bibId then

        // TODO: get item's status & unify the response message

        $response = [
            'resultCount' => 0,
            'sorry_message' => 'Method not fully implemented yet'
        ];


        return $this->output($response, self::STATUS_OK);
    }

}
