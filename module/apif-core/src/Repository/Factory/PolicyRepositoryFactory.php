<?php


namespace APIF\Core\Repository\Factory;

use APIF\Core\Repository\PolicyRepository;
use Interop\Container\ContainerInterface;

class PolicyRepositoryFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new PolicyRepository($config);
    }
}