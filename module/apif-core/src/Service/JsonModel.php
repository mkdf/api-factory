<?php


namespace APIF\Core\Service;


use Laminas\Json\Json;
use Laminas\Stdlib\ArrayUtils;

class JsonModel extends \Laminas\View\Model\JsonModel
{
    /**
     * Serialize to JSON
     *
     * @return string
     */
    public function serialize()
    {
        $variables = $this->getVariables();
        if ($variables instanceof Traversable) {
            $variables = ArrayUtils::iteratorToArray($variables);
        }

        $options = [
            'prettyPrint' => $this->getOption('prettyPrint'),
        ];

        if (null !== $this->jsonpCallback) {
            return $this->jsonpCallback . '(' . Json::encode($variables, false, $options) . ');';
        }
        //return Json::encode($variables, false, $options);
        return json_encode($variables, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }

}