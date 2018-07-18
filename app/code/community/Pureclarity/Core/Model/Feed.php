<?php

class Pureclarity_Core_Model_Feed extends Mage_Core_Model_Abstract
{
    // Process the product feed and update the progress file, in page sizes of 20 (or other if overriden)
    function processProductFeed($productExportModel, $progressFileName, $feedFile, $pageSize = 20)
    {
        $currentPage = 0;
        $pages = 0;
        $feedProducts = array();
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', 0, 1);

        fwrite($feedFile, '"Products":[');
        $firstProduct = true;
        do {
            $result = $productExportModel->getFullProductFeed($pageSize, $currentPage);
            $pages = $result["Pages"];

            foreach ($result["Products"] as $product) {
                $json = Mage::helper('pureclarity_core')->formatFeed($product, 'json');

                if (!$firstProduct) {
                    fwrite($feedFile, ',');
                }
                fwrite($feedFile, $json);
                $firstProduct = false;
            }

            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', $currentPage, $pages, "false");
            $currentPage++;
        } while ($currentPage <= $pages);

        fwrite($feedFile, ']');

        // fwrite($feedFile, '],"Pages":');
        // fwrite($feedFile, $pages);
        // fwrite($feedFile, '}');

        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', $currentPage, $pages, "true");
    }

    function getFullCatFeed($progressFileName, $storeId) {        
        $feedCategories = '"Categories":[';
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->addUrlRewriteToResult();
        

        $maxProgress = count($categoryCollection);
        $currentProgress = 0;
        $isFirst = true;
        foreach ($categoryCollection as $category) {
            
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
                "Image" => $imageURL,
                "Link" => sprintf("/%s", str_replace(Mage::getUrl('',array('_secure'=>true)), '', $category->getUrl($category)))
            );

            
            // Check if to ignore this category in recommenders
            if ($category->getData('pureclarity_hide_from_feed') == '1'){
                 $categoryData["ExcludeFromRecommenders"] = true;
            }

            //Check if category is active
            if (!$category->getIsActive()){
                 $categoryData["IsActive"] = false;
            }

            if ($category->getLevel() > 1){
                $categoryData["ParentIds"] = array($category->getParentCategory()->getId());
            }

            if ($imageURL2 != null){
                $categoryData["PCImage"] = $imageURL2;
            }

            if (!$isFirst)
                $feedCategories .= ',';
            
            $isFirst = false;
            $feedCategories .= Mage::helper('pureclarity_core')->formatFeed($categoryData, 'json');
            
            $currentProgress += 1;
            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'category', $currentProgress, $maxProgress, "false");
        }

        $feedCategories .= ']';
        return $feedCategories;
    }




    function getFullBrandFeed($progressFileName, $storeId){
        $feedBrands = '"Brands":[';        
        $brandCategoryId = Mage::helper('pureclarity_core')->getBrandParentCategory($storeId);

        if ($brandCategoryId && $brandCategoryId != "-1"){

            $category = Mage::getModel('catalog/category')->load($brandCategoryId);

            $subcategories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('image')
                ->addIdFilter($category->getChildren());

            $maxProgress = count($subcategories);
            $currentProgress = 0;
            $isFirst = true;
            foreach($subcategories as $subcategory) {
                $thisBrand = array(
                    "Id" => $subcategory->getId(),
                    "DisplayName" =>  $subcategory->getName()
                );
                
                $imageURL = $subcategory->getImageUrl();
                if ($imageURL){
                    $imageURL = str_replace(array("https:", "http:"), "", $imageURL);
                    $thisBrand['Image'] = $imageURL;
                }

                if (!$isFirst)
                    $feedBrands .= ',';
                $isFirst = false;
                $feedBrands .= Mage::helper('pureclarity_core')->formatFeed($thisBrand, 'json');
                $currentProgress += 1;
                Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'brand', $currentProgress, $maxProgress, "false");
            }
            $feedBrands .= ']';
            return $feedBrands;
        }
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'brand', 1, 1);
        return "";
        
    }

    function BrandFeedArray($storeId){
        $feedBrands = array();
        $brandCategoryId = Mage::helper('pureclarity_core')->getBrandParentCategory($storeId);

        if ($brandCategoryId && $brandCategoryId != "-1"){
            $category = Mage::getModel('catalog/category')->load($brandCategoryId);
            $subcategories = $category->getChildrenCategories();
            foreach($subcategories as $subcategory) {
                $feedBrands[$subcategory->getId()] = $subcategory->getName();
            }
            return $feedBrands;
        }
        return array();
        
    }
}
