<?php


namespace APIF\Core\Service\Factory;


use APIF\Core\Service\SwaggerAddonManager;
use Interop\Container\ContainerInterface;

class SwaggerAddonManagerFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SwaggerAddonManager();
    }
}