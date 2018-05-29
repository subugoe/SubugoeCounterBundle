<?php

namespace Subugoe\CounterBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class SubugoeCounterExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('reports_dir', $config['reports_dir']);
        $container->setParameter('document_fields', $config['document_fields']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $reportServiceDefintion = $container->getDefinition('subugoe_counter.report_service');
        $reportServiceDefintion->addMethodCall('setConfig', [
                $config['piwik_idsite'],
                $config['piwik_token_auth'],
                $config['platform'],
                $config['counter_collections'],
        ]
        );

        $reportServiceDefintion = $container->getDefinition('subugoe_counter.mail_service');
        $reportServiceDefintion->addMethodCall('setConfig', [
                $config['admin_email'],
                $config['report_subject'],
                $config['report_body'],
                $config['reporting_start_subject'],
                $config['reporting_start_body'],
                $config['reporting_end_subject'],
                $config['reporting_end_body'],
                $config['number_of_reports_sent'],
                $config['cumulative_report_subject'],
                $config['cumulative_report_body'],
        ]
        );

        $trackingListenerDefintion = $container->getDefinition('subugoe_counter.tracking_listener');
        $trackingListenerDefintion->addMethodCall('setConfig', [
                $config['piwik_idsite'],
                $config['piwik_token_auth'],
                $config['document_fields'],
                $config['piwiktracker_baseurl'],
                $config['doc_type_monograph'],
                $config['doc_type_periodical'],
                $config['exclude_ips'],
        ]
        );
    }

    public function getAlias()
    {
        return 'subugoe_counter';
    }
}
