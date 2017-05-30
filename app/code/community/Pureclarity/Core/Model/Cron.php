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
    const DELTA_LOG = 'pureclarity_delta.log';
    protected $soapHelper;

    public function _construct()
    {
        $this->soapHelper = Mage::helper('pureclarity_core/soap');
    }

    /**
     * Process Delta Feeds and push to PureClarity
     */
    public function deltaFeed($peakModeOnly = false)
    {
        // create a unique token until we get a response from PureClarity
        $uniqueId = 'PureClarity' . uniqid();
        $requests = array();

        // Loop round each store and process Deltas
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                foreach ($group->getStores() as $store) {

                    // Check we're allowed to do it for this store
                    if(Mage::helper('pureclarity_core')->isDeltaNotificationActive($store->getId()) || $peakModeOnly) {
                    
                        $deleteProducts = $feedProducts = array();

                        // get deltaFeed Collection for store
                        $storeIds = array('in' => array(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID, $store->getId()));
                        $collection = Mage::getModel('pureclarity_core/productFeed')
                            ->getCollection()
                            ->addFieldToFilter('status_id', array('eq' => 0))
                            ->addFieldToFilter('store_id', $storeIds);
                        
                        // Check we have something
                        if ($collection->count() > 0){

                            $deltaIds = array();

                            // park these so that another process doesn't pick them up, also
                            // create a hash to get last value (in case product been edited multiple times)
                            $productHash = array();
                            foreach ($collection as $deltaProduct) {
                                $deltaIds[] = $deltaProduct->getProductId();
                                if (!$peakModeOnly)
                                    $deltaProduct->setStatusId(3)->setToken($uniqueId)->save();
                                $productHash[$deltaProduct->getProductId().'-'.$deltaProduct->getStoreId()] = $deltaProduct;
                            }
                            
                            $productExportModel = Mage::getModel('pureclarity_core/productExport');
                            $productExportModel->init($store->getId());
                            // load products
                            foreach ($productHash as $deltaProduct) {
                                
                                // Get product for this store
                                $product = Mage::getModel('catalog/product')
                                    ->setStoreId($store->getId())
                                    ->load($deltaProduct->getProductId());
                                
                                // Is deleted?
                                $deleted = $product->getData('status') == Mage_Catalog_Model_Product_Status::STATUS_DISABLED ||
                                           $product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;

                                // Check product is loaded    
                                if ($product != null){
                                    // Check if deleted or if product is no longer visible
                                    if($deleted == true) {
                                        $deleteProducts[] = $product->getSku();
                                    } else {
                                        // Get data from product exporter
                                        $data = $productExportModel->processProduct($product);
                                        if ($data != null)
                                            $feedProducts[] = $data;
                                    }
                                }
                            }

                            $request = array(
                                'AppKey'            => Mage::helper('pureclarity_core')->getAccessKey($store->getId()),
                                'Secret'            => Mage::helper('pureclarity_core')->getSecretKey($store->getId()),
                                'Products'          => $feedProducts,
                                'DeleteProducts'    => $deleteProducts
                            );
                            $requests[] = $request;
                            
                            if (!$peakModeOnly){
                                $body = Mage::helper('pureclarity_core')->formatFeed($request, 'json');

                                $url = Mage::helper('pureclarity_core')->getDeltaEndpoint($store->getId());
                                $useSSL = Mage::helper('pureclarity_core')->useSSL($store->getId());

                                $response = $this->soapHelper->request($url, $useSSL, $body);
                                $response = json_decode($response);
                                if(!is_object($response)) {
                                    Mage::log('DELTA Issue from PC - ' . var_export($deltaIds, true), null, self::DELTA_LOG);
                                }
                                foreach ($collection as $deltaProduct) {
                                    $deltaProduct->delete();
                                }
                            }
                            $productExportModel = null;
                        }
                    }
                }
            }
        }
        return $requests;
    }

    


    // Produce a feed and notify PureClarity so that it can fetch it.
    public function doFeed($feedtype, $storeId){

        $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName($feedtype);
        $store = Mage::getModel('core/store')->load($storeId);
        $feedFilePath = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . Pureclarity_Core_Helper_Data::getFileNameForFeed($feedtype, $store->getCode());

        $feedFile = @fopen($feedFilePath, "w+");
        if ((!$feedFile) || !flock($feedFile, LOCK_EX | LOCK_NB)){
            throw new \Exception("Pureclarity: Cannot open feed file for writing: " . $file);
        }
        
        // Get the feed data for the specified feed type
        $feedName = '';
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:
                $productExportModel = Mage::getModel('pureclarity_core/productExport');
                $productExportModel->init($storeId);
                $feedData = $this->processProductFeed($productExportModel, $progressFileName);
                $feedName = 'product';
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY:
                $feedModel = Mage::getModel('pureclarity_core/feed');
                $feedData = $feedModel->getFullCatFeed($progressFileName, $storeId);
                $feedName = 'category';
                break;
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:
                $feedModel = Mage::getModel('pureclarity_core/feed');
                $feedData = $feedModel->getFullBrandFeed($progressFileName, $storeId);
                $feedName = 'brand';
                break;
            default:
                throw new \Exception("Pureclarity feed type not recognised: $feedtype");
        }

        // Format the feed in JSON format and save
        $json = Mage::helper('pureclarity_core')->formatFeed($feedData, 'json');
        fwrite($feedFile, $json);
        fclose($feedFile);

        // Ensure progress file is set to complete
        $this->updateProgressFile($progressFileName, $feedName, 1, 1, 'true');

        // Notify PC about the feed being available
        $url = Mage::helper('pureclarity_core')->getFeedNotificationEndpoint($storeId, $this->getStoreUrlNoTrailingSlash(), $feedtype);
        $useSSL = Mage::helper('pureclarity_core')->useSSL($storeId);
        $body = Mage::helper('pureclarity_core')->getFeedBody($storeId);
        $response = $this->soapHelper->request($url, $useSSL, $body);
    }


    // Process the product feed and update the progress file, in page sizes of 20 (or other if overriden)
    protected function processProductFeed($productExportModel, $progressFileName, $pageSize = 20){
        $currentPage = 1;
        $pages = 0;
        $feedProducts = array();
        $this->updateProgressFile($progressFileName, 'product', 0, 1, "false");
        do {
            $result = $productExportModel->getFullProductFeed($pageSize, $currentPage);
            $pages = $result["Pages"];
            $feedProducts = array_merge($feedProducts,$result["Products"]);
            $this->updateProgressFile($progressFileName, 'product', $currentPage, $pages, "false");
            $currentPage++;
        } while ($currentPage <= $pages);
        $this->updateProgressFile($progressFileName, 'product', $currentPage, $pages, "true");
        return  array(
            "Products" => $feedProducts,
            "Pages" => $pages
        );
    }

    // Helper function to update the progress file
    protected function updateProgressFile($progressFileName, $feedName, $currentPage, $pages, $isComplete)
    {
        $progressFile = fopen($progressFileName, "w");
        fwrite($progressFile, "{\"name\":\"$feedName\",\"cur\":$currentPage,\"max\":$pages,\"isComplete\":$isComplete}" );
        fclose($progressFile);
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


    // Helper functions
    private function getStoreUrlNoTrailingSlash(){
        $storeUrl = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_URL, 1);
        if(substr($storeUrl, -1, strlen($storeUrl)) == '/') {
            $storeUrl = substr($storeUrl, 0, strlen($storeUrl)-1);
        }
        return $storeUrl;
    }

    
}
