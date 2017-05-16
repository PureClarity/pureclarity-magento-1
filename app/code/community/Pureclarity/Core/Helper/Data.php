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

    // TODO For development, use the amazon link, otherwise use the pcs.pureclarity.net link
    protected $_apiAccessUrl = '//pcs.pureclarity.net';
    //protected $_apiAccessUrl = '//pc-tc.s3-eu-west-1.amazonaws.com';

    const PRODUCTION_VALUE  = 1;
    const STAGING_VALUE     = 0;

    const PRODUCTION_SCRIPT = 'cs';
    const STAGING_SCRIPT    = 'test-cs';

    const FEED_TYPE_PRODUCT  = 'product';
    const FEED_TYPE_CATEGORY = 'category';
    const FEED_TYPE_BRAND    = 'brand';

    const PROGRESS_FILE_BASE_NAME = 'pureclarity_feed_progress';

    public function isActive()
    {
        return Mage::getStoreConfig("pureclarity_core/environment/active");
    }

    public function isBMZDebugActive()
    {
        return Mage::getStoreConfig("pureclarity_core/advanced/bmz_debug");
    }

    public function isSearchActive()
    {
        return Mage::getStoreConfig("pureclarity_core/environment/search_active");
    }

    public function isFeedNotificationActive($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/environment/notify_feed", $storeId);
    }

    public function getAccessKey($storeId = null)
    {
        if (is_null(storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        return Mage::getStoreConfig("pureclarity_core/credentials/access_key", $storeId);
    }

    public function getBrandAttributeCode($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/brand_feed/attribute_code", $storeId);
    }

    public function isBrandFeedEnabled($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/brand_feed/enabled", $storeId);
    }

    public function getApiAccessUrl()
    {
        return $this->_apiAccessUrl;
    }

    public function getFullFeedProdUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/override_urls/full_feed_prod_url");
    }

    public function getFullFeedCatUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/override_urls/full_feed_cat_url");
    }

    public function getFullFeedBrandUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/override_urls/full_feed_brand_url");
    }

    public function getProductPlaceholderUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_product");
    }

    public function getCategoryPlaceholderUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_category");
    }

    public function getSecondaryCategoryPlaceholderUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_category_secondary");
    }

    public function getBrandPlaceholderUrl()
    {
        return Mage::getStoreConfig("pureclarity_core/placeholders/placeholder_brand");
    }

    public function getScriptFile()
    {
        if(Mage::getStoreConfig("pureclarity_core/environment/mode") == self::PRODUCTION_VALUE) {
            return self::PRODUCTION_SCRIPT;
        } else {
            return self::STAGING_SCRIPT;
        }
    }

    public function isProduction()
    {
        return Mage::getStoreConfig("pureclarity_core/environment/mode") == self::PRODUCTION_VALUE;
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

    public static function progressFileName($feedtype){
        // Get the path to the file that stores the progress for the specified feed.
        $varDir = Mage::getBaseDir('var');
        $feedName = self::feedName($feedtype);
        $fullPath = $varDir . DS . self::PROGRESS_FILE_BASE_NAME . $feedName;
        return $fullPath;
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
        $orderItems = $order->getAllItems();
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
