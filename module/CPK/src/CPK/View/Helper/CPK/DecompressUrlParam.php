<?php
namespace CPK\View\Helper\CPK;
/**
 * DecompressUrlParam view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Martin Kravec <kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Decompress extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Decompress URL param
     *
     * @param string $string     String to compress
     *
     * @return string
     */
    public function __invoke(string $string)
    {
        return \LZCompressor\LZString::decompressFromBase64(specialUrlDecode($string));
    }
}