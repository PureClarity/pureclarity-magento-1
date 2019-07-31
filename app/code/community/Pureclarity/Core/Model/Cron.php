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
 * Controls running of PureClarity feeds
 */
class Pureclarity_Core_Model_Cron extends Pureclarity_Core_Model_Model
{
    protected $soapHelper;

    const DELTA_LOG = 'pureclarity_delta.log';

    public function __construct()
    {
        $this->soapHelper = Mage::helper('pureclarity_core/soap');
        parent::__construct();
    }

    public function runAllFeeds()
    {
        Mage::log("In runAllFeeds");
        // Loop round each store and create feed
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    // Only generate feeds when feed notification is active
                    if ($this->coreHelper->isFeedNotificationActive($store->getId())) {
                        $this->allFeeds($store->getId());
                    }
                }
            }
        }
    }

    /**
     * Process Delta Feeds and push to PureClarity
     */
    public function deltaFeed($observerObject, $peakModeOnly = false)
    {

        Mage::log("In deltaFeed");

        // create a unique token until we get a response from PureClarity
        $uniqueId = 'PureClarity' . uniqid();
        $requests = array();
        
        // Loop round each store and process Deltas
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                foreach ($group->getStores() as $store) {
                    Mage::log("Processing for store id " . $store->getId());

                    // Check we're allowed to do it for this store
                    if ($this->coreHelper->isDeltaNotificationActive($store->getId()) || $peakModeOnly) {
                        $deleteProducts = array();
                        $feedProducts = array();

                        Mage::log("Getting delta feed collection for store");
                        // get deltaFeed Collection for store
                        $storeIds = array(
                            'in' => array(
                                Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID, 
                                $store->getId()
                            )
                        );
                        $deltaProducts = Mage::getModel('pureclarity_core/productFeed')
                            ->getCollection()
                            ->addFieldToFilter(
                                'status_id', array(
                                    'eq' => 0
                                )
                            )
                            ->addFieldToFilter('store_id', $storeIds);

                        Mage::log("We have " . $deltaProducts->count() . " products to process");

                        // Check we have something
                        if ($deltaProducts->count() > 0) {
                            $deltaIds = array();

                            // Park these so that another process doesn't pick them up, also
                            // create a hash to get last value (in case product has been edited multiple times)
                            $productHash = array();
                            foreach ($deltaProducts as $deltaProduct) {
                                $deltaIds[] = $deltaProduct->getProductId();
                                if (! $peakModeOnly) {
                                    $deltaProduct->setStatusId(3)->setToken($uniqueId)->save();
                                }

                                $productHash[$deltaProduct->getProductId() . '-' . $deltaProduct->getStoreId()] = $deltaProduct;
                            }

                            $productExportModel = Mage::getModel('pureclarity_core/productExport');
                            $productExportModel->init($store->getId());
                            // load products
                            foreach ($productHash as $deltaProduct) {
                                // Get product for this store
                                $product = Mage::getModel('catalog/product')
                                    ->setStoreId($store->getId())
                                    ->load($deltaProduct->getProductId());

                                // Is product deleted, or otherwise no longer visible?
                                $isDeleted = (
                                        $product->getData('status') == Mage_Catalog_Model_Product_Status::STATUS_DISABLED 
                                        || $product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
                                    );
                                    
                                if ($this->coreHelper->excludeOutOfStockFromProductFeed($store->getId())) {
                                    $stockItem = $product->getStockItem();
                                    if ($stockItem && !$stockItem->getIsInStock()) {
                                        $isDeleted = true;
                                    }
                                }

                                // Check product is loaded
                                if ($product != null) {
                                    // Check if deleted or if product is no longer visible
                                    if ($isDeleted) {
                                        $deleteProducts[] = $product->getSku();
                                    } else {
                                        // Get data from product exporter
                                        $data = $productExportModel->getProductData($product, count($feedProducts) + 1);
                                        if ($data != null) {
                                            $feedProducts[] = $data;
                                        } else {
                                            // product is either excluded via category / or not a valid product
                                            // so we should send a delete to ensure it is not in PC data
                                            $deleteProducts[] = $product->getSku();
                                        }
                                    }

                                    //if we've changed the sku - make sure old one gets deleted
                                    if ($deltaProduct->getOldsku() != $product->getSku()) {
                                        $deleteProducts[] = $deltaProduct->getOldsku();
                                    }

                                    //find all products where this is a variant, and call getProductData() on them
                                    Mage::log("About to look for parent product ids");
                                    Mage::log("Type id=" . $product->getTypeId());
                                    $parentProductIds = Mage::getResourceSingleton('catalog/product_type_configurable')
                                        ->getParentIdsByChild($product->getId());
                                    Mage::log("Product id " . $product->getId() . " has " . count($parentProductIds) . " parent products");
                                    foreach ($parentProductIds as $parentProductId) {
                                        $parentProduct = Mage::getModel('catalog/product')
                                            ->setStoreId($store->getId())
                                            ->load($parentProductId);
                                        if ($parentProduct != null) {
                                            Mage::log("Parent product: got product for id " . $parentProduct->getId());
                                            $data = $productExportModel->getProductData($parentProduct, count($feedProducts) + 1);
                                            if ($data != null) {
                                                $feedProducts[] = $data;
                                            }
                                        }
                                    }
                                }   
                            }

                            $request = array(
                                'AppKey' => $this->coreHelper->getAccessKey($store->getId()),
                                'Secret' => $this->coreHelper->getSecretKey($store->getId()),
                                'Products' => $feedProducts,
                                'DeleteProducts' => $deleteProducts,
                                'Format' => 'magentoplugin1.0.0',
                            );
                            Mage::log("About to send request: " . print_r($request, true));
                            $requests[] = $request;

                            if (! $peakModeOnly) {
                                $body = $this->coreHelper->formatFeed($request, 'json');

                                $url = $this->coreHelper->getDeltaEndpoint($store->getId());
                                $useSSL = $this->coreHelper->useSSL($store->getId());

                                $jsonResponse = $this->soapHelper->request($url, $useSSL, $body);
                                Mage::log("Response:\n" . $jsonResponse);
                                $response = json_decode($jsonResponse);
                                if (! is_object($response)) {
                                    Mage::log('DELTA Issue from PC - ' . var_export($deltaIds, true), null, self::DELTA_LOG);
                                }

                                foreach ($deltaProducts as $deltaProduct) {
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

    public function selectedFeeds($storeId, $feeds)
    {
        $this->doFeed($feeds, $storeId);
    }
    
    /**
     * Sets selected feeds to be run by cron asap
     *
     * @param integer $storeId
     * @param string[] $feeds
     */
    public function scheduleSelectedFeeds($storeId, $feeds)
    {
        $pcDir = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS ;
        $scheduleFilePath = $pcDir . 'scheduled_feed';
        
        $schedule = array(
            'store' => $storeId,
            'feeds' => $feeds
        );
        
        $fileHandler = new Varien_Io_File();
        
        $fileHandler->open(array('path' => $pcDir)); 
        if (!$fileHandler->write($scheduleFilePath, json_encode($schedule))) {
            Mage::throwException(
                'Error: Cannot open feed file for writing under var/pureclarity directory. ' 
                . 'It could be locked or there maybe insufficient permissions to write to the directory. ' 
                . 'You must delete locked files and ensure PureClarity has permission to write to the var directory. ' 
                . 'File: ' . $scheduleFilePath
            );
        }
    }

    /**
     * Produce a feed and POST to PureClarity.
     * @param $feedTypes array
     * @param $storeId integer
     */ 
    public function doFeed($feedTypes, $storeId)
    {
        Mage::log("PureClarity: In Cron->doFeed()");
        $this->coreHelper->setProgressFile($this->progressFileName, 'N/A', 0, 0);

        //can take a while to run the feed
        set_time_limit(0);

        $feedModel = Mage::getModel('pureclarity_core/feed')
            ->initialise($storeId);
        if(! $feedModel){
            return false;
        }

        foreach ($feedTypes as $feedType) {
            Mage::log("PureClarity: In Cron->doFeed(): " .  $feedType);

            switch ($feedType) {
                case Pureclarity_Core_Model_Feed::FEED_TYPE_PRODUCT:
                    $feedModel->sendProducts();
                    break;
                case Pureclarity_Core_Model_Feed::FEED_TYPE_CATEGORY:
                    $feedModel->sendCategories();
                    break;
                case Pureclarity_Core_Model_Feed::FEED_TYPE_BRAND:
                    if ($this->coreHelper->isBrandFeedEnabled($storeId)) {
                        $feedModel->sendBrands();
                    } 
                    break;
                case Pureclarity_Core_Model_Feed::FEED_TYPE_USER:
                    $feedModel->sendUsers();
                    break;
                case Pureclarity_Core_Model_Feed::FEED_TYPE_ORDER:
                    $feedModel->sendOrders();
                    break;
                default:
                    throw new Exception("PureClarity feed type not recognised: {$feedType}");
            }
        }

        Mage::log("PureClarity: In Cron->doFeed(): about to call checkSuccess()");
        $feedModel->checkSuccess();
    }

    // Get and open file for the feed
    protected function getFeedFile($feedFilePath)
    {
        $feedFile = @fopen($feedFilePath, "w+");
        if ((!$feedFile) || !flock($feedFile, LOCK_EX | LOCK_NB)) {
            throw new Exception("Error: Cannot open feed file for writing under var/pureclarity directory. It could be locked or there maybe insufficient permissions to write to the directory. You must delete locked files and ensure PureClarity has permission to write to the var directory. File: " . $feedFilePath);
        }

        return $feedFile;
    }

    private function getFeedFilePath($storeId)
    {
        return Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . $storeId . '-feed.json';
    }

    private function getOrderFilePath($storeId)
    {
        return Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . $storeId . '-orders.csv';
    }

    // Produce all feeds in one file.
    public function allFeeds($storeId)
    {
        $this->doFeed(
            array(
            Pureclarity_Core_Model_Feed::FEED_TYPE_PRODUCT, 
            Pureclarity_Core_Model_Feed::FEED_TYPE_CATEGORY, 
            Pureclarity_Core_Model_Feed::FEED_TYPE_BRAND, 
            Pureclarity_Core_Model_Feed::FEED_TYPE_USER
            ), $storeId
        );
    }

    // Produce a product feed and notify PureClarity so that it can fetch it.
    public function fullProductFeed($storeId)
    {
        $this->doFeed(
            array(
                Pureclarity_Core_Model_Feed::FEED_TYPE_PRODUCT
            ), $storeId
        );
    }

    // Produce a category feed and notify PureClarity so that it can fetch it.
    public function fullCategoryFeed($storeId)
    {
        $this->doFeed(
            array(
                Pureclarity_Core_Model_Feed::FEED_TYPE_CATEGORY
            ), $storeId
        );
    }

    // Produce a brand feed and notify PureClarity so that it can fetch it.
    public function fullBrandFeed($storeId)
    {
        $this->doFeed(
            array(
                Pureclarity_Core_Model_Feed::FEED_TYPE_BRAND
            ), $storeId
        );
    }

    // Helper functions
    private function getStoreUrlNoTrailingSlash()
    {
        $storeUrl = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_URL, 1);
        if (substr($storeUrl, -1, strlen($storeUrl)) == '/') {
            $storeUrl = substr($storeUrl, 0, strlen($storeUrl) - 1);
        }

        return $storeUrl;
    }

}
