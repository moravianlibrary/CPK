<?php
namespace MZKCatalog\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmzk' => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                ) /* factories */
            ), /* recorddriver */
        ), /* plugin_managers */
    ), /* vufind */
);

return $config;