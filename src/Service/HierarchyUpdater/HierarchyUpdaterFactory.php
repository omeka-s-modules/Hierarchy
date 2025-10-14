<?php
namespace Hierarchy\Service\HierarchyUpdater;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class HierarchyUpdaterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $api = $container->get('Omeka\ApiManager');
        $siteSettings = $container->get('Omeka\Settings');
        return new HierarchyUpdater($api, $siteSettings);
    }
}
