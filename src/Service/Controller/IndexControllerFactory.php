<?php
namespace Hierarchy\Service\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Hierarchy\Controller\IndexController;
use Hierarchy\Service\HierarchyUpdater\HierarchyUpdater;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hierarchyUpdater = $container->get(HierarchyUpdater::class);
        return new \Hierarchy\Controller\IndexController($hierarchyUpdater);
    }
}
