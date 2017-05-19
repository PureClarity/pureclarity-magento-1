<?php

class Pureclarity_Core_Model_System_Config_Source_Region
{

    public function toOptionArray()
    {
        $helper = Mage::helper('pureclarity_core');
        return array(
            array(
                'label' => $helper->__('Region 1'),
                'value' => 1
            ),
            array(
                'label' => $helper->__('Region 2'),
                'value' => 2
            ),
            array(
                'label' => $helper->__('Region 3'),
                'value' => 3
            ),
            array(
                'label' => $helper->__('Region 4'),
                'value' => 4
            ),
            array(
                'label' => $helper->__('Region 5'),
                'value' => 5
            ),
            array(
                'label' => $helper->__('Region65'),
                'value' => 6
            ),
            array(
                'label' => $helper->__('Region 7'),
                'value' => 7
            ),
            array(
                'label' => $helper->__('Region 8'),
                'value' => 8
            ),
            array(
                'label' => $helper->__('Region 9'),
                'value' => 9
            ),
            array(
                'label' => $helper->__('Region 10'),
                'value' => 10
            )
        );
    }

}