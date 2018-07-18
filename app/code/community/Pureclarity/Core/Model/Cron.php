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
    protected $sftpHelper;

    public function _construct()
    {
        $this->soapHelper = Mage::helper('pureclarity_core/soap');
        $this->sftpHelper = Mage::helper('pureclarity_core/sftp');
    }

    /**
     * Process Delta Feeds and push to PureClarity
     */
    public function deltaFeed($observerObject, $peakModeOnly = false)
    {
        // create a unique token until we get a response from PureClarity
        $uniqueId = 'PureClarity' . uniqid();
        $requests = array();

        // Loop round each store and process Deltas
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                foreach ($group->getStores() as $store) {

                    // Check we're allowed to do it for this store
                    if (Mage::helper('pureclarity_core')->isDeltaNotificationActive($store->getId()) || $peakModeOnly) {

                        $deleteProducts = $feedProducts = array();

                        // get deltaFeed Collection for store
                        $storeIds = array('in' => array(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID, $store->getId()));
                        $collection = Mage::getModel('pureclarity_core/productFeed')
                            ->getCollection()
                            ->addFieldToFilter('status_id', array('eq' => 0))
                            ->addFieldToFilter('store_id', $storeIds);

                        // Check we have something
                        if ($collection->count() > 0) {

                            $deltaIds = array();

                            // park these so that another process doesn't pick them up, also
                            // create a hash to get last value (in case product been edited multiple times)
                            $productHash = array();
                            foreach ($collection as $deltaProduct) {
                                $deltaIds[] = $deltaProduct->getProductId();
                                if (!$peakModeOnly) {
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

                                // Is deleted?
                                $deleted = $product->getData('status') == Mage_Catalog_Model_Product_Status::STATUS_DISABLED ||
                                $product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;

                                // Check product is loaded
                                if ($product != null) {
                                    // Check if deleted or if product is no longer visible
                                    if ($deleted == true) {
                                        $deleteProducts[] = $product->getSku();
                                    } else {
                                        // Get data from product exporter
                                        $data = $productExportModel->processProduct($product, count($feedProducts) + 1);
                                        if ($data != null) {
                                            $feedProducts[] = $data;
                                        }

                                    }
                                    //if we've changed the sku - make sure old one gets deleted
                                    if ($deltaProduct->getOldsku() != $product->getSku()) {
                                        $deleteProducts[] = $deltaProduct->getOldsku();
                                    }
                                }
                            }

                            $request = array(
                                'AppKey' => Mage::helper('pureclarity_core')->getAccessKey($store->getId()),
                                'Secret' => Mage::helper('pureclarity_core')->getSecretKey($store->getId()),
                                'Products' => $feedProducts,
                                'DeleteProducts' => $deleteProducts,
                                'Format' => 'magentoplugin1.0.0',
                            );
                            $requests[] = $request;

                            if (!$peakModeOnly) {
                                $body = Mage::helper('pureclarity_core')->formatFeed($request, 'json');

                                $url = Mage::helper('pureclarity_core')->getDeltaEndpoint($store->getId());
                                $useSSL = Mage::helper('pureclarity_core')->useSSL($store->getId());

                                $response = $this->soapHelper->request($url, $useSSL, $body);
                                $response = json_decode($response);
                                if (!is_object($response)) {
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

    public function selectedFeeds($storeId, $feeds)
    {
        $this->doFeed($feeds, $storeId);
    }

    // Produce a feed and notify PureClarity so that it can fetch it.
    public function doFeed($feedtypes, $storeId)
    {
        //can take a while to run the feed
        set_time_limit(0);
        $feedFilePath = $this->getFeedFilePath($storeId);
        $feedFile = $this->getFeedFile($feedFilePath);
        // Feed Start
        fwrite($feedFile, '{ "Version": 2,');

        foreach ($feedtypes as $feedtype) {
            $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName();

            // Initialise Progress File.
            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, $feedtype, 0, 1);

            // Get the feed data for the specified feed type
            switch ($feedtype) {
                case 'product':
                    $productExportModel = Mage::getModel('pureclarity_core/productExport');
                    $productExportModel->init($storeId);
                    $feedModel = Mage::getModel('pureclarity_core/feed');
                    $feedModel->processProductFeed($productExportModel, $progressFileName, $feedFile);
                    break;
                case 'category':
                    $feedModel = Mage::getModel('pureclarity_core/feed');
                    $feedData = $feedModel->getFullCatFeed($progressFileName, $storeId);
                    fwrite($feedFile, $feedData);
                    break;
                case 'brand':
                    if (!Mage::helper('pureclarity_core')->isBrandFeedEnabled($storeId)) {
                        $feedModel = Mage::getModel('pureclarity_core/feed');
                        $feedData = $feedModel->getFullBrandFeed($progressFileName, $storeId);
                        fwrite($feedFile, $feedData);
                    }
                    break;
                case 'user':
                    fwrite($feedFile, '"Users":[]');
                    break;
                default:
                    throw new \Exception("Pureclarity feed type not recognised: $feedtype");
            }

            if (end($feedtypes) !== $feedtype) {
                fwrite($feedFile, ',');
            }

        }

        fwrite($feedFile, '}');
        fclose($feedFile);

        // Ensure progress file is set to complete
        $uniqueId = 'PureClarityFeed-' . uniqid();
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, $feedtype, 1, 1, "true", "false");

        Mage::log('uploading to SFTP');
        // Uploade to sftp
        $host = Mage::helper('pureclarity_core')->getSftpHost($storeId);
        $port = Mage::helper('pureclarity_core')->getSftpPort($storeId);
        $appKey = Mage::helper('pureclarity_core')->getAccessKey($storeId);
        $secretKey = Mage::helper('pureclarity_core')->getSecretKey($storeId);
        $this->sftpHelper->send($host, $port, $appKey, $secretKey, $uniqueId, $feedFilePath);
        Mage::log('uploaded to SFTP');

        // Set to uploaded
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, $feedtype, 1, 1, "true", "true");
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
        return Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . 'feed.json';
    }

    // Product All feeds in one file.
    public function allFeeds($storeId)
    {
        $this->doFeed(array('product', 'category', 'brand', 'users'), $storeId);
    }

    // Produce a product feed and notify PureClarity so that it can fetch it.
    public function fullProductFeed($storeId)
    {
        $this->doFeed(array('product'), $storeId);
    }
    // Produce a category feed and notify PureClarity so that it can fetch it.
    public function fullCategoryFeed($storeId)
    {
        $this->doFeed(array('category'), $storeId);
    }
    // Produce a brand feed and notify PureClarity so that it can fetch it.
    public function fullBrandFeed($storeId)
    {
        $this->doFeed(array('brand'), $storeId);
    }

    public function runAllFeeds()
    {
        // Loop round each store and create feed
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    // Only generate feeds when feed notification is active
                    if (!Mage::helper('pureclarity_core')->isFeedNotificationActive($store->getId())) {
                        allFeeds($store->getId());
                    }
                }
            }
        }
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
