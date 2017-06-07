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
class Pureclarity_Core_Helper_Data extends Mage_Core_Helper_Abstract {


    // ENDPOINTS
    protected $scriptUrl = '//pcs.pureclarity.net';
    protected $regions = array(1 => 'api.pureclarity.net',         
                               2 => 'api-us-e.pureclarity.net',
                               3 => 'api-us-w.pureclarity.net',
                               4 => 'api-ap-s.pureclarity.net',
                               5 => 'api-ap-ne.pureclarity.net',
                               6 => 'api-ap-se.pureclarity.net',
                               7 => 'api-ap-se2.pureclarity.net',
                               8 => 'api-ap-ne2.pureclarity.net',
                               9 => 'api-eu-c.pureclarity.net',
                               10 => 'api-eu-w.pureclarity.net');

    const FEED_TYPE_PRODUCT  = 'product';
    const FEED_TYPE_CATEGORY = 'category';
    const FEED_TYPE_BRAND    = 'brand';
    const PROGRESS_FILE_BASE_NAME = 'pureclarity_feed_progress';
    const PURECLARITY_EXPORT_URL = 'pureclarity/export/feed?storeid={storeid}&type={type}';


    // Environment Variables
    public function isActive($storeId)
    {
        $accessKey = $this->getAccessKey($storeId);
        if ($accessKey != null && $accessKey != "")
            return Mage::getStoreConfig("pureclarity_core/environment/active", $storeId);
        return false;
    }

    public function getAdminUrl()
    {
        return "https://admin.pureclarity.net";
    }

    // Credentials
    public function getAccessKey($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/credentials/access_key", $storeId);
    }

    public function getSecretKey($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/credentials/secret_key", $storeId);
    }

    public function getRegion($storeId)
    {
        $region = Mage::getStoreConfig("pureclarity_core/credentials/region", $storeId);
        if ($region == null)
            $region = 1;
        return $region;
    }

    
    // General Config 
    public function isSearchActive($storeId = null)
    {
        if ($this->isActive($this->getStoreId($storeId))){
            return Mage::getStoreConfig("pureclarity_core/general_config/search_active", $this->getStoreId($storeId));
        }
        return false;
    }

    public function isMerchActive($storeId = null)
    {
        if ($this->isActive($this->getStoreId($storeId))){
            return Mage::getStoreConfig("pureclarity_core/general_config/merch_active", $this->getStoreId($storeId));
        }
        return false;
    }

    public function isFeedNotificationActive($storeId)
    {
        if ($this->isActive($storeId)){
            return Mage::getStoreConfig("pureclarity_core/general_config/notify_feed", $storeId);
        }
        return false;
    }

    public function isDeltaNotificationActive($storeId)
    {
        if ($this->isActive($storeId)){
            return Mage::getStoreConfig("pureclarity_core/general_config/delta_feed", $storeId);
        }
        return false;
    }

    public function isBrandFeedEnabled($storeId)
    {
        if ($this->isActive($storeId)){
            return Mage::getStoreConfig("pureclarity_core/general_config/brand_feed_enabled", $storeId);
        }
        return false;
    }

    public function getBrandAttributeCode($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/general_config/brand_attribute_code", $storeId);
    }


    // Placeholders
    public function getProductPlaceholderUrl($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_product", $storeId);
    }

    public function getCategoryPlaceholderUrl($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_category", $storeId);
    }

    public function getSecondaryCategoryPlaceholderUrl($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_category_secondary", $storeId);
    }

    public function getBrandPlaceholderUrl($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_brand", $storeId);
    }


    // ADVANCED
    public function isBMZDebugActive($storeId = null)
    {
        return Mage::getStoreConfig("pureclarity_core/advanced/bmz_debug", $this->getStoreId($storeId));
    }



    // END POINTS
    public function getHost($storeId){
        $pureclarityHostEnv = getenv('PURECLARITY_MAGENTO_HOST');
        if ($pureclarityHostEnv != null && $pureclarityHostEnv != '')
            return $pureclarityHostEnv;
        $region = $this->getRegion($storeId);
        return $this->regions[$region];
    }

    public function useSSL($storeId){
        $pureclarityHostEnv = getenv('PURECLARITY_MAGENTO_USESSL');
        if ($pureclarityHostEnv != null && strtolower($pureclarityHostEnv) == 'false')
            return false;
        return true;
    }

    public function getDeltaEndpoint($storeid){
        return $this->getHost($storeId) . '/api/productdelta';
    }

    public function getMotoEndpoint($storeid){
        return $this->getHost($storeId) . '/api/track/appid=' . $this->getAccessKey($storeId) . '&evt=moto_order_track';
    }

    public function getFeedNotificationEndpoint($storeId, $websiteDomain, $feedType){
        $returnUrl = $websiteDomain . '/' . self::PURECLARITY_EXPORT_URL;
        $returnUrl = str_replace('{storeid}', $storeId, $returnUrl);
        $returnUrl = str_replace('{type}', $feedType, $returnUrl);
        return $this->getHost($storeId) . '/api/productfeed?appkey=' . $this->getAccessKey($storeId) . '&url='. urlencode($returnUrl) . '&feedtype=magentoplugin1.0.0';
    }

    public function getFeedBody($storeId){
        $body = array("AccessKey" => $this->getAccessKey($storeId), "SecretKey" => $this->getSecretKey($storeId));
        return Mage::helper('pureclarity_core')->formatFeed($body);
    }

    public static function getFileNameForFeed($feedtype, $storeCode){
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  return $storeCode . '-product.json';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: return $storeCode . '-category.json';
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    return $storeCode . '-brand.json';
        }
        return null;
    }



    // MISC/HELPER METHODS
    public function getScriptUrl()
    {
        return $this->scriptUrl;
    }

    public function getApiStartUrl()
    {
        $pureclarityScriptUrl = getenv('PURECLARITY_SCRIPT_URL');
        if ($pureclarityScriptUrl != null && $pureclarityScriptUrl != '')
            return $pureclarityScriptUrl;
        return $this->getScriptUrl() . '/' . $this->getAccessKey($this->getStoreId()) . '/cs.js';
    }

    public function getStoreId($storeId = null)
    {
        if (is_null($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        return $storeId;
    }

    public function getOrderObject()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        return $order;
    }

    public static function feedName($feedtype){
        // Get the string that identifies this feed
        switch($feedtype){
            case Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT:  return "product";
            case Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY: return "category";
            case Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND:    return "brand";
            default:
                throw new \Exception("Pureclarity feed type not recognised: $feedtype");
        }
    }
    
    public static function getPureClarityBaseDir(){
        $varDir = Mage::getBaseDir('var') . DS . 'pureclarity';
        $fileIo = new Varien_Io_File();
        $fileIo->mkdir($varDir);
        return $varDir;
    }

    public static function getProgressFileName($feedtype){
        return self::getPureClarityBaseDir() . DS . self::PROGRESS_FILE_BASE_NAME . self::feedName($feedtype);
    }


    public function getOrder()
    {
        $order = $this->getOrderObject();

        if (!$order){
            throw new \Exception("Pureclarity: unable to get order");
        }

        $address = $order->getShippingAddress();

        if (!$address){
            throw new \Exception("Pureclarity: unable to get order address");
        }

        return array(
            'orderId'           => $order->getIncrementId(),
            'firstName'         => $order->getCustomerFirstname(),
            'lastName'          => $order->getCustomerLastname(),
            'postCode'          => $address->getPostcode(),
            'email'             => $order->getCustomerEmail(),
            'orderTotal'        => $order->getGrandTotal()
        );
    }

    public function getOrderItems()
    {
        $orderInformation = array();

        $order = $this->getOrderObject();
        if (!$order){
            throw new \Exception("Pureclarity: unable to get order");
        }
        $orderItems = $order->getAllVisibleItems();
        foreach($orderItems as $orderItem) {

            /** @var Mage_Sales_Model_Order_Item $orderItem */
            $orderInformation[] = array(
                'orderId'       => $order->getIncrementId(),
                'sku'           => $orderItem->getSku(),
                'qty'           => $orderItem->getQtyOrdered(),
                'unitPrice'     => $orderItem->getPrice()
            );
        }

        return $orderInformation;
    }

    public function formatFeed($feed, $feedFormat = 'json')
    {
        switch ($feedFormat) {
            case 'json':
                return json_encode($feed);
                break;
            case 'jsonpretty':
                return json_encode($feed, JSON_PRETTY_PRINT);
                break;
        }
    }

}
