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
class Pureclarity_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    // ENDPOINTS
    protected $scriptUrl = '//pcs.pureclarity.net';
    protected $regions = array(
        1 => "https://api-eu-w-1.pureclarity.net",
        2 => "https://api-eu-w-2.pureclarity.net",
        3 => "https://api-eu-c-1.pureclarity.net",
        4 => "https://api-us-e-1.pureclarity.net",
        5 => "https://api-us-e-2.pureclarity.net",
        6 => "https://api-us-w-1.pureclarity.net",
        7 => "https://api-us-w-2.pureclarity.net",
        8 => "https://api-ap-s-1.pureclarity.net",
        9 => "https://api-ap-ne-1.pureclarity.net",
        10 => "https://api-ap-ne-2.pureclarity.net",
        11 => "https://api-ap-se-1.pureclarity.net",
        12 => "https://api-ap-se-2.pureclarity.net",
        13 => "https://api-ca-c-1.pureclarity.net",
        14 => "https://api-sa-e-1.pureclarity.net"
    );

    protected $sftpRegions = array(
        1 => "https://sftp-eu-w-1.pureclarity.net",
        2 => "https://sftp-eu-w-2.pureclarity.net",
        3 => "https://sftp-eu-c-1.pureclarity.net",
        4 => "https://sftp-us-e-1.pureclarity.net",
        5 => "https://sftp-us-e-2.pureclarity.net",
        6 => "https://sftp-us-w-1.pureclarity.net",
        7 => "https://sftp-us-w-2.pureclarity.net",
        8 => "https://sftp-ap-s-1.pureclarity.net",
        9 => "https://sftp-ap-ne-1.pureclarity.net",
        10 => "https://sftp-ap-ne-2.pureclarity.net",
        11 => "https://sftp-ap-se-1.pureclarity.net",
        12 => "https://sftp-ap-se-2.pureclarity.net",
        13 => "https://sftp-ca-c-1.pureclarity.net",
        14 => "https://sftp-sa-e-1.pureclarity.net"
    );

    const PLACEHOLDER_UPLOAD_DIR = "pureclarity";
    const PROGRESS_FILE_BASE_NAME = 'pureclarity-feed-progress-';
    const PURECLARITY_EXPORT_URL = 'pureclarity/export/feed?storeid={storeid}&type={type}';

    // Environment Variables
    public function isActive($storeId)
    {
        $accessKey = $this->getAccessKey($storeId);
        if (! empty($accessKey)) {
            return Mage::getStoreConfig("pureclarity_core/environment/active", $storeId);
        }

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
    public function isMerchActive($storeId = null)
    {
        // if ($this->isActive($this->getStoreId($storeId))) {
        //     return Mage::getStoreConfig("pureclarity_core/general_config/merch_active", $this->getStoreId($storeId));
        // }

        // return false;
        return true;
    }

    public function isSearchActive($storeId = null)
    {
        // if ($this->isActive($this->getStoreId($storeId))) {
        //     return Mage::getStoreConfig("pureclarity_core/general_config/search_active", $this->getStoreId($storeId));
        // }

        return false;
    }

    public function isProdListingActive($storeId = null)
    {
        if ($this->isActive($this->getStoreId($storeId))) {
            return Mage::getStoreConfig("pureclarity_core/general_config/prodlisting_active", $this->getStoreId($storeId));
        }

        return false;
    }

    public function isFeedNotificationActive($storeId)
    {
        if ($this->isActive($storeId)) {
            return Mage::getStoreConfig("pureclarity_core/general_config/notify_feed", $storeId);
        }

        return false;
    }

    public function isDeltaNotificationActive($storeId)
    {
        if ($this->isActive($storeId)) {
            return Mage::getStoreConfig("pureclarity_core/general_config/delta_feed", $storeId);
        }

        return false;
    }

    public function isBrandFeedEnabled($storeId)
    {
        if ($this->isActive($storeId)) {
            return Mage::getStoreConfig("pureclarity_core/general_config/brand_feed_enabled", $storeId);
        }

        return false;
    }

    public function getBrandParentCategory($storeId)
    {
        return Mage::getStoreConfig("pureclarity_core/general_config/brand_parent_category", $storeId);
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
    public function getHost($storeId)
    {
        $pureclarityHostEnv = getenv('PURECLARITY_MAGENTO_HOST');
        if ($pureclarityHostEnv != null && $pureclarityHostEnv != '')
            return $pureclarityHostEnv;
        $region = $this->getRegion($storeId);
        return $this->regions[$region];
    }

    public function getSftpHost($storeId)
    {
        $pureclarityHostEnv = getenv('PURECLARITY_SFTP_HOST');
        if ($pureclarityHostEnv != null && $pureclarityHostEnv != '')
            return $pureclarityHostEnv;
        $region = $this->getRegion($storeId);
        return $this->sftpRegions[$region];
    }

    public function getSftpPort($storeId)
    {
        $pureclarityHostEnv = getenv('PURECLARITY_SFTP_PORT');
        if ($pureclarityHostEnv != null && $pureclarityHostEnv != '')
            return intval($pureclarityHostEnv);
        return 2222;
    }

    public function useSSL($storeId)
    {
        $pureclarityHostEnv = getenv('PURECLARITY_MAGENTO_USESSL');
        if ($pureclarityHostEnv != null && strtolower($pureclarityHostEnv) == 'false')
            return false;
        return true;
    }

    public function getDeltaEndpoint($storeid)
    {
        return $this->getHost($storeId) . '/api/productdelta';
    }

    public function getMotoEndpoint($storeid)
    {
        return $this->getHost($storeId) . '/api/track?appid=' . $this->getAccessKey($storeId) . '&evt=moto_order_track';
    }

    public function getFeedBaseUrl($storeId)
    {
        $url = getenv('PURECLARITY_FEED_HOST');
        $port = getenv('PURECLARITY_FEED_PORT');
        if (empty($url)) {
            $url = $this->sftpRegions[$this->getRegion($storeId)];
        }

        if (! empty($port)) {
            $url = $url . ":" . $port;
        }

        return $url . "/";
    }

    public function getFeedNotificationEndpoint($storeId, $websiteDomain, $feedType)
    {
        $returnUrl = $websiteDomain . '/' . self::PURECLARITY_EXPORT_URL;
        $returnUrl = str_replace('{storeid}', $storeId, $returnUrl);
        $returnUrl = str_replace('{type}', $feedType, $returnUrl);
        return $this->getHost($storeId) . '/api/productfeed?appkey=' . $this->getAccessKey($storeId) . '&url='. urlencode($returnUrl) . '&feedtype=magentoplugin1.0.0';
    }

    public function getFeedBody($storeId)
    {
        $body = array("AccessKey" => $this->getAccessKey($storeId), "SecretKey" => $this->getSecretKey($storeId));
        return Mage::helper('pureclarity_core')->formatFeed($body);
    }

    public static function getFileNameForFeed($feedtype, $storeCode)
    {
        return $storeCode . '-' . $feedType . '.json';
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

    public function getPlaceholderDir()
    {
        return Mage::getBaseDir('media') . DS . self::PLACEHOLDER_UPLOAD_DIR . DS;
    }
    public function getPlaceholderUrl()
    {
        //return Mage::getBaseUrl('media', array('_secure'=>true)) . '/' . self::PLACEHOLDER_UPLOAD_DIR . '/';
        return '/media/' . self::PLACEHOLDER_UPLOAD_DIR . '/';
    }

    public function getOrderObject()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        return $order;
    }

    
    public static function getPureClarityBaseDir()
    {
        $varDir = Mage::getBaseDir('var') . DS . 'pureclarity';
        $fileIo = new Varien_Io_File();
        $fileIo->mkdir($varDir);
        return $varDir;
    }

    public static function getProgressFileName()
    {
        return self::getPureClarityBaseDir() . DS . self::PROGRESS_FILE_BASE_NAME . 'all.json';
    }

    public static function setProgressFile($progressFileName, $feedName, $currentPage, $pages, $isComplete = "false", $isUploaded = "false", $error = "")
    {
        if ($progressFileName != null) {
            $progressFile = fopen($progressFileName, "w");
            fwrite($progressFile, "{\"name\":\"{$feedName}\",\"cur\":{$currentPage},\"max\":{$pages},\"isComplete\":{$isComplete},\"isUploaded\":{$isUploaded},\"error\":\"{$error}\"}");
            fclose($progressFile);
        }
    }

    public function getOrder()
    {
        $order = $this->getOrderObject();

        if (!$order) {
            throw new \Exception("Pureclarity: unable to get order");
        }

        $address = $order->getShippingAddress();

        if (!$address) {
            throw new \Exception("Pureclarity: unable to get order address");
        }

        return array(
            'orderid'    => $order->getIncrementId(),
            'firstname'  => $order->getCustomerFirstname(),
            'lastname'   => $order->getCustomerLastname(),
            'postcode'   => $address->getPostcode(),
            'email'      => $order->getCustomerEmail(),
            'userid'     => $order->getCustomerId(),
            'ordertotal' => $order->getGrandTotal()
        );
    }

    public function getOrderItems($order = null)
    {

        if (!$order)
            $order = $this->getOrderObject();

        if (!$order) {
            throw new \Exception("Pureclarity: unable to get order");
        }

        $items = array();
        $orderInformation = array();
        $orderItems = $order->getAllItems();

        // process parents
        foreach ($orderItems as $item) {
            if (!$item->getParentItemId()) {
                $items[$item->getId()] = array(
                    'productId' => $item->getProductId(),
                    'qty' => $item->getQtyOrdered(),
                    'unitPrice' => $item->getPrice()
                );
            }
        }

        // Process child products
        foreach ($orderItems as $item) {
            $parentId = $item->getParentItemId();
            if ($parentId != null && $items[$parentId] != null) {
                $items[$parentId]['associatedproducts'][] = array(
                        'sku' => $item->getProduct()->getSku(),
                        'qty' => $item->getQtyOrdered()
                );
            }
        }

        // Build output information
        foreach ($items as $itemId => $item) {
            $orderObject = array(
                'orderid'   => $order->getIncrementId(),
                'refid'     => $itemId,
                'id'        => $item['productId'],
                'qty'       => $item['qty'],
                'unitprice' => $item['unitPrice'],
                'children'  => array()
            );
            
            if ($item['associatedproducts']) {
                $orderObject['children'] = $item['associatedproducts'];
            }

            $orderInformation[] = $orderObject;
        }

        return $orderInformation;
    }

    public function formatFeed($feed, $feedFormat = 'json')
    {
        switch ($feedFormat) {
            case 'json':
                return json_encode($feed);
            case 'jsonpretty':
                return json_encode($feed, JSON_PRETTY_PRINT);
        }
    }

}
