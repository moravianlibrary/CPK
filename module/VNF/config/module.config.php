<?php
namespace VNF\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'recorddriver' => array (
                'factories' => array(
                    'solrmkp'     => 'VNF\RecordDriver\Factory::getSolrMkp',
                    'solrmerged'  => 'VNF\RecordDriver\Factory::getSolrMarcMerged',
                    'solrdefault' => 'VNF\RecordDriver\Factory::getSolrMarc',
                ) /* factories */
            ) /* recorddriver */
        ) /* plugin_managers */
    ) /* vufind */
);

return $config;
