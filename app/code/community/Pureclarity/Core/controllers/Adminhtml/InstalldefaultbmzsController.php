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

class Pureclarity_Core_Adminhtml_InstallDefaultBMZsController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Install the default BMZs
     *
     * @return void
     */
    public function installbmzAction()
    {
        // Create the default BMZ static blocks.

        $defaultBlockID = "default_pc_bmz";
        $defaultReferenceID = "AA-00";

        if (Mage::getModel('cms/block')->load($defaultBlockID)->getIsActive()){
            $result = "The default BMZ static block has already been installed. To reinstall, please delete the block with ID '$defaultBlockID' and then click install again. To add more BMZ blocks, make copies of the default block and edit the reference ID. (Default reference ID is '$defaultReferenceID')";
        }
        else {
            try {
                $storeID = Mage::app()->getStore()->getStoreId();
                $content = "{{block type=\"pureclarity_core/bmz\" name=\"bmz-$defaultReferenceID\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"$defaultReferenceID\"}}";
                $block = Mage::getModel('cms/block');
                $block->setTitle('Default Pureclarity BMZ');
                $block->setIdentifier($defaultBlockID);
                $block->setStores(array($storeID));
                $block->setIsActive(1);
                $block->setContent($content);
                $block->save();
                $result = "Default BMZ static blocks created successfully.";
            }
            catch (Exception $e){
                $result = "Unexpected failure to create default BMZ static block.";
                $result .= $e->getMessage();
            }
        }
        Mage::app()->getResponse()->setBody($result);
    }
}