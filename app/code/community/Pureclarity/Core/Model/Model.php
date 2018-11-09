<?php

/**
* PureClarity Product Export Module
*/
class Pureclarity_Core_Model_Model extends Mage_Core_Model_Abstract
{
    protected $coreHelper;

    const PLACEHOLDER_IMAGE_URL = "images/pureclarity_core/PCPlaceholder250x250.jpg";

    public function __construct()
    {
        $this->coreHelper = Mage::helper('pureclarity_core');
    }

    /**
     * Removes protocol from the start of $url
     * @param $url string
     */
    protected function removeUrlProtocol($url)
    {
        if(!empty($url) && is_string($url)){
            $url = str_replace(
                array(
                    "https:", 
                    "http:"
                ), "", $url
            );
        }

        return $url;
    }
}
