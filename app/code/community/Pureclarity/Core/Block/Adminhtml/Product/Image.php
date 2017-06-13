<?php

class Pureclarity_Core_Block_Adminhtml_Product_Image extends Varien_Data_Form_Element_Image {
    protected function _getUrl()
    {
        $url = false;
        if ($this->getValue()) {
            $url = Mage::helper('pureclarity_core')->getPlaceholderUrl() . $this->getValue();
        }
        return $url;
    }
}