<?php


namespace APIF\Core\Controller\Factory;


use APIF\Core\Controller\SchemaManagementController;
use APIF\Core\Repository\SchemaRepositoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SchemaManagementControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(SchemaRepositoryInterface::class);
        return new SchemaManagementController($repository, $config);
    }

}