<?php

namespace Subugoe\CounterBundle\DependencyInjection;

use Subugoe\CounterBundle\EventListener\PiwikTrackingListener;
use Subugoe\CounterBundle\Service\ReportService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class SubugoeCounterExtension extends Extension
{
    public function getAlias()
    {
        return 'subugoe_counter';
    }

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
    }
}
