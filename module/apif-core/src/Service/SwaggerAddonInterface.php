<?php


namespace APIF\Core\Service;


interface SwaggerAddonInterface
{
    public function getController();
    public function hasFeature();
    public function getLabel();
    public function isActive();
    public function setActive($bool);
}