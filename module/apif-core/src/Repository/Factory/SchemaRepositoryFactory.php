<?php


namespace APIF\Core\Repository\Factory;

use APIF\Core\Repository\SchemaRepository;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SchemaRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new SchemaRepository($config);
    }
}