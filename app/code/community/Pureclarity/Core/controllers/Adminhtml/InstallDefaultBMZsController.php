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
        // At the moment this just creates one CMS static block. it is called "default_pc_bmz" and has the reference AA-00

        $defaultBlockID = "default_pc_bmz";
        $defaultReferenceID = "AA-00";

        // This controller is called by an AJAX request, the resulting string is shown to the user in a message box.
        if (Mage::getModel('cms/block')->load($defaultBlockID)->getIsActive()){
            // In this case, the block already exists.
            $result = "The default BMZ static block has already been installed. To reinstall, please delete the block with ID '$defaultBlockID' and then click install again. To add more BMZ blocks, make copies of the default block and edit the reference ID. (Default reference ID is '$defaultReferenceID')";
        }
        else {
            try {
                // Try to install the block...
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
                // If something goes wrong, fail nicely
                $result = "Unexpected failure to create default BMZ static block.";
                $result .= $e->getMessage();
            }
        }
        Mage::app()->getResponse()->setBody($result);
    }
}