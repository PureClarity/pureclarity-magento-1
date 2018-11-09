<?php
/*****************************************************************************************
 * Magento
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *  
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *  
 * @category  PureClarity
 * @package   PureClarity_Core
 * @author    PureClarity Technologies Ltd (www.pureclarity.com)
 * @copyright Copyright (c) 2017 PureClarity Technologies Ltd
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *****************************************************************************************/

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
                'label' => $helper->__('Region 6'),
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