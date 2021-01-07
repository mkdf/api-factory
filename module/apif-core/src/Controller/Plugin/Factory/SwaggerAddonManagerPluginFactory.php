<?php


namespace APIF\Core\Controller\Plugin\Factory;


use APIF\Core\Controller\Plugin\SwaggerAddonManagerPlugin;
use APIF\Core\Service\SwaggerAddonManagerInterface;
use Interop\Container\ContainerInterface;

class SwaggerAddonManagerPluginFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $m = $container->get(SwaggerAddonManagerInterface::class);
        return new SwaggerAddonManagerPlugin($m);
    }
}