<?php
/**
 * PureClarity Modes (dev / production)
 *
 * @title       Pureclarity_Core_Model_System_Config_Source_Mode
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
class Pureclarity_Core_Model_System_Config_Source_Mode
{

    public function toOptionArray()
    {
        $helper = Mage::helper('pureclarity_core');
        return array(
            array(
                'label' => $helper->__('Development/Test/UAT'),
                'value' => 0
            ),
            array(
                'label' => $helper->__('Production'),
                'value' => 1
            )
        );
    }

}