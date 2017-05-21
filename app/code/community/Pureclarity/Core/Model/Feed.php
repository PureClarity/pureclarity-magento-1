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

class Pureclarity_Core_Model_Feed extends Mage_Core_Model_Abstract
{

    function getFullCatFeed($progressFileName, $storeId) {

        $feedCategories = array("Categories" => array());
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToSelect('*')
            ->addFieldToFilter('is_active',array("eq"=> true))
            ->addUrlRewriteToResult();
        

        $maxProgress = count($categoryCollection);
        $currentProgress = 0;
        foreach ($categoryCollection as $category) {

            // Check if to ignore this category
            if ($category->getData('pureclarity_hide_from_feed') == '1')
                continue;
            
            // Get image
            $firstImage = $category->getImageUrl();
            if($firstImage != "") {
                $imageURL = $firstImage;
            } else {
                $imageURL = Mage::helper('pureclarity_core')->getCategoryPlaceholderUrl($storeId);
                if (!$imageURL) {
                    $imageURL = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                    if (!$imageURL) {
                        $imageURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                    }
                }
            }
            $imageURL = str_replace(array("https:", "http:"), "", $imageURL);
            
            // Get Second Image
            $imageURL2 = null;
            $secondImage = $category->getData('pureclarity_secondary_image');
            if ($secondImage != "") {
                $imageURL2 = sprintf("%scatalog/category/%s", Mage::getBaseUrl('media'), $secondImage);
            } else {
                $imageURL2 = Mage::helper('pureclarity_core')->getSecondaryCategoryPlaceholderUrl($storeId);
                if (!$imageURL2) {
                    $imageURL2 = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                }
            }
            $imageURL2 = str_replace(array("https:", "http:"), "", $imageURL2);
            
            // Build Data
            $categoryData = array(
                "Id" => $category->getId(),
                "DisplayName" => $category->getName(),
                "ParentIds" => array($category->getParentCategory()->getId()),
                "Image" => $imageURL,
                "Link" => sprintf("/%s", str_replace(Mage::getUrl('',array('_secure'=>true)), '', $category->getUrl($category)))
            );

            if ($imageURL2 != null){
                $categoryData["Image2"] = $imageURL2;
            }

            $feedCategories['Categories'][] = $categoryData;
            $currentProgress += 1;
            $progressFile = fopen($progressFileName, "w");
            fwrite($progressFile, "{\"name\":\"category\",\"cur\":$currentProgress,\"max\":$maxProgress,\"isComplete\":false}");
            fclose($progressFile);
        }
        return $feedCategories;
    }




    function getFullBrandFeed($progressFileName){
        $brandCode = Mage::helper('pureclarity_core')->getBrandAttributeCode();
        try {
            $attribute = Mage::getSingleton('eav/config')
                ->getAttribute(Mage_Catalog_Model_Product::ENTITY, $brandCode);
        }
        catch (\Exception $e){
            Mage::log("Unable to get brand attribute for brand feed: '$brandCode'. Does this attribute exist?", Zend_Log::ERR);
            return;
        }

        $feedBrands = array("Brands" => array());
        $storeId = Mage::app()->getStore()->getId();
        $options = $attribute->setStoreId($storeId)->getSource()->getAllOptions(false);
        // Find the SOLWIN image helper, if installed.
        $solwinImageHelper = null;

        $modules = Mage::getConfig()->getNode('modules')->children();
        $modulesArray = (array)$modules;

        if(isset($modulesArray['Mage_Attributeimage_Helper_Data'])) {
            $solwinImageHelper = Mage::helper('attributeimage');
        } 

        $maxProgress = count($options);
        $currentProgress = 0;
        foreach($options as $opt){
            $imageURL = null;
            if ($solwinImageHelper != null) {
                $id = $opt['value'];
                // TODO - this image selection procuedure is also similar to the category code. Deduplicate.
                $imageURL = $solwinImageHelper->getAttributeImage($id);
                if (!$imageURL){
                    $imageURL = Mage::helper('pureclarity_core')->getBrandPlaceholderUrl();
                    if (!$imageURL) {
                        $imageURL = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                        if (!$imageURL) {
                            $imageURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                        }
                    }
                }
            }                                        

            $thisBrand = array(
                "MagentoID" => sprintf("%s", $opt['value']),
                "Brand" => sprintf("%s", $opt['label']),
                //"Description" => sprintf("%s", $catDescription) // TODO Get the description for this option
            );
            if ($imageURL != null){
                $thisBrand['Image'] = $imageURL;
            }

            $feedBrands['Brands'][] = $thisBrand;
                              

            $currentProgress += 1;
            $progressFile = fopen($progressFileName, "w");
            fwrite($progressFile, "{\"name\":\"brand\",\"cur\":$currentProgress,\"max\":$maxProgress,\"isComplete\":false}");
            fclose($progressFile);
        }
        return $feedBrands;
    }



}
