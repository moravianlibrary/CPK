<?php
namespace HF\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
             'recorddriver' => array (
                 'factories' => array(
                     'solrhist_mzk'	=> 'HF\RecordDriver\Factory::getSolrMarc', 
                     'solrhist_mzk_rajhrad'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mzk_dacice'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mzk_trebova'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mzk_znojmo'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_vkol'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_cbvk'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_kkfb'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_parl'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mend'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mas'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_mkp'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_nm'	=> 'HF\RecordDriver\Factory::getSolrMarcNM',
                     'solrhist_muni'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrhist_knav'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                     'solrdefault'	=> 'HF\RecordDriver\Factory::getSolrMarc',
                      ), /* factories */
                 ), /* recorddriver */
             ), /* plugin_managers */
       'recorddriver_tabs' => array(
             'HF\RecordDriver\SolrMarc' => array(
                    'tabs' => array (
                         'staffviewmarc' => 'VuFind\RecordTab\StaffViewMARC',
                         'TOC' => 'TOC',   
                 ),
             ),
        ),
 
    ),
);

return $config;
