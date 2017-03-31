<?php

class Pureclarity_Core_Block_Adminhtml_System_Config_RunFeedButton extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /*
    * Set template
    */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pureclarity/system/config/run_feed_button.phtml');
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for button
     *
     * @return string
     */
    public function getPopupUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_runfeedbox/runfeed');
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'PC_Admin',
                'label' => $this->helper('adminhtml')->__('Run feed...'),
                'onclick' => 'javascript:pureclarity_magento_run_feed(); return false;'
            ));

        return $button->toHtml();
    }
}