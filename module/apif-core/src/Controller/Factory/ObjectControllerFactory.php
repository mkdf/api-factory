<?php

namespace APIF\Core\Controller\Factory;

use APIF\Core\Controller\ObjectController;
use APIF\Core\Repository\APIFCoreRepositoryInterface;
use APIF\Core\Service\ActivityLogManagerInterface;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ObjectControllerFactory implements FactoryInterface
{

    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        $repository = $container->get(APIFCoreRepositoryInterface::class);
        $activityLog = $container->get(ActivityLogManagerInterface::class);
        return new ObjectController($repository, $activityLog, $config);
    }
}