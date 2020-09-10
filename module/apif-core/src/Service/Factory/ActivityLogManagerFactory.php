<?php


namespace APIF\Core\Service\Factory;


use APIF\Core\Service\ActivityLogManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ActivityLogManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new ActivityLogManager($config);
    }
}