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
                     'solrvnf_sup' => 'VNF\RecordDriver\Factory::getSolrSup',
                      ), /* factories */
                 ), /* recorddriver */
                 'recordtab' => array(
                     'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                         'invokables' => array(
                             'supraphonrecordtab' => 'VNF\RecordTab\SupraphonRecordTab',
                         ),
                 ), /* recordtab */
             ), /* plugin_managers */
        'recorddriver_tabs' => array(
             'VNF\RecordDriver\SolrSup' => array(
                 'tabs' => array (
                     'Description' => 'Description',
                     'SupraphonRecordTab' => 'SupraphonRecordTab',
                     'UserComments' => 'UserComments',
                     'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                 ),
                 'defaultTab' => null,
            ),
        ),
    ),
);

return $config;
