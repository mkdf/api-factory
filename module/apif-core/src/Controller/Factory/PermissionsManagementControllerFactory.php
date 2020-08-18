<?php


namespace APIF\Core\Controller\Factory;

use APIF\Core\Controller\PermissionsManagementController;
use APIF\Core\Repository\APIFCoreRepositoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PermissionsManagementControllerFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(APIFCoreRepositoryInterface::class);
        return new PermissionsManagementController($repository, $config);
    }
}