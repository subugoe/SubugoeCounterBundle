<?php

namespace Subugoe\CounterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('subugoe_counter');
        $treeBuilder->getRootNode()
              ->children()
                  ->integerNode('piwik_idsite')->end()
                  ->scalarNode('piwik_token_auth')->end()
                  ->scalarNode('piwiktracker_baseurl')->end()
                  ->scalarNode('piwikreporter_baseurl')->end()
                  ->scalarNode('reports_dir')->end()
                  ->scalarNode('admin_email')->end()
                  ->scalarNode('platform')->end()
                  ->scalarNode('report_subject')->end()
                  ->scalarNode('report_body')->end()
                  ->scalarNode('reporting_start_subject')->end()
                  ->scalarNode('reporting_start_body')->end()
                  ->scalarNode('reporting_end_subject')->end()
                  ->scalarNode('reporting_end_body')->end()
                  ->scalarNode('number_of_reports_sent')->end()
                  ->scalarNode('cumulative_report_subject')->end()
                  ->scalarNode('cumulative_report_body')->end()
                  ->scalarNode('doc_type_monograph')->end()
                  ->scalarNode('doc_type_periodical')->end()
                  ->arrayNode('document_fields')
                      ->prototype('scalar')->end()
                  ->end()
                  ->arrayNode('exclude_ips')
                      ->prototype('scalar')->end()
                  ->end()
                  ->arrayNode('counter_collections')
                      ->prototype('array')
                          ->children()
                              ->scalarNode('id')->end()
                              ->scalarNode('full_title')->end()
                              ->scalarNode('publisher')->end()
                      ->end()
                  ->end()
              ->end();

        return $treeBuilder;
    }
}
