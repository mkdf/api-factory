<?php


namespace APIF\Core\Repository\Factory;

use APIF\Core\Repository\FileRepository;
use Interop\Container\ContainerInterface;

class FileRepositoryFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("Config");
        return new FileRepository($config);
    }
}