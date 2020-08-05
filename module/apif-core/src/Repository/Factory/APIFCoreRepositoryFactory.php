<?php

namespace APIF\Core\Repository\Factory;

use APIF\Core\Repository\APIFCoreRepository;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class APIFCoreRepositoryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new APIFCoreRepository($config);
    }
}