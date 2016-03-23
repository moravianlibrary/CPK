<?php
/**
 * Logos view helper
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

/**
 * Logos view helper
 *
 * @category VuFind2
 * @package View_Helpers
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Logos extends \Zend\View\Helper\AbstractHelper
{

    /**
     * DB institutions table
     *
     * @var \CPK\Db\Table\Institutions
     */
    protected $institutionsTable;

    /**
     * Associative array holding the logos
     *
     * @var object
     */
    protected $idpLogos;

    /**
     * Constructor
     *
     * @param
     *            \Zend\Config\Config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $idps = $config->toArray();
        
        foreach ($idps as $source => $idp) {
            
            if (isset($idp['logo']))
                $this->idpLogos[$source] = $idp['logo'];
        }
    }

    /**
     * Returns URL of the institution's logo specified by the source.
     *
     * @param string $source            
     */
    public function getLogo($source)
    {
        if (($pos = strpos($source, '.')) !== false) {
            $source = substr($source, 0, $pos);
        }
        
        if (isset($this->idpLogos[$source])) {
            return $this->idpLogos[$source];
        }
        
        return '';
    }
}
