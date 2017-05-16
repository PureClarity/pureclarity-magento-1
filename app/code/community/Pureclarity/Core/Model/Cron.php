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
* PureClarity Cron Model
*/
class Pureclarity_Core_Model_Cron extends Mage_Core_Model_Abstract
{
    const DELTA_LOG               = 'pureclarity_delta.log';
    const PRODUCT_DELTA           = '/api/productdelta';
    const PRODUCT_DELTA_UPDATE    = '/api/productdeltastatus';
    const NOTIFICATION_URL        = '/api/productfeed?appkey={access_key}&url={website_root_url}%2F{path_to_file}&feedtype=pureclarity_json';
    const CUSTOM_NOTIFICATION_URL = '/api/productfeed?appkey={access_key}&url={custom_url}&feedtype=pureclarity_json';

    protected $_soapHelper;

    /**
     * Load our soap helper
     */
    public function _construct()
    {
        $this->_soapHelper = Mage::helper('pureclarity_core/soap');
    }

    /**
     * Create a SOAP call to PureClarity that will contain all the product deltas since the last run
     *
     * @throws Mage_Core_Exception
     */
    public function deltaFeed()
    {
        Mage::log('PureClarity: Processing Delta Feeds');
        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }
        Mage::log('PureClarity: Processing Delta Feeds');

        $deleteProducts = $feedProducts = array();

        // get deltaFeed Collection
        $collection = Mage::getModel('pureclarity_core/productFeed')
            ->getCollection()
            ->addFieldToFilter('status_id', array('eq' => 0));

        // create a unique token until we get a response from PureClarity
        $uniqueId = 'PureClarity' . uniqid();

        // park these so that another process doesn't pick them up
        foreach ($collection as $deltaProduct) {
            $deltaProduct->setStatusId(3)->setToken($uniqueId)->save();
        }

        // load products
        foreach ($collection as $deltaProduct) {
            $product = Mage::getModel('catalog/product')->load($deltaProduct->getProductId());

            if($deltaProduct->getDeleted() == 1) {
                $deleteProducts[] = $product->getSku();
            } else {

                // get categories for product
                $feedCategories = $categoryList = array();
                $categories = $product->getCategoryIds();
                $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('entity_id', array('in' => $categories));
                foreach ($categoryCollection as $category) {

                    $parentTree = array();

                    foreach ($category->getParentCategories() as $parent) {
                        if($parent->getId() != $category->getId() && $parent->getId() != Mage::app()->getStore(self::STORE_ID)->getRootCategoryId()) {
                            $parentTree[] = $parent->getName();
                        }
                    }

                    if(!empty($parentTree)) {
                        $feedCategories[] = implode(' > ', $parentTree) . ' > ' . $category->getName();
                    } else {
                        $feedCategories[] = $category->getName();
                    }

                    $categoryList[] = $category->getName();

                }

                $productUrl = str_replace(Mage::getBaseUrl(), '', $product->getProductUrl());
                $productUrl = str_replace(Mage::getUrl('',array('_secure'=>true)), '', $productUrl);
                if (substr($productUrl, 0, 1) != '/') {
                    $productUrl = '/' . $productUrl;
                }

                // TODO revisit - RBL workaround
                $productImageUrl = str_replace('-admin', '', Mage::helper('catalog/image')->init($product, 'image')->resize(250)->__toString());
                $productImageUrl = str_replace('admin.', 'www.', $productImageUrl);

                /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                if ($stockItem->getIsInStock() == 1){
                    $inStock = true;
                } else {
                    $inStock = false;
                }

                /** @var Mage_Catalog_Model_Product $product */
                $data = array(
                    "Sku"                   => $product->getData('sku'),
                    "Title"                 => $product->getData('name'),
                    "Description"           => strip_tags($product->getData('description')),
                    "Link"                  => $productUrl,
                    "Image"                 => $productImageUrl,
                    "ImageOverlay"          => '',
                    "Categories"            => $feedCategories,
                    "MagentoCategories"     => $categoryList,
                    "Brand"                 => $product->getData('brand'),
                    "Prices"                => array($product->getData('price')),
                    "OnOffer"               => false,
                    "NewArrival"            => false,
                    "MagentoProductId"      => $product->getId(),
                    "MagentoPromoText"      => $product->getPromoText(),
                    "MagentoProductType"    => $product->getTypeId(),
                    "MagentoStock"          => $inStock
                );

                /* TODO Look at this in ps-dev
                if ($product->getData('special_price') != null) {
                    $data["SalePrices"] = array($product->getData('special_price'));
                }
                */

                if($product->getData('color') != null) {
                    $data["Colour"] = array($product->getAttributeText('color'));
                }

                $sizes = array();

                if($product->getTypeId() == 'configurable') {
                    $childProducts = $product->getTypeInstance()->getUsedProducts();
                    foreach($childProducts as $childProduct) {
                        $sizes[] = $childProduct->getAttributeText('size');
                    }
                }

                if (count($sizes) > 0) {
                    $data["Size"] = $sizes;
                }

                $feedProducts[] = $data;

            }
        }

        $request = array(
            'AppKey'            => Mage::helper('pureclarity_core')->getAccessKey(),
            'Products'          => $feedProducts,
            'DeleteProducts'    => $deleteProducts
        );

        $body = Mage::helper('pureclarity_core')->formatFeed($request, 'json');
        $response = $this->_soapHelper->makeRequest($body, self::PRODUCT_DELTA);
        Mage::log($response);
        $response = json_decode($response);

        if(is_object($response)) {

            $token = $response->Token;
            
            foreach ($collection as $deltaProduct) {
                $deltaProduct->setStatusId(2)->setToken($token)->save();
            }
        } else {
            $errors = array();
            foreach ($collection as $deltaProduct) {
                $errors[] = $deltaProduct->getId();
            }

            Mage::log('DELTA Issue from PC - ' . var_export($errors, true), null, self::DELTA_LOG);
        }

    }

    private function getExportReadyDir(){
        $varDir = 'media';
        return $varDir . DS . 'pureclarity';
    }

    private function getStoreUrlNoTrailingSlash(){
        $storeUrl = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_URL, 1);
        if(substr($storeUrl, -1, strlen($storeUrl)) == '/') {
            $storeUrl = substr($storeUrl, 0, strlen($storeUrl)-1);
        }
        return $storeUrl;
    }

    private function getNotificationUrl($filename, $feedtype){
        // Get the custom URL for this feed type
        $helper = Mage::helper('pureclarity_core');
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  
                $customUrl = $helper->getFullFeedProdUrl(); 
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: 
                $customUrl = $helper->getFullFeedCatUrl();  
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    
                $customUrl = $helper->getFullFeedBrandUrl(); 
                break;
            default: 
                throw new \Exception("Pureclarity feed type not recognised: $feedtype");
        }
        // Check if the custom URL is actually specified
        if ($customUrl == "") {
            // Generate the notification URL with the automatic feed URL
            $notificationUrl = self::NOTIFICATION_URL;
            $notificationUrl = str_replace('{access_key}', Mage::helper('pureclarity_core')->getAccessKey(), $notificationUrl);
            $storeUrl = $this->getStoreUrlNoTrailingSlash();
            $notificationUrl = str_replace('{website_root_url}', urlencode($storeUrl), $notificationUrl);
            $exportReadyDir = $this->getExportReadyDir();
            $pathToFile = $exportReadyDir . DS . $filename;
            if (DS != '/') { //the directory separator might not be a '/' but urls should always use '/'
                $pathToFile = str_replace(DS, '/', $pathToFile);
            }
            $notificationUrl = str_replace('{path_to_file}', urlencode($pathToFile), $notificationUrl);
            return $notificationUrl;
        }
        else{
            // Generate the notification URL with the custom feed URL
            $customNotificationUrl = self::CUSTOM_NOTIFICATION_URL;
            $customNotificationUrl = str_replace('{access_key}', Mage::helper('pureclarity_core')->getAccessKey() , $customNotificationUrl);
            $customNotificationUrl = str_replace('{custom_url}', urlencode($customUrl) , $customNotificationUrl);
            return $customNotificationUrl;
        }

    }

    private function getFileNameForFeed($feedtype, $storeCode){
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  return $storeCode . '-pureclarity_product_feed.json';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: return $storeCode . '-pureclarity_category_feed.json';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    return $storeCode . '-pureclarity_brand_feed.json';
        }
        return "UNKNOWN_FEED";
    }

    private function getIDForFeed($feedtype){
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  return 'product';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: return 'category';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    return 'brand';
        }
        return "UNKNOWN_FEED";
    }

    /**
     * Produce a feed and notify PureClarity so that it can fetch it.
     */
    public function doFeed($feedtype, $storeId){

        // Brand feed can be disabled separately
        if ($feedtype == Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND){
            if(!Mage::helper('pureclarity_core')->isBrandFeedEnabled($storeId)) {
                return;
            }
        }

        $progressFileName = Pureclarity_Core_Helper_Data::progressFileName($feedtype);

        $store = Mage::getModel('core/store')->load($storeId);


        // file IO handler object
        $fileIo = new Varien_Io_File();
        // Determine the file path and make sure the folder exists.
        $filename = $this->getFileNameForFeed($feedtype, $store->getCode());
        $baseDir = Mage::getBaseDir();
        $exportReadyDir = $this->getExportReadyDir();
        $fileIo->mkdir($baseDir . DS . $exportReadyDir);
        $file = $baseDir . DS . $exportReadyDir . DS . $filename;


        $feedFile = @fopen($file, "w+");
        if ((!$feedFile) || !flock($feedFile, LOCK_EX | LOCK_NB)){
            throw new \Exception("Pureclarity: Cannot open feed file for writing: " . $file);
        }
        $feedModel = Mage::getModel('pureclarity_core/feed');

        // Get the feed data for the specified feed type
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  
                $productExportModel = Mage::getModel('pureclarity_core/productexport');
                $productExportModel->init($storeId);
                $feedData = $productExportModel->processFeed($progressFileName);
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: 
                $feedData = $feedModel->getFullCatFeed($progressFileName);
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    
                $feedData = $feedModel->getFullBrandFeed($progressFileName);
                break;
            default:
                throw new \Exception("Pureclarity feed type not recognised: $feedtype");
        }   


        // Format the feed in JSON format
        $json = Mage::helper('pureclarity_core')->formatFeed($feedData, 'json');


        // Save feed to file
        fwrite($feedFile, $json);
        fclose($feedFile);


        $feedName = $this->getIDForFeed($feedtype);
        $progressFile = fopen($progressFileName, "w");
        fwrite($progressFile, "{\"name\":\"$feedName\",\"cur\":\"1\",\"max\":\"1\",\"isComplete\":true}");
        fclose($progressFile);


        // notify PC about the feed being available (url builds the feed)
        $notificationUrl = $this->getNotificationUrl($filename, $feedtype);
        //echo $notificationUrl;
        //$this->_soapHelper->makeGetRequest($notificationUrl);
    }

    // Produce a product feed and notify PureClarity so that it can fetch it.
    public function fullProductFeed($storeId){ $this->doFeed(Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT, $storeId); }
    // Produce a category feed and notify PureClarity so that it can fetch it.
    public function fullCategoryFeed($storeId){ $this->doFeed(Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY, $storeId); }
    // Produce a brand feed and notify PureClarity so that it can fetch it.
    public function fullBrandFeed($storeId){ $this->doFeed(Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND, $storeId); }

    public function runAllFeeds(){
        // Loop round each store and create feed
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    // Only generate feeds when feed notification is active
                    if(!Mage::helper('pureclarity_core')->isFeedNotificationActive($store->getId())) {
                        // Brand feed is probably the quickest, do that first
                        fullBrandFeed($store->getId());
                        // Then the category feed.
                        fullCategoryFeed($store->getId());
                        // The product is almost certainly the slowest.
                        fullProductFeed($store->getId());
                    }
                }
            }
        }
    }
}
