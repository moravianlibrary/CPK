<?php
namespace CPK\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array(
            'recorddriver' => array (
                'factories' => array(
                    'solrdefault' => 'CPK\RecordDriver\Factory::getSolrMarc',
                    'solrcpk_mzk' => 'CPK\RecordDriver\Factory::getSolrMarcMZK',
                    'solrcpk_vkol' => 'CPK\RecordDriver\Factory::getSolrMarcVKOL',
                    'solrcpk_nlk' => 'CPK\RecordDriver\Factory::getSolrMarcNLK',
                ) /* factories */
            ), /* recorddriver */
        ),
    ),
);

return $config;
