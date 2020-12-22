<?php


namespace APIF\Core\Service;


interface SwaggerAddonManagerInterface
{
    public function registerAddon(SwaggerAddonInterface $addon);
    public function getAddons();
}