<?php
/**
 * Record driver view helper
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
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace CPK\View\Helper\CPK;
use MZKCommon\View\Helper\MZKCommon\Record as ParentRecord;
use CPK\Db\Row\User;

/**
 * Record driver view helper
 *
 * @author	Martin Kravec	<kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */

class Record extends ParentRecord
{
    /**
     * Display values of 7xx fields
     *
     * @param   boolean $showDescription
     *
     * @return string
     */
    public function displayFieldsOf7xx($showDescription)
    {
        return $this->contextHelper->renderInContext(
            'RecordDriver/SolrDefault/fieldsOf7xx.phtml', array('showDescription' => $showDescription)
        );
    }

    /**
     * Display field 773
     *
     * @return string
     */
    public function displayField773()
    {
        return $this->contextHelper->renderInContext(
            'RecordDriver/SolrDefault/field773.phtml',
            []
        );
    }

    /**
     * Get "recordId" = authorityId by field 'id_authority'
     *
     * @param string $authorityId
     *
     * @return string
     */
    public function getRecordIdByAuthority($authorityId)
    {
        $mainParams = [];
        $mainParams['limit'] = '100';
        $mainParams['join'] = 'AND';
        $mainParams['bool0'] = [];
        $mainParams['bool0'][] = 'AND';
        $mainParams['type0'] = [];
        $mainParams['type0'][] = 'AllFields';
        $mainParams['lookfor0'] = [];
        $mainParams['lookfor0'][] = '';
        $mainParams['filter'] = [];
        $mainParams['filter'][] = 'id_authority:'.$authorityId;

        $request = $mainParams;

        $sm = $this->getView()->getHelperPluginManager()->getServiceLocator();

        $runner = $sm->get('VuFind\SearchRunner');

        $records = $runner->run(
            $request, 'Solr', null
        );

        $results = $records->getResults();

        if(! isset($results[0])) {
            return false;
        }

        $authority = $results[0];

        return $authority->getUniqueId();
    }

    /**
     * This functions gets recordsId, finds parent and all the local IDs
     * and finds the one, that fits users preferences (favorite library)
     *
     * @param string            $recordId
     *
     * @return string
     */
    public function getRelevantRecord($recordId)
    {
        $authManager = $this->view->auth()->getManager();
        $user = $authManager->isLoggedIn();

        if ($user instanceof \CPK\Db\Row\User) {

            $sm = $this->getView()->getHelperPluginManager()->getServiceLocator();
            $ajaxController = $sm->get('ajaxCtrl');
            $availablesRecords = $ajaxController->getRecordSiblings($recordId);

            $myLibraries = $user->getNonDummyInstitutions();
            sort($myLibraries);

            foreach ($availablesRecords as $record) {
                foreach ($myLibraries as $myLibrary) {
                    if (explode(".", $record, 2)[0] == $myLibrary) {
                        return $record;
                    }
                }
            }
        }
        return $recordId;
    }
}
