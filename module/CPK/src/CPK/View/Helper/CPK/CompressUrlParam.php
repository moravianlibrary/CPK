<?php
namespace CPK\View\Helper\CPK;
/**
 * CompressUrlParam view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Martin Kravec <kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class CompressUrlParam extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Compress URL param
     *
     * @param string $string     String to compress
     *
     * @return string
     */
    public function __invoke(string $string)
    {
        return specialUrlEncode(\LZCompressor\LZString::compressToBase64($string));
    }
}