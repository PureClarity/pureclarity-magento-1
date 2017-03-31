<?php
/**
 * PureClarity Feed tasks
 *
 * @title       Pureclarity_Core_Model_Feed
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
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
     * Generate all the data for all visible and enabled products needed for PureClarity
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getFullProductFeed($progressFileName)
    {

/*
Notes for the future:

 if( $_products->isGrouped() ) {
    $associatedProducts = $this->getProduct()->getTypeInstance(true)->getAssociatedProducts($this->getProduct());
}

if (p->isConfigurable()){
$childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$product);
}
*/
          
        $feedProducts = array();

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addUrlRewrite()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter("status", array("eq" => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))
            ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);

        $collection->setPageSize(10); // Low page size for better resolution on progress bar.
        $pages = $collection->getLastPageNumber();

        // For testing product feeds quickly, uncomment the following
        //$pages = min($pages, 1); // Limit to a single page of results

        $brandCode = '';
        $brandLookup = [];

        // If brand feed is enabled, get the brands
        if(Mage::helper('pureclarity_core')->isBrandFeedEnabled()) {
                      
            $brandCode = Mage::helper('pureclarity_core')->getBrandAttributeCode();
                      
            // Send progress updates to /dev/null, as this is just part of the product feed.
            // This is to avoid conflicting if both brand and product feeds are run simultaneously
            $nullFile = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'? "nul" : "/dev/null";
                      
            $brands = $this->getFullBrandFeed($nullFile)["Brands"];
                      
            foreach ($brands as $brand){
                $brandLookup[$brand["MagentoID"]] = $brand["Brand"];
            }
        }

        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->getItems();

        $attributesToInclude = [];
        $attributesToExclude = array("prices", "price");
        // Brand code is included separately
        if(Mage::helper('pureclarity_core')->isBrandFeedEnabled()) {
            $attributesToExclude[] = strtolower($brandCode);
        }

        foreach ($attributes as $attribute){
            $code = $attribute->getAttributecode();
            if ($attribute->getIsFilterable()!=0 && !in_array(strtolower($code), $attributesToExclude)) {
                $attributesToInclude[] = array($code, $attribute->getFrontendLabel());
            }
        }


        $currentPage = 1;
        $batchNumber = 0;
        $seenProductIds = array();
        do {
            $collection->setCurPage($currentPage);
            $collection->load();

            foreach($collection as $product) {

                if(!in_array($product->getId(), $seenProductIds)) {

                    $searchTags = '';

                    // get categories for product
                    $feedCategories = $categoryList = array();
                    $categories = $product->getCategoryIds();
                    $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                        ->addAttributeToSelect('name')
                        ->addAttributeToFilter('entity_id', array('in' => $categories))
                        ->addFieldToFilter('is_active', array("in" => array('1')));

                    foreach ($categoryCollection as $category) {

                        $parentTree = array();

                        foreach ($category->getParentCategories() as $parent) {
                            if ($parent->getId() != $category->getId() && $parent->getId() != Mage::app()->getStore(self::STORE_ID)->getRootCategoryId()) {
                                $parentTree[] = $this->sanitizeCategoryName($parent->getName());
                            }
                        }

                        if (!empty($parentTree)) {
                            $feedCategories[] = implode(' > ', $parentTree) . ' > ' . $this->sanitizeCategoryName($category->getName());
                        } else {
                            $feedCategories[] = $this->sanitizeCategoryName($category->getName());
                        }

                        $categoryList[] = $this->sanitizeCategoryName($category->getName());

                    }


                    /*
                    // TODO array_unique seems to cause trouble here.
                    $categoryList = array_unique($categoryList, SORT_STRING);
                    */
                    $productUrl = str_replace(Mage::getBaseUrl(), '', $product->getUrlPath());
                    $productUrl = str_replace(Mage::getUrl('', array('_secure' => true)), '', $productUrl);
                    if (substr($productUrl, 0, 1) != '/') {
                        $productUrl = '/' . $productUrl;
                    }

                    if($product->getImage() && $product->getImage() != 'no_selection'){
                        $productImageUrl = Mage::helper('catalog/image')->init($product, 'image')->resize(250)->__toString();
                    }
                    else{
                        $productImageUrl = Mage::helper('pureclarity_core')->getProductPlaceholderUrl();
                        if (!$productImageUrl) {
                            $productImageUrl = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                            if (!$productImageUrl) {
                                $productImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                            }
                        }
                    }

                    /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                    $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                    if ($stockItem->getIsInStock() == 1) {
                        $inStock = true;
                    } else {
                        $inStock = false;
                    }

                    $searchTags = array($product->getShortSku());

                    $productBrand = null;

                    if (Mage::helper('pureclarity_core')->isBrandFeedEnabled()) {
                        $brandID = $product->getData($brandCode);
                        $productBrand = $brandLookup[$brandID];
                    }

                    /** @var Mage_Catalog_Model_Product $product */
                    $data = array(
                        "Sku" => $product->getData('sku'),
                        "Title" => $product->getData('name'),
                        "Description" => strip_tags($product->getData('description')),
                        "Link" => $productUrl,
                        "Image" => $productImageUrl,
                        "ImageOverlay" => '',
                        "Categories" => $feedCategories,
                        "MagentoCategories" => $categoryList,
                        "Prices" => array($product->getPrice()),
                        //"OnOffer"               => false, // TODO
                        //"NewArrival"            => false, // TODO
                        "MagentoProductId" => $product->getId(),
                        //"MagentoPromoText"      => $product->getPromoText(), // RBL
                        "MagentoProductType" => $product->getTypeId(),
                        "MagentoStock" => $inStock,
                        "SearchTags" => $searchTags
                    );


                    /* TODO - look at ps-dev
                                        if ($product->getData('special_price') != null) {
                                            $data["SalePrices"] = array($product->getData('special_price'));
                                        }
                    */
          

                    foreach ($attributesToInclude as $attribute) {
                        $code = $attribute[0];
                        $name = $attribute[1];
                        if ($product->getData($code) != null) {
                            $attrValues = $product->getAttributeText($code);
                            if (!is_array($attrValues)){
                                $attrValues = array($attrValues);
                            }
                            $data[$name] = $attrValues;
                        }
                    }
                    if ($productBrand != null) {
                        $data["Brand"] = $productBrand;
                    }

/* Probably RBL Specific
                    $sizes = array();

                    if ($product->getTypeId() == 'configurable') {
                        $childProducts = $product->getTypeInstance()->getUsedProducts();
                        foreach($childProducts as $childProduct) {
                            $sizes[] = $childProduct->getAttributeText('size');
                        }
                    }

                    if (count($sizes) > 0) {
                        $data["Size"] = $sizes;
                    }
*/
                    $feedProducts[] = $data;

                    $seenProductIds[] = $product->getId();

                }

            }

            $collection->clear();
            $currentPage++;

            $progressFile = fopen($progressFileName, "w");
            fwrite($progressFile, "{\"name\":\"product\",\"cur\":$currentPage,\"max\":$pages,\"isComplete\":false}");
            fclose($progressFile);
        } while ($currentPage <= $pages);

          
        return array(
            "Products" => $feedProducts
        );

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
