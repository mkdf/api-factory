<?php


namespace APIF\Core\Service;


class SwaggerAddonManager implements SwaggerAddonManagerInterface
{
    private $_addons = [];
    private $_active = NULL;

    public function registerAddon(SwaggerAddonInterface $addon){
        if(!in_array($addon, $this->_addons)){
            $this->_addons[] = $addon;
        }
    }

    public function getAddons(){
        $addons = [];
        foreach($this->_addons as $addon){
            if($addon->hasFeature()){
                array_push($addons, $addon);
            }
        }
        return $addons;
    }

    public function setActive(MvcEvent $event){

    }
}