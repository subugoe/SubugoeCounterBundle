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

        $container->setParameter('piwik_idsite', $config['piwik_idsite']);
        $container->setParameter('piwik_token_auth', $config['piwik_token_auth']);
        $container->setParameter('reports_dir', $config['reports_dir']);
        $container->setParameter('admin_nlh_email', $config['admin_nlh_email']);
        $container->setParameter('nlh_platform', $config['nlh_platform']);
        $container->setParameter('report_subject', $config['report_subject']);
        $container->setParameter('report_body', $config['report_body']);
        $container->setParameter('reporting_start_subject', $config['reporting_start_subject']);
        $container->setParameter('reporting_start_body', $config['reporting_start_body']);
        $container->setParameter('reporting_end_subject', $config['reporting_end_subject']);
        $container->setParameter('reporting_end_body', $config['reporting_end_body']);
        $container->setParameter('number_of_reports_sent', $config['number_of_reports_sent']);
        $container->setParameter('counter_collections', $config['counter_collections']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    public function getAlias()
    {
        return 'subugoe_counter';
    }
}
