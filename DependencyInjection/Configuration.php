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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('subugoe_counter');
        $rootNode
            ->children()
                ->integerNode('piwik_idsite')->end()
                ->scalarNode('piwik_token_auth')->end()
                ->scalarNode('reports_dir')->end()
                ->scalarNode('admin_nlh_email')->end()
                ->scalarNode('nlh_platform')->end()
                ->scalarNode('report_subject')->end()
                ->scalarNode('report_body')->end()
                ->scalarNode('reporting_start_subject')->end()
                ->scalarNode('reporting_start_body')->end()
                ->scalarNode('reporting_end_subject')->end()
                ->scalarNode('reporting_end_body')->end()
                ->scalarNode('number_of_reports_sent')->end()
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
