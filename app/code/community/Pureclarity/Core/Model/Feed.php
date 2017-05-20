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

    // TODO - Review - Should this be a config item?
    const STORE_ID = 1;

    protected $notificationUrlTemplate = '{api-access-url}/appid={access_key}&url={website_root_url}%2Fpureclarity-product-feed%2F&feedtype=pureclarity_json';
    protected $notificationUrl = null;

    /**
     * set up notification URL
     */
    public function _construct()
    {
        $this->notificationUrl = str_replace(
            '{api-access-url}',
            Mage::helper('pureclarity_core')->getApiAccessUrl(),
            $this->notificationUrlTemplate
        );
    }

    private function sanitizeCategoryName($name){
        // TODO This is a temporary workaround
        // Category names cannot have pipe characters in them at the moment
        // Work ongoing to fix this.
        return str_replace("|", "/", $name);
    }



    /**
     *
     * getFullCatFeed
     *
     * Load in ALL active categories
     *
     * @return array
     */
    function getFullCatFeed($progressFileName) {

        $feedCategories = array("Categories" => array());
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('image')
            ->addAttributeToSelect('description')
            ->addAttributeToSelect('custom_category_image')
            ->addAttributeToSelect('category_short_description')
            ->addAttributeToSelect('pureclarity_secondary_image')
            ->addAttributeToSelect('pureclarity_hide_from_feed')
            ->addAttributeToFilter('entity_id', array('nin' => array(Mage::app()->getStore(self::STORE_ID)->getRootCategoryId(), self::STORE_ID)))
            ->addFieldToFilter('is_active',array("in"=>array('1')))
            ->addUrlRewriteToResult();

        $maxProgress = count($categoryCollection);
        $currentProgress = 0;
        /** @var Mage_Catalog_Model_Category $categoryItem */
        foreach ($categoryCollection as $categoryItem) {
            $hideFromfeed = $categoryItem->getData('pureclarity_hide_from_feed');
            if ($hideFromfeed == '1') {
                continue;
            }

            $categoryList = array();
            $category = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('entity_id', array('eq' => $categoryItem->getId()))->getFirstItem();

            $parentTree = array();
            $categoryString = "";

            foreach ($category->getParentCategories() as $parent) {
                if($parent->getId() != $category->getId() && $parent->getId() != Mage::app()->getStore(self::STORE_ID)->getRootCategoryId()) {
                    $parentTree[] = $parent->getName();
                }
            }

            if(!empty($parentTree)) {
                $categoryString = implode(' > ', $parentTree) . ' > ' . $category->getName();
            } else {
                $categoryString = $category->getName();
            }

            $categoryList[] = $category->getName();

            // Finding the right image is tricky.
            // Images are tried in this order:
            //  1. the category's image
            //  2. the category placeholder image as defined in the config.
            //  3. the default peculiarity placeholder image
            //  4. the default magento placeholder image
            $firstImage = $categoryItem->getImageUrl();
            if($firstImage != "") {
                $imageURL = $firstImage;
            } else {
                $imageURL = Mage::helper('pureclarity_core')->getCategoryPlaceholderUrl();
                if (!$imageURL) {
                    $imageURL = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                    if (!$imageURL) {
                        $imageURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                    }
                }
            }
            $imageURL = str_replace(array("https:", "http:"), "", $imageURL);

            // This image selection process is mostly the same as the above
            // TODO - deduplicate this code.
            $imageURL2 = null;
            $secondImage = $categoryItem->getData('pureclarity_secondary_image');
            if ($secondImage != "") {
                $imageURL2 = sprintf("%scatalog/category/%s", Mage::getBaseUrl('media'), $secondImage);
            } else {
                $imageURL2 = Mage::helper('pureclarity_core')->getSecondaryCategoryPlaceholderUrl();
                if (!$imageURL2) {
                    $imageURL2 = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                    if (!$imageURL2) {
                        $imageURL2 = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                    }
                }
            }
            $imageURL2 = str_replace(array("https:", "http:"), "", $imageURL2);

            $catDescription = strip_tags($categoryItem->getData('description'));

            $categoryData = array(

                "Category" => sprintf("%s", $categoryString),
                "Image" => $imageURL,
                "Link" => sprintf("%s", str_replace(Mage::getUrl('',array('_secure'=>true)), '', $categoryItem->getUrl($categoryItem))),
                "Description" => sprintf("%s", $catDescription)

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
