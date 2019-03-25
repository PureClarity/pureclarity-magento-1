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

class Pureclarity_Core_Helper_Soap
{
    protected $coreHelper;

    const LOG_FILE = "pureclarity_soap.log";

    public function __construct()
    {
        $this->coreHelper = Mage::helper('pureclarity_core');
    }

    public function request($url, $useSSL, $payload = null)
    {
        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $url);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT_MS, 5000);
        curl_setopt($soap_do, CURLOPT_TIMEOUT_MS, 10000);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $useSSL);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $useSSL);

        if ($payload != null){
            curl_setopt($soap_do, CURLOPT_POST, true);
            curl_setopt($soap_do, CURLOPT_POSTFIELDS, $payload);
            curl_setopt(
                $soap_do, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json', 
                    'Content-Length: ' . strlen($payload)
                )
            );
        }
        else {
            curl_setopt($soap_do, CURLOPT_POST, false);
        }

        curl_setopt($soap_do, CURLOPT_FAILONERROR, true);
        curl_setopt($soap_do, CURLOPT_VERBOSE, true);

        if (! $result = curl_exec($soap_do)) {
            Mage::log('ERROR: ' . curl_error($soap_do), null, self::LOG_FILE);
        }

        curl_close($soap_do);

        Mage::log("------------------ REQUEST ------------------", null, self::LOG_FILE);
        Mage::log(print_r($url, true), null, self::LOG_FILE);
        if ($payload != null){
            Mage::log(print_r($payload, true), null, self::LOG_FILE);
        }

        Mage::log("------------------ RESPONSE ------------------", null, self::LOG_FILE);
        Mage::log(print_r($result, true), null, self::LOG_FILE);
        Mage::log("------------------ END PRODUCT DELTA ------------------", null, self::LOG_FILE);

        return $result;
    }

    /**
     * Makes a GET request to PureClarity for MOTO Orders
     *
     * @param null $order
     * @param null $orderItems
     * @return mixed
     */
    public function motoOrderGetRequest($storeId, $order = null, $orderItems = null)
    {
        $additional = '';

        if ($order == null) {
            Mage::log("Order information has not been set.", null, self::LOG_FILE);
        }

        if ($orderItems == null) {
            Mage::log("Oorder items information has not been set.", null, self::LOG_FILE);
        }

        foreach($order as $key => $value) {
            $additional .= '&' . $key . '=' . $value;
        }

        $i = 1;

        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($orderItems as $item) {
            foreach($item as $key => $value){
                if ($key == 'children') { 
                    foreach ($value as $x => $child) {
                        foreach ($child as $childKey => $childValue) {
                            $additional .= '&' . $key . $i . '_' . $x . '_' . $childKey . '=' . $childValue;
                        }
                    }
                } else if ($key != 'orderid'){
                    $additional .= '&' . $key . $i . '=' . $value;
                }
            }

            $i++;
        }

        $url = $this->coreHelper->getMotoEndpoint($storeId);
        $useSSL = $this->coreHelper->useSSL($storeId);

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $url . $additional);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT_MS, 5000);
        curl_setopt($soap_do, CURLOPT_TIMEOUT_MS, 10000);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $useSSL);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $useSSL);
        curl_setopt($soap_do, CURLOPT_POST, false);

        curl_setopt($soap_do, CURLOPT_FAILONERROR, true);
        curl_setopt($soap_do, CURLOPT_VERBOSE, true);

        if (! $result = curl_exec($soap_do)) {
            Mage::log('ERROR: ' . curl_error($soap_do), null, self::LOG_FILE);
        }

        curl_close($soap_do);

        Mage::log("------------------ BEGIN SOAP TRANSACTION ------------------", null, self::LOG_FILE);
        Mage::log("------------------ REQUEST ------------------", null, self::LOG_FILE);
        Mage::log(print_r($url, true), null, self::LOG_FILE);
        Mage::log("------------------ RESPONSE ------------------", null, self::LOG_FILE);
        Mage::log(print_r($result, true), null, self::LOG_FILE);
        Mage::log("------------------ END SOAP TRANSACTION ------------------", null, self::LOG_FILE);

        return $result;
    }

}
