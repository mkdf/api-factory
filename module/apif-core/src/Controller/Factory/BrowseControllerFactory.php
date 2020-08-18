<?php


namespace APIF\Core\Controller\Factory;


use APIF\Core\Controller\BrowseController;
use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BrowseControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(APIFCoreRepositoryInterface::class);
        return new BrowseController($repository, $config);
    }
}