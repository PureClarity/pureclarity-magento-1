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

/**
* PureClarity Product Export Module
*/
class Pureclarity_Core_Model_ProductExport extends Mage_Core_Model_Abstract
{

    public $storeId = null;
    public $baseCurrencyCode = nulls;
    public $currenciesToProcess = array();
    public $attributesToInclude = array();
    public $seenProductIds = array();
    public $currentStore = null;
    
    // Initialise the model ready to call the product data for the give store.
    public function init($storeId)
    {
        // Use this store, if not passed in.
        $this->storeId = $storeId;
        if (is_null($this->storeId)) {
            $this->storeId = Mage::app()->getStore()->getId();
        }
        
        $this->currentStore = Mage::getModel('core/store')->load($this->storeId);

        // Set Currency list
        $currencyModel = Mage::getModel('directory/currency'); 
        $this->baseCurrencyCode = Mage::app()->getBaseCurrencyCode();
        $currencies = $currencyModel->getConfigAllowCurrencies();
        $currencyRates = $currencyModel->getCurrencyRates($this->baseCurrencyCode, array_values($currencies));
        $this->currenciesToProcess[] = $this->baseCurrencyCode;
        foreach($currencies as $currency){
            if ($currency != $this->baseCurrencyCode && $currencyRates[$currency]){
                $this->currenciesToProcess[] = $currency;
            }
        }


        // TODO - SORT BRAND CODE OUT
        $brandCode = '';
        $brandLookup = [];
        // If brand feed is enabled, get the brands
        if(Mage::helper('pureclarity_core')->isBrandFeedEnabled($this->storeId)) {
            $brandCode = Mage::helper('pureclarity_core')->getBrandAttributeCode($this->storeId);
            // Send progress updates to /dev/null, as this is just part of the product feed.
            // This is to avoid conflicting if both brand and product feeds are run simultaneously
            $nullFile = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'? "nul" : "/dev/null";
            $brands = $this->getFullBrandFeed($nullFile)["Brands"];
            foreach ($brands as $brand){
                $brandLookup[$brand["MagentoID"]] = $brand["Brand"];
            }
        }

        // Get Attributes
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        $attributesToExclude = array("prices", "price");
        // Brand code is included separately, so add to exclude attributes list
        if(Mage::helper('pureclarity_core')->isBrandFeedEnabled($this->storeId)) {
            $attributesToExclude[] = strtolower($brandCode);
        }

        // Get list of attributes to include
        foreach ($attributes as $attribute){
            $code = $attribute->getAttributecode();
            if ($attribute->getIsFilterable()!=0 && !in_array(strtolower($code), $attributesToExclude)) {
                $this->attributesToInclude[] = array($code, $attribute->getFrontendLabel());
            }
        }
    }

    // Process the product feed and update the progress file.
    public function processFeed($progressFileName)
    {
        $currentPage = 1;
        $pages = 0;
        $feedProducts = array();
        $this->updateProgressFile($progressFileName, 0, 1, "false");
        do {
            $result = $this->getFullProductFeed(20, $currentPage);
            $pages = $result["Pages"];
            $feedProducts = array_merge($feedProducts,$result["Products"]);
            $this->updateProgressFile($progressFileName, $currentPage, $pages, "false");
            $currentPage++;
        } while ($currentPage <= $pages);
        $this->updateProgressFile($progressFileName, $currentPage, $pages, "true");
        return  array(
            "Products" => $feedProducts,
            "Pages" => $pages
        );
    }

    // Helper function to update the progress file
    protected function updateProgressFile($progressFileName, $currentPage, $pages, $isComplete)
    {
        $progressFile = fopen($progressFileName, "w");
        fwrite($progressFile, "{\"name\":\"product\",\"cur\":$currentPage,\"max\":$pages,\"isComplete\":$isComplete}" );
        fclose($progressFile);
    }

    
    // Get the full product feed for the given page and size
    public function getFullProductFeed($pageSize = 1000000, $currentPage = 1)
    {
        // Get product collection
        $products = Mage::getModel('pureclarity_core/product')->getCollection()
            ->setStoreId($this->storeId)
            ->addUrlRewrite()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter("status", array("eq" => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))
            ->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);
            
        // Get pages
        $pages = $products->getLastPageNumber();
        if ($currentPage > $pages) {
            $products = array();
        }
        
        // Loop through products
        $feedProducts = array();
        foreach($products as $product) {

            // Check hash that we've not already seen this product
            if($this->seenProductIds[$product->getId()]===null) {

                // Set Category Ids for product
                $categories = $product->getCategoryIds();
                $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('entity_id', array('in' => $categories))
                    ->addFieldToFilter('is_active', array("in" => array('1')));
                
                // Get a list of the category names
                $categoryList = array();
                foreach ($categoryCollection as $category) {
                    $categoryList[] = $category->getName();
                }

                // Get Product Link URL
                $productUrl = str_replace(Mage::getBaseUrl(), '', $product->getUrlPath());
                $productUrl = str_replace(Mage::getUrl('', array('_secure' => true)), '', $productUrl);
                if (substr($productUrl, 0, 1) != '/') {
                    $productUrl = '/' . $productUrl;
                }

                // Get Product Image URL
                $productImageUrl = '';
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
                
                // Set standard data
                $data = array("_index" => count($feedProducts)+1);
                $this->setProductData($product, $data);

                // Set Other data
                $data["Link"] = $productUrl;
                $data["Image"] = $productImageUrl;
                $data["Categories"] = $categories;
                $data["MagentoCategories"] = array_values(array_unique($categoryList, SORT_STRING));
                $data["MagentoProductId"] = $product->getId();
                $data["MagentoProductType"] = $product->getTypeId();
                $data["InStock"] = (Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getIsInStock() == 1) ? true : false;

                // Add attributes
                $this->setAttributes($product, $data);

                // Look for child products in Configurable, Grouped or Bundled products
                $childProducts = array();
                switch ($product->getTypeId()) {
                    case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                        $childIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());
                        $childProducts = Mage::getModel('pureclarity_core/product')->getCollection()
                                ->addAttributeToSelect('*')
                                ->addFieldToFilter('entity_id', array('in'=> $childIds));
                        break;
                    case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                        $childProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                        break;
                    case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                        $childProducts = $product->getTypeInstance(true)->getSelectionsCollection($product->getTypeInstance(true)->getOptionsIds($product), $product);
                        break;
                }
                // Set child products if we have any
                $this->childProducts($childProducts, $data);
                // Set prices
                $this->setProductPrices($product, $data, $childProducts);
                // Add to feed array
                $feedProducts[] = $data;
                // Add to hash to make sure we don't get dupes
                $this->seenProductIds[$product->getId()] = true;
            }

        }
        
        $products->clear;
        return  array(
            "Pages" => $pages,
            "Products" => $feedProducts
        );
    }

    protected function childProducts($products, &$data){
        foreach($products as $product){
            $this->setProductData($product, $data);
            $this->setAttributes($product, $data);
        }
    }

    protected function setProductData($product, &$data)
    {
        $this->addValueToDataArray($data, 'Sku', $product->getData('sku'));
        $this->addValueToDataArray($data, 'Title', $product->getData('name'));
        $this->addValueToDataArray($data, 'Description', strip_tags($product->getData('description')));
        $this->addValueToDataArray($data, 'Description', strip_tags($product->getShortDescription()));
    }

    protected function addValueToDataArray(&$data, $key, $value){
        if ($value !== null && (!is_array($data[$key]) || !in_array($value, $data[$key]))){
            $data[$key][] = $value;
        }
    }

    protected function setProductPrices($product, &$data, &$childProducts = null)
    {
        // TODO - Get Customer Group Prices
        $groupPrices = $product->getData('group_price');

        $basePrices = $this->getProductPrice($product, false, true, $childProducts);
        $baseFinalPrices = $this->getProductPrice($product, true, true, $childProducts);
        foreach($this->currenciesToProcess as $currency){
            // Process currency for min price
            $minPrice = $this->convertCurrency($basePrices['min'], $currency);
            $this->addValueToDataArray($data, 'Prices', number_format($minPrice, 2, '.', '').' '.$currency);
            $minFinalPrice = $this->convertCurrency($baseFinalPrices['min'], $currency);
            if ($minFinalPrice !== null && $minFinalPrice < $minPrice){
                $this->addValueToDataArray($data, 'SalesPrices', number_format($minFinalPrice, 2,'.', '').' '.$currency);
            }
            // Process currency for max price if it's different to min price
            $maxPrice = $this->convertCurrency($basePrices['max'], $currency);
            if ($minPrice<$maxPrice){
                $this->addValueToDataArray($data, 'Prices', number_format($maxPrice, 2, '.', '').' '.$currency);
                $maxFinalPrice = $this->convertCurrency($baseFinalPrices['max'], $currency);
                if ($maxFinalPrice !== null && $maxFinalPrice < $maxPrice){
                    $this->addValueToDataArray($data, 'SalesPrices', number_format($maxFinalPrice, 2,'.', '').' '.$currency);
                }
            }
        }
    }

     protected function convertCurrency($price, $to){
        if ($to === $this->baseCurrencyCode) return $price;
        return Mage::helper('directory')->currencyConvert($price, $this->baseCurrencyCode, $to);
    }

     protected function getProductPrice(Mage_Catalog_Model_Product $product, $getFinalPrice = false, $includeTax = true, &$childProducts = null) {
        $minPrice = 0;
        $maxPrice = 0;
        switch ($product->getTypeId()) {
            case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                $model = $product->getPriceModel();
                $minPrice = $model->getTotalPrices($product, 'min', $includeTax);
                $maxPrice = $model->getTotalPrices($product, 'max', $includeTax);
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                $config = Mage::getSingleton('catalog/config');
                $groupProduct = Mage::getModel('pureclarity_core/product')
                    ->getCollection()
                    ->addAttributeToSelect($config->getProductAttributes())
                    ->addAttributeToFilter('entity_id', $product->getId())
                    ->setPage(1, 1)
                    ->addMinimalPrice()
                    ->addTaxPercents()
                    ->load()
                    ->getFirstItem();
                if ($groupProduct) {
                    $minPrice = $groupProduct->getMinimalPrice();
                    $maxPrice = $groupProduct->getMaxPrice();
                    if ($includeTax) {
                        $helper = Mage::helper('tax');
                        $minPrice = $helper->getPrice($tmpProduct, $minPrice, true);
                        $maxPrice = $helper->getPrice($tmpProduct, $maxPrice, true);
                    }
                }
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                $price = $this->getDefaultFromProduct($product,$getFinalPrice,$includeTax);
                if (!$price) {
                    $associatedProducts = ($childProducts !== null) ? 
                                          $childProducts : 
                                          Mage::getModel('catalog/product_type_configurable')
                                            ->getUsedProducts(null, $product);
                    $lowestPrice = false;
                    $highestPrice = false;
                    foreach ($associatedProducts as $associatedProduct) {
                        $productModel = Mage::getModel('catalog/product')->load($associatedProduct->getId());
                        $variationPrices = $this->getProductPrice($productModel, $getFinalPrice, true);
                        
                        if (!$lowestPrice || $variationPrices['min'] < $lowestPrice) {
                            $lowestPrice = $variationPrices['min'];
                        }
                        if (!$highestPrice || $variationPrices['max'] > $highestPrice){
                            $highestPrice = $variationPrices['max'];
                        }
                    }
                    $minPrice = $lowestPrice;
                    $maxPrice = $highestPrice;
                }
                else {
                    $minPrice = $price;
                    $maxPrice = $price;
                    $attributes = $product->getTypeInstance(true)->getConfigurableAttributes($product);
                    $maxOptionPrice = 0;
                    foreach($attributes as $attribute) {
                        $attributePrices = $attribute->getPrices();
                        if (is_array($attributePrices)) {
                            foreach($attributePrices as $attributePrice){
                                if (isset($attributePrice['pricing_value']) && isset($attributePrice['is_percent'])){
                                    if ($attributePrice['is_percent']) {
                                        $priceValue = $price * $attributePrice['pricing_value'] / 100;
                                    }
                                    else {
                                        $priceValue = $attributePrice['pricing_value'];
                                    }
                                    $priceValue = $this->convertPrice($priceValue, true);
                                    if ($priceValue > $maxOptionPrice)
                                        $maxOptionPrice = $priceValue;
                                }
                            }
                        }
                    }
                    if ($maxOptionPrice>0){
                        $product->setConfigurablePrice($maxOptionPrice);
                        $configurablePrice = $product->getConfigurablePrice();
                        $maxPrice = $maxPrice + $configurablePrice;
                    }
                }
                break;
            default:
                $minPrice = $this->getDefaultFromProduct($product,$getFinalPrice,$includeTax);
                $maxPrice = $minPrice;
                break;
        }
        return array('min' => $minPrice, 'max' => $maxPrice);
    }

    protected function getDefaultFromProduct(Mage_Catalog_Model_Product $product, $getFinalPrice = false, $includeTax = true) 
    {
        $price = $getFinalPrice ? $product->getFinalPrice() : $product->getPrice();
        if ($includeTax) {
            $helper = Mage::helper('tax');
            $price = $helper->getPrice($product, $price, true);
        }
        return $price;
    }


    protected function setAttributes(Mage_Catalog_Model_Product $product, &$data)
    {  
        foreach ($this->attributesToInclude as $attribute) {
            $code = $attribute[0];
            $name = $attribute[1];
            if ($product->getData($code) != null) {
                $attrValue = $product->getAttributeText($code);
                if (is_array($attrValue)){
                    foreach($attrValue as $value){
                        $this->addValueToDataArray($data, $name, $value);
                    }
                }
                else {
                    $this->addValueToDataArray($data, $name, $attrValue);
                }
            }
            $productBrand = null;
            if (Mage::helper('pureclarity_core')->isBrandFeedEnabled($this->storeId)) {
                $brandID = $product->getData($brandCode);
                $productBrand = $brandLookup[$brandID];
                if ($productBrand !== null) {
                    $data["Brand"][] = $productBrand;
                }
            }
        }
    }

    protected function convertPrice($price, $round = false)
    {
        if (empty($price)) {
            return 0;
        }

        $price = $this->currentStore->convertPrice($price);
        if ($round) {
            $price = $this->currentStore->roundPrice($price);
        }

        return $price;
    }


}