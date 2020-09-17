<?php


namespace APIF\Core\Controller\Factory;


use APIF\Core\Controller\BrowseController;
use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BrowseControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(APIFCoreRepositoryInterface::class);
        $readLogger = $container->get('apifReadLogger');
        $activityLog = $container->get(ActivityLogManagerInterface::class);
        return new BrowseController($repository, $activityLog, $config, $readLogger);
    }
}