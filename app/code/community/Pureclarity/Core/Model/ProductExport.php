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
class Pureclarity_Core_Model_ProductExport extends Pureclarity_Core_Model_Model
{

    public $storeId = null;
    public $baseCurrencyCode = nulls;
    public $currenciesToProcess = array();
    public $attributesToInclude = array();
    public $seenProductIds = array();
    public $currentStore = null;
    public $brandLookup = array();
    protected $categoryCollection = array();

    /**
     * Initialise the model ready to set the data for the given store.
     */
    public function init($storeId = null)
    {
        try{
            $this->storeId = $storeId;
            // Use this store, if not passed in.
            if (is_null($this->storeId)) {
                Mage::log("PureClarity: In ProductExport->init(): store id is null, so getting from existing store");
                $this->storeId = Mage::app()->getStore()->getId();
                Mage::log("PureClarity: In ProductExport->init(): store id is now " . $this->storeId);
            }
            
            $this->currentStore = Mage::getModel('core/store')->load($this->storeId);
            Mage::log("PureClarity: In ProductExport->init(): set the currentStore");

            // Set Currency list
            $currencyModel = Mage::getModel('directory/currency'); 
            $this->baseCurrencyCode = Mage::app()->getBaseCurrencyCode();
            Mage::log("PureClarity: In ProductExport->init(): baseCurrencyCode is " . $this->baseCurrencyCode);
            $currencies = $currencyModel->getConfigAllowCurrencies();
            Mage::log("PureClarity: In ProductExport->init(): got currencies: " . print_r($currencies, true));
            $currencyRates = $currencyModel->getCurrencyRates($this->baseCurrencyCode, array_values($currencies));
            Mage::log("PureClarity: In ProductExport->init(): got currencyRates: " . print_r($currencyRates, true));

            $this->currenciesToProcess[] = $this->baseCurrencyCode;
            foreach ($currencies as $currency) {
                Mage::log("PureClarity: In ProductExport->init(): processing currency: " . $currency);
                if ($currency != $this->baseCurrencyCode && isset($currencyRates[$currency]) && ! empty($currencyRates[$currency])) {
                    $this->currenciesToProcess[] = $currency;
                }
            }

            Mage::log("PureClarity: In ProductExport->init(): currenciesToProcess: " . print_r($this->currenciesToProcess, true));

            // Manage Brand
            $this->brandLookup = array();
            // If brand feed is enabled, get the brands
            if($this->coreHelper->isBrandFeedEnabled($this->storeId)) {
                Mage::log("PureClarity: In ProductExport->init(): brand feed is enabled");
                $feedModel = Mage::getModel('pureclarity_core/feed');
                $this->brandLookup = $feedModel->getBrandFeedArray($this->storeId);
                Mage::log("PureClarity: In ProductExport->init(): got the brand feed array");
            }
            else{
                Mage::log("PureClarity: In ProductExport->init(): brand feed not enabled");
            }

            // Get Attributes
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
            $attributesToExclude = array(
                "prices",
                "price"
            );
            Mage::log("PureClarity: In ProductExport->init(): about to get list of attributes to include");

            // Get list of attributes to include
            foreach ($attributes as $attribute) {
                $code = $attribute->getAttributecode();
                $label = $attribute->getFrontendLabel(); // required for empty() use below @php5.4
                if (! in_array(strtolower($code), $attributesToExclude) && ! empty($label)) {
                    $this->attributesToInclude[] = array(
                        $code, 
                        $attribute->getFrontendLabel()
                    );
                }
            }

            Mage::log("PureClarity: In ProductExport->init(): got list of attributes to include");

            // Get Category List 
            $this->categoryCollection = array();
            $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addFieldToFilter(
                    'is_active', array(
                        "in" => array(
                            '1'
                        )
                    )
                );
            Mage::log("PureClarity: In ProductExport->init(): got magento category collection");
            foreach ($categoryCollection as $category) {
                $this->categoryCollection[$category->getId()] = $category->getName();
            }

            Mage::log("PureClarity: In ProductExport->init(): set category array");
        }
        catch(\Exception $e) {
            Mage::log("PureClarity: In ProductExport->init(): Exception: " . $e->getMessage());
        }
    }
    
    /**
     * Get the full product feed for the given page and size
     */
    public function getFullProductFeed($pageSize = 1000000, $currentPage = 1)
    {
        Mage::log("PureClarity: In ProductExport->getFullProductFeed(), page size {$pageSize}, current page {$currentPage}");
        // Get product collection
        $validVisibility = array(
            'in' => array(
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH, 
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG, 
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH
            )
        );
        $products = Mage::getModel('pureclarity_core/product')->getCollection()
            ->setStoreId($this->storeId)
            ->addStoreFilter($this->storeId)
            ->addUrlRewrite()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter(
                "status", array(
                    "eq" => Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                )
            )
            ->addFieldToFilter('visibility', $validVisibility)
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);
        Mage::log("PureClarity: In ProductExport->getFullProductFeed(): got product collection");

        // Get pages
        $pages = $products->getLastPageNumber();
        if ($currentPage > $pages) {
            $products = array();
        }
        
        // Loop through products
        $feedProducts = array();
        foreach ($products as $product) {
            $data = $this->getProductData($product, count($feedProducts) + ($pageSize * $currentPage) + 1);
            if ($data != null)
                $feedProducts[] = $data;
        }
        
        return  array(
            "Pages" => $pages,
            "Products" => $feedProducts
        );
    }

    /**
     * Returns the product data
     * @return array
     */
    public function getProductData(&$product, $index)
    {
        try{
            // Check hash that we've not already seen this product
            if(! array_key_exists($product->getId(), $this->seenProductIds) 
                || $this->seenProductIds[$product->getId()]===null) {
                // Set Category Ids for product
                $categoryIds = $product->getCategoryIds();

                // Get a list of the category names
                $categoryList = array();
                $brandId = null;
                foreach ($categoryIds as $id) {
                    if (array_key_exists($id, $this->categoryCollection)) {
                        $categoryList[] = $this->categoryCollection[$id];
                    }

                    if (! $brandId && array_key_exists($id, $this->brandLookup)) {
                        $brandId = $id;
                    }
                }

                // Get Product Link URL
                $urlParams = array(
                    '_nosid' => true,
                    // '_scope' => $this->storeId // not relevant for Magento 1 (M2 plugin only, keeping here but commenting out to avoid future query)
                );
                $productUrl = $product->setStoreId($this->storeId)
                    ->getUrlModel()
                    ->getUrl($product, $urlParams);
                if ($productUrl) {
                    $productUrl = $this->removeUrlProtocol($productUrl);
                }

                // Get Product Image URL
                $productImageUrl = '';
                if ($product->getImage() && $product->getImage() != 'no_selection') {
                    $image = Mage::helper('catalog/image');
                    try {
                        $productImage = $image->init($product, 'image');
                        if ($productImage != null) {
                            $image->resize(250);
                            $productImageUrl = $image->__toString();
                        }
                    }
                    catch(\Exception $e) {
                        Mage::log("ERROR: Image File not found for product with Id: " . $product->getId());
                    }
                } else {
                    $productImageUrl = $this->coreHelper->getProductPlaceholderUrl($this->storeId);
                    if (! $productImageUrl) {
                        $productImageUrl = $this->getSkinUrl(self::PLACEHOLDER_IMAGE_URL);
                        if (! $productImageUrl) {
                            $productImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . "frontend/base/default/" . self::PLACEHOLDER_IMAGE_URL;
                        }
                    }
                }

                $productImageUrl = $this->removeUrlProtocol($productImageUrl);

                // Set standard data
                $isInStock = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($product)
                    ->getIsInStock();
                $data = array(
                    "_index" => $index,
                    "Id" => $product->getId(),
                    "Sku" => $product->getData('sku'),
                    "Title" => $product->getData('name'),
                    "Description" => array(
                            strip_tags($product->getData('description')),
                            strip_tags($product->getShortDescription())
                        ),
                    "Link" => $productUrl,
                    "Image" => $productImageUrl,
                    "Categories" => $categoryIds,
                    "MagentoProductId" => $product->getId(),
                    "MagentoCategories" => array_values(array_unique($categoryList, SORT_STRING)),
                    "MagentoProductType" => $product->getTypeId(),
                    "InStock" => $isInStock,
                );

                // Set visibility
                switch ($product->getVisibility()) {
                    case Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG:
                        $data["ExcludeFromSearch"] = true;
                        break;
                    case Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH:
                        $data["ExcludeFromProductListing"] = true;
                        break;
                }

                // Set Brand
                if ($brandId) {
                    $data["Brand"] = $brandId;
                }

                // Set PureClarity Custom values
                $searchTagString = $product->getData('pureclarity_search_tags');
                if (! empty($searchTagString)) {
                    $searchTags = explode(",", $searchTagString);
                    if(count($searchTags)) {
                        foreach ($searchTags as $key => &$searchTag) {
                            $searchTag = trim($searchTag);
                            if(empty($searchTag)) {
                                unset($searchTags[$key]);
                            }
                        }

                        if(count($searchTags)) {
                            $data["SearchTags"] = array_values($searchTags);
                        }
                    }
                }

                $overlayImage = $product->getData('pureclarity_overlay_image');
                if ($overlayImage != "") {
                    $data["ImageOverlay"] = $this->coreHelper->getPlaceholderUrl() . $overlayImage;
                }

                if ($product->getData('pureclarity_exc_rec') == '1') {
                    $data["ExcludeFromRecommenders"] = true;
                }
            
                if ($product->getData('pureclarity_newarrival') == '1') {
                    $data["NewArrival"] = true;
                }
                
                if ($product->getData('pureclarity_onoffer') == '1') {
                    $data["OnOffer"] = true;
                }
                
                $promoMessage = $product->getData('pureclarity_promo_message');
                if ($promoMessage != null && $promoMessage != '') {
                    $data["PromoMessage"] = $promoMessage;
                }

                // Add attributes
                $this->setAttributes($product, $data);
                // Look for child products in Configurable, Grouped or Bundled products
                $childProducts = array();
                switch ($product->getTypeId()) {
                    case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                        $childIds = Mage::getModel('catalog/product_type_configurable')
                            ->getChildrenIds($product->getId());
                        if (count($childIds[0]) > 0) {
                            $childProducts = Mage::getModel('pureclarity_core/product')
                                ->getCollection()
                                ->addAttributeToSelect('*')
                                ->addFieldToFilter(
                                    'entity_id', array(
                                        'in' => $childIds[0]
                                    )
                                );
                        }
                        else{
                            //configurable with no children - exclude from feed
                            return null;
                        }
                        break;
                    case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                        $childProducts = $product->getTypeInstance(true)
                            ->getAssociatedProducts($product);
                        break;
                    case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                        $childProducts = $product->getTypeInstance(true)
                            ->getSelectionsCollection(
                                $product->getTypeInstance(true)->getOptionsIds($product), 
                                $product
                            );
                        break;
                }

                $this->processChildProducts($childProducts, $data);

                // Set prices
                $this->setProductPrices($product, $data, $childProducts);

                // Add to hash to make sure we don't get dupes
                $this->seenProductIds[$product->getId()] = true;

                // Add to feed array
                return $data;
            }

            return null;
        }
        catch(\Exception $e) {
            Mage::log("PureClarity: In ProductExport->getProductData(): Error: " . $e->getMessage());
        }
    }

    protected function processChildProducts($childProducts, &$data)
    {
        foreach ($childProducts as $product) {
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
        if (! empty($searchTag)) {
            $this->addValueToDataArray($data, 'SearchTags', $searchTag);
        }
    }

    protected function addValueToDataArray(&$data, $key, $value)
    {
        if (! array_key_exists($key, $data)) {
            $data[$key][] = $value;
        }
        elseif ($value !== null 
                && (! is_array($data[$key]) || ! in_array($value, $data[$key]))
            ) {
            $data[$key][] = $value;
        }
    }

    protected function setProductPrices($product, &$data, &$childProducts = null)
    {
        $basePrices = $this->getProductPrice($product, false, true, $childProducts);
        $baseFinalPrices = $this->getProductPrice($product, true, true, $childProducts);
        foreach ($this->currenciesToProcess as $currency) {
            // Process currency for min price
            $minPrice = $this->convertCurrency($basePrices['min'], $currency);
            $this->addValueToDataArray($data, 'Prices', number_format($minPrice, 2, '.', '') . ' ' . $currency);
            $minFinalPrice = $this->convertCurrency($baseFinalPrices['min'], $currency);
            if ($minFinalPrice !== null && $minFinalPrice < $minPrice) {
                $this->addValueToDataArray($data, 'SalePrices', number_format($minFinalPrice, 2, '.', '') . ' ' . $currency);
            }

            // Process currency for max price if it's different to min price
            $maxPrice = $this->convertCurrency($basePrices['max'], $currency);
            if ($minPrice < $maxPrice) {
                $this->addValueToDataArray($data, 'Prices', number_format($maxPrice, 2, '.', '') . ' ' . $currency);
                $maxFinalPrice = $this->convertCurrency($baseFinalPrices['max'], $currency);
                if ($maxFinalPrice !== null && $maxFinalPrice < $maxPrice) {
                    $this->addValueToDataArray($data, 'SalePrices', number_format($maxFinalPrice, 2, '.', '') . ' ' . $currency);
                }
            }
        }
    }

     protected function convertCurrency($price, $to)
     {
        if ($to === $this->baseCurrencyCode) {
            return $price;
        }

        return Mage::helper('directory')->currencyConvert($price, $this->baseCurrencyCode, $to);
     }

     protected function getProductPrice(Mage_Catalog_Model_Product $product, $getFinalPrice = false, $includeTax = true, &$childProducts = null) 
     {
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
                        $taxHelper = Mage::helper('tax');
                        $minPrice = $taxHelper->getPrice($groupProduct, $minPrice, true);
                        $maxPrice = $taxHelper->getPrice($groupProduct, $maxPrice, true);
                    }
                }
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                $price = $this->getDefaultFromProduct($product, $getFinalPrice, $includeTax);
                if (! $price) {
                    $associatedProducts = ($childProducts !== null) ? 
                                          $childProducts : 
                                          Mage::getModel('catalog/product_type_configurable')
                                            ->getUsedProducts(null, $product);
                    $lowestPrice = false;
                    $highestPrice = false;
                    foreach ($associatedProducts as $associatedProduct) {
                        $productModel = Mage::getModel('catalog/product')->load($associatedProduct->getId());
                        $variationPrices = $this->getProductPrice($productModel, $getFinalPrice, true);
                        
                        if (! $lowestPrice || $variationPrices['min'] < $lowestPrice) {
                            $lowestPrice = $variationPrices['min'];
                        }

                        if (! $highestPrice || $variationPrices['max'] > $highestPrice) {
                            $highestPrice = $variationPrices['max'];
                        }
                    }

                    $minPrice = $lowestPrice;
                    $maxPrice = $highestPrice;
                }
                else {
                    $minPrice = $price;
                    $maxPrice = $price;
                    $attributes = $product->getTypeInstance(true)
                        ->getConfigurableAttributes($product);
                    $maxOptionPrice = 0;
                    foreach ($attributes as $attribute) {
                        $attributePrices = $attribute->getPrices();
                        if (is_array($attributePrices)) {
                            foreach ($attributePrices as $attributePrice) {
                                if (isset($attributePrice['pricing_value']) && isset($attributePrice['is_percent'])) {
                                    if ($attributePrice['is_percent']) {
                                        $pricingValue = ($price * $attributePrice['pricing_value'] / 100);
                                    }
                                    else {
                                        $pricingValue = $attributePrice['pricing_value'];
                                    }

                                    $priceValue = $this->convertPrice($pricingValue, true);
                                    if ($priceValue > $maxOptionPrice)
                                        $maxOptionPrice = $priceValue;
                                }
                            }
                        }
                    }

                    if ($maxOptionPrice > 0) {
                        $product->setConfigurablePrice($maxOptionPrice);
                        $configurablePrice = $product->getConfigurablePrice();
                        $maxPrice = $maxPrice + $configurablePrice;
                    }
                }
                break;
            default:
                $minPrice = $this->getDefaultFromProduct($product, $getFinalPrice, $includeTax);
                $maxPrice = $minPrice;
                break;
        }

        return array(
                'min' => $minPrice,
                'max' => $maxPrice
            );
     }

    protected function getDefaultFromProduct(Mage_Catalog_Model_Product $product, $getFinalPrice = false, $includeTax = true) 
    {
        $price = ($getFinalPrice ? $product->getFinalPrice() : $product->getPrice());
        if ($includeTax) {
            $price = Mage::helper('tax')->getPrice($product, $price, true);
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
                    if(is_array($product->getData($code))) {
                        if($code == 'media_gallery') {
                            $galleryImages = $product->getData($code)['images'];
                            $attrValue = array();
                            $media_base_url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
                            foreach ($galleryImages as $galleryImage) {
                                $attrValue[] = $this->removeUrlProtocol($media_base_url . 'catalog/product' . $galleryImage['file']);
                            }
                        }
                        else{
                            Mage::log("Unrecognized array attribute: {$code}");
                        }
                    }
                    else{
                        $attrValue = $product->getAttributeText($code);
                    }
                }
                catch (Exception $e) {
                    // Unable to read attribute text
                    continue;
                }

                if (! empty($attrValue)) {
                    if (is_array($attrValue)) {
                        foreach ($attrValue as $value) {
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
