<?php

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