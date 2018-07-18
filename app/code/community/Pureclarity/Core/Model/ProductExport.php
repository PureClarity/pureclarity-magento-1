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
    public $brandLookup = array();
    protected $categoryCollection = [];
    
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


        // Manage Brand
        $this->brandLookup = [];
        // If brand feed is enabled, get the brands
        if(Mage::helper('pureclarity_core')->isBrandFeedEnabled($this->storeId)) {
            $feedModel = Mage::getModel('pureclarity_core/feed');
            $this->brandLookup = $feedModel->BrandFeedArray($this->storeId);
        }

        // Get Attributes
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        $attributesToExclude = array("prices", "price");

        // Get list of attributes to include
        foreach ($attributes as $attribute){
            $code = $attribute->getAttributecode();
            if (!in_array(strtolower($code), $attributesToExclude) && !empty($attribute->getFrontendLabel())) {
                $this->attributesToInclude[] = array($code, $attribute->getFrontendLabel());
            }
        }

        // Get Category List 
        $this->categoryCollection = [];
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('is_active', array("in" => array('1')));
        foreach($categoryCollection as $category){
            $this->categoryCollection[$category->getId()] = $category->getName();
        }
    }


    
    // Get the full product feed for the given page and size
    public function getFullProductFeed($pageSize = 1000000, $currentPage = 1)
    {
        // Get product collection
        $validVisiblity = array('in' => array(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH, 
                                              Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG, 
                                              Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH));
        $products = Mage::getModel('pureclarity_core/product')->getCollection()
            ->setStoreId($this->storeId)
            ->addUrlRewrite()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter("status", array("eq" => Mage_Catalog_Model_Product_Status::STATUS_ENABLED))
            ->addFieldToFilter('visibility', $validVisiblity)
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
            $data = $this->processProduct($product, count($feedProducts)+($pageSize * $currentPage)+1);
            if ($data != null)
                $feedProducts[] = $data;
        }
        
        return  array(
            "Pages" => $pages,
            "Products" => $feedProducts
        );
    }

    // Gets the data for a product.
    public function processProduct(&$product, $index)
    {
        // Check hash that we've not already seen this product
        if(!array_key_exists($product->getId(), $this->seenProductIds) || $this->seenProductIds[$product->getId()]===null) {

            // Set Category Ids for product
            $categoryIds = $product->getCategoryIds();

             // Get a list of the category names
             $categoryList = [];
             $brandId = null;
             foreach ($categoryIds as $id) {
                 if (array_key_exists($id, $this->categoryCollection)){
                     $categoryList[] = $this->categoryCollection[$id];
                 }
                 if (!$brandId && array_key_exists($id, $this->brandLookup)){
                     $brandId = $id;
                 }
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
                $image =Mage::helper('catalog/image');
                $image->init($product, 'image');
                $image->resize(250);
                $productImageUrl = $image->__toString();
            }
            else{
                $productImageUrl = Mage::helper('pureclarity_core')->getProductPlaceholderUrl($this->storeId);
                if (!$productImageUrl) {
                    $productImageUrl = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                if (!$productImageUrl) {
                        $productImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)."frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                    }
                }
            }
            $productImageUrl = str_replace(array("https:", "http:"), "", $productImageUrl);
            
            // Set standard data
            $data = array(
                "Sku" => $product->getData('sku'),
                "Title" => $product->getData('name'),
                "Description" => array(strip_tags($product->getData('description')), strip_tags($product->getShortDescription())),
                "Link" => $productUrl,
                "Image" => $productImageUrl,
                "Categories" => $categoryIds,
                "MagentoCategories" => array_values(array_unique($categoryList, SORT_STRING)),
                "MagentoProductId" => $product->getId(),
                "MagentoProductType" => $product->getTypeId(),
                "InStock" => (Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getIsInStock() == 1) ? true : false
            );

            // Set the visibility for PureClarity
            $visibility = $product->getVisibility();
            if ($visibility == Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG){
                $data["ExcludeFromSearch"] = true;
            }
            else if ($visibility == Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH){
                $data["ExcludeFromProductListing"] = true;
            }

            // Set Brand
            if ($brandId){
                $data["Brand"] = $brandId;
            }

            // Set PureClarity Custom values
            $searchTag = $product->getData('pureclarity_search_tags');
            if ($searchTag != null && $searchTag != '')
                 $data["SearchTags"] = array($searchTag);

            $overlayImage = $product->getData('pureclarity_overlay_image');
            if ($overlayImage != "")
                $data["ImageOverlay"] = Mage::helper('pureclarity_core')->getPlaceholderUrl() . $overlayImage;

            if ($product->getData('pureclarity_exc_rec') == '1')
                 $data["ExcludeFromRecommenders"] = true;
        
            if ($product->getData('pureclarity_newarrival') == '1')
                 $data["NewArrival"] = true;
            
            if ($product->getData('pureclarity_onoffer') == '1')
                 $data["OnOffer"] = true;
            
            $promoMessage = $product->getData('pureclarity_promo_message');
            if ($promoMessage != null && $promoMessage != '')
                 $data["PromoMessage"] = $promoMessage;

            // Add attributes
            $this->setAttributes($product, $data);

            // Look for child products in Configurable, Grouped or Bundled products
            $childProducts = array();
            switch ($product->getTypeId()) {
                case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                    $childIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());
                    if (count($childIds[0]) > 0){
                        $childProducts = Mage::getModel('pureclarity_core/product')->getCollection()
                            ->addAttributeToSelect('*')
                            ->addFieldToFilter('entity_id', array('in'=> $childIds[0]));
                    }else{
                        //configurable with no children - exlude from feed
                        return null;
                    }
                    break;
                case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                    $childProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                    break;
                case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                    $childProducts = $product->getTypeInstance(true)->getSelectionsCollection($product->getTypeInstance(true)->getOptionsIds($product), $product);
                    break;
            }

            // Process any child products
            $this->childProducts($childProducts, $data);

            // Set prices
            $this->setProductPrices($product, $data, $childProducts);

            // Add to hash to make sure we don't get dupes
            $this->seenProductIds[$product->getId()] = true;

            // Add to feed array
            return $data;
        }

        return null;
    }

    protected function childProducts($products, &$data){
        foreach($products as $product){
            $this->setProductData($product, $data);
            $this->setAttributes($product, $data);
        }
    }

    protected function setProductData($product, &$data)
    {
        $this->addValueToDataArray($data, 'AssociatedSkus', $product->getData('sku'));
        $this->addValueToDataArray($data, 'AssociatedTitles', $product->getData('name'));
        $this->addValueToDataArray($data, 'Description', strip_tags($product->getData('description')));
        $this->addValueToDataArray($data, 'Description', strip_tags($product->getShortDescription()));
        $searchTag = $product->getData('pureclarity_search_tags');
        if ($searchTag != null && $searchTag != '')
            $this->addValueToDataArray($data, 'SearchTags', $searchTag);
    }

    protected function addValueToDataArray(&$data, $key, $value){

        if (!array_key_exists($key,$data)){
            $data[$key][] = $value;
        }else if ($value !== null && (!is_array($data[$key]) || !in_array($value, $data[$key]))){
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
                $this->addValueToDataArray($data, 'SalePrices', number_format($minFinalPrice, 2,'.', '').' '.$currency);
            }
            // Process currency for max price if it's different to min price
            $maxPrice = $this->convertCurrency($basePrices['max'], $currency);
            if ($minPrice<$maxPrice){
                $this->addValueToDataArray($data, 'Prices', number_format($maxPrice, 2, '.', '').' '.$currency);
                $maxFinalPrice = $this->convertCurrency($baseFinalPrices['max'], $currency);
                if ($maxFinalPrice !== null && $maxFinalPrice < $maxPrice){
                    $this->addValueToDataArray($data, 'SalePrices', number_format($maxFinalPrice, 2,'.', '').' '.$currency);
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
            case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
            case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                $config = Mage::getSingleton('catalog/config');
                $groupProduct = Mage::getModel('pureclarity_core/product')
                    ->getCollection()
                    ->setStoreId($this->storeId)
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
                        $minPrice = $helper->getPrice($groupProduct, $minPrice, true);
                        $maxPrice = $helper->getPrice($groupProduct, $maxPrice, true);
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
                try{
                    $attrValue = $product->getAttributeText($code);
                }
                catch (Exception $e){
                    // Unable to read attribute text
                    continue;
                }
                if (!empty($attrValue)){
                    if (is_array($attrValue)){
                        foreach($attrValue as $value){
                            $this->addValueToDataArray($data, $name, $value);
                        }
                    }
                    else {
                        $this->addValueToDataArray($data, $name, $attrValue);
                    }
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