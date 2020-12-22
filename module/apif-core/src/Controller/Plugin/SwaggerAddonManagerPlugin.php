<?php


namespace APIF\Core\Controller\Plugin;


use APIF\Core\Service\SwaggerAddonManagerInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class SwaggerAddonManagerPlugin extends AbstractPlugin
{
    private $_manager;

    public function __construct(SwaggerAddonManagerInterface  $manager)
    {
        $this->_manager = $manager;
    }

    public function __invoke()
    {
        return $this->_manager;
    }
}