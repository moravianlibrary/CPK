<?php
/**
 * BuyChoiceHandler Model Class
 *
 * PHP version 5
 *
 * Copyright (C) MZK 2015.
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
 * @author    Martin Kravec    <kravec@mzk.cz>
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

namespace CPK\WantIt;

/**
 * BuyChoiceHandler Model
 * Provides available Buy actions on records and handles user's requests.
 *
 * @author    Martin Kravec    <kravec@mzk.cz>
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class BuyChoiceHandler extends AbstractHttpClient implements
    BuyChoiceHandlerInterface
{
    /**
     * RecordDriver
     *
     * @var \CPK\RecordDriver\SolrMarc $recordDriver
     */
    protected $recordDriver;

    public function __construct(\CPK\RecordDriver\SolrMarc $recordDriver)
    {
        $this->recordDriver = $recordDriver;
    }

    /**
     * {@inheritdoc}
     */
    public function getGoogleBooksVolumeLink()
    {
        $isbns = $this->recordDriver->getISBNs();
        $isbn = $isbns[0] ?? null;
        $lccn = $this->recordDriver->getLCCN();
        $oclc = $this->recordDriver->getCleanOCLCNum();

        if (empty($isbn) && empty($lccn) && empty($oclc)) {
            return null;
        }

        $url = 'https://www.googleapis.com/books/v1/volumes';

        if (!empty($isbn)) {
            $params = ['q' => 'isbn:' . str_replace("-", "", $isbn)];
        }
        if (!empty($lccn)) {
            $params = ['q' => 'lccn:' . $lccn];
        }
        if (!empty($oclc)) {
            $params = ['q' => 'oclc:' . $oclc];
        }

        $dataArray = $this->getRequestDataResponseAsArray($url, $params);

        $link = null;
        if ($dataArray['totalItems'] >= 1) {
            $canonicalLink = $dataArray['items'][0]['volumeInfo']['canonicalVolumeLink'] ?? null;
            $link = isset($canonicalLink) ? $canonicalLink . '&sitesec=buy' : null;
        }

        return $link;
    }

    /**
     * {@inheritdoc}
     */
    public function getZboziLink()
    {
        $isbns = $this->recordDriver->getISBNs();
        $isbn = $isbns[0] ?? null;

        if (empty($isbn)) {
            return null;
        }

        $url = 'https://www.zbozi.cz/api/v1/search';
        $params = [
            'groupByCategory' => 0,
            'loadTopProducts' => 'true',
            'page' => 1,
            'query' => str_replace("-", "", $isbn),
        ];

        $dataArray = $this->getRequestDataResponseAsArray($url, $params);

        $link = null;

        if (isset($dataArray['status']) && $dataArray['status'] === 200) {
            $productUrl = $dataArray['products'][0]['normalizedName'] ?? null;
            if ($productUrl !== null) {
                $link = 'https://www.zbozi.cz/vyrobek/' . urlencode($productUrl)
                    . '/';
            }
        }
        return $link;
    }
}