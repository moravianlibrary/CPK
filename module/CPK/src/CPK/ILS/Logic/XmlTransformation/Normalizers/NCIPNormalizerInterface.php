<?php
namespace CPK\ILS\Logic\XmlTransformation\Normalizers;

use CPK\ILS\Logic\XmlTransformation\JsonXML;

interface NCIPNormalizerInterface
{
    /**
     * Processes normalizing
     *
     * @param string $stringXml
     * @return mixed
     */
    public function normalize(string $stringXml);

    /**
     * Normalizes users fines (blocks, traps)
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupUserBlocksAndTraps(JsonXML &$response);

    /**
     * Normalizes users profile
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupUserProfile(JsonXML &$response);

    /**
     * Normalizes pick up locations
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupAgencyLocations(JsonXML &$response);

    /**
     * Normalizes users items history
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupUserLoanedItemsHistory(JsonXML &$response);

    /**
     * Normalizes item status (availability)
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupItemStatus(JsonXML &$response);

    /**
     * Normalizes item set statuses (availability)
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupItemSetStatus(JsonXML &$response);

    /**
     * Normalizes users transactions
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeLookupUserLoanedItems(JsonXML &$response);

    /**
     * Normalizes users holds
     *
     * @param JsonXML $response
     * @return mixed
     */
    public function normalizeRequestedItems(JsonXML &$response);
}