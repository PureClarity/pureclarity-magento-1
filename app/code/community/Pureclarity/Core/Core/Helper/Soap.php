<?php
/**
 * PureClarity SOAP Helper for API Calls
 *
 * @title       Pureclarity_Core_Helper
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
class Pureclarity_Core_Helper_Soap
{

    const PRODUCTION_VALUE      = 1;
    const STAGING_VALUE         = 0;

    const FEED_URL_PRODUCTION   = 'https://api.pureclarity.net';
    const FEED_URL_UAT          = 'http://staging01-api.pureclarity.net';  

    const MOTO_URL              = '/api/track/appid={access_key}&evt=moto_order_track';

    const LOG_FILE              = "pureclarity_soap.log";
    
    protected $feedUrl          = '';
    protected $motoUrl          = '';

    /**
     * Pureclarity_Core_Helper_Soap constructor.
     */
    public function __construct()
    {
        if(Mage::getStoreConfig("pureclarity_core/environment/mode") == self::PRODUCTION_VALUE) {
            $this->feedUrl = self::FEED_URL_PRODUCTION;
        } else {
            $this->feedUrl = self::FEED_URL_UAT;
        }
        $this->motoUrl = str_replace('{access_key}', Mage::helper('pureclarity_core')->getAccessKey() , self::MOTO_URL);
    }

    /**
     * @param null $payload
     * @param $additional
     * @return mixed
     */
    public function makeRequest($payload = null, $additional)
    {

        if ($payload == null) {
            Mage::log("Payload has not been set.", null, self::LOG_FILE);
        }

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $this->feedUrl . $additional);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_TIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, true);
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload))
        );

        curl_setopt($soap_do, CURLOPT_FAILONERROR, true);
        curl_setopt($soap_do, CURLOPT_VERBOSE, true);

        if (!$result = curl_exec($soap_do)) {
            Mage::log(curl_error($soap_do), null, self::LOG_FILE);
        }

        curl_close($soap_do);

        Mage::log("------------------ BEGIN SOAP TRANSACTION ------------------", null, self::LOG_FILE);
        Mage::log("------------------ REQUEST ------------------", null, self::LOG_FILE);
        Mage::log(print_r($this->feedUrl . $additional, true), null, self::LOG_FILE);
        Mage::log(print_r($payload, true), null, self::LOG_FILE);
        Mage::log("------------------ RESPONSE ------------------", null, self::LOG_FILE);
        Mage::log(print_r($result, true), null, self::LOG_FILE);
        Mage::log("------------------ END SOAP TRANSACTION ------------------", null, self::LOG_FILE);

        return $result;

    }

    /**
     * Makes a GET request to PureClarity for Feed Notification
     *
     * @param $additional
     * @return mixed
     */
    public function makeGetRequest($additional)
    {
        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $this->feedUrl . $additional);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_TIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, false);

        curl_setopt($soap_do, CURLOPT_FAILONERROR, true);
        curl_setopt($soap_do, CURLOPT_VERBOSE, true);

        if (!$result = curl_exec($soap_do)) {
            Mage::log(curl_errno($soap_do) . curl_error($soap_do));
        }

        curl_close($soap_do);

        Mage::log("------------------ BEGIN SOAP TRANSACTION " . __METHOD__ . " ------------------", null, self::LOG_FILE);
        Mage::log("------------------ REQUEST ------------------", null, self::LOG_FILE);
        Mage::log(var_export($this->feedUrl . $additional, true), null, self::LOG_FILE);
        Mage::log("------------------ RESPONSE ------------------", null, self::LOG_FILE);
        Mage::log(print_r($result, true), null, self::LOG_FILE);
        Mage::log("------------------ END SOAP TRANSACTION ------------------", null, self::LOG_FILE);

        return $result;
    }

    /**
     * Makes a GET request to PureClarity for MOTO Orders
     *
     * @param null $order
     * @param null $orderItems
     * @return mixed
     */
    public function makeMotoGetRequest($order = null, $orderItems = null)
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
            $additional .= '&sku' . $i . '=' . $item->getSku();
            $additional .= '&qty' . $i . '=' . $item->getQtyOrdered();
            $additional .= '&unitprice' . $i . '=' . $item->getPrice();

            $i++;
        }

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $this->feedUrl . $this->motoUrl . $additional);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_TIMEOUT_MS, 3000);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($soap_do, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, false);

        curl_setopt($soap_do, CURLOPT_FAILONERROR, true);
        curl_setopt($soap_do, CURLOPT_VERBOSE, true);

        if (!$result = curl_exec($soap_do)) {
            Mage::log(curl_errno($soap_do) . curl_error($soap_do));
        }

        curl_close($soap_do);

        Mage::log("------------------ BEGIN SOAP TRANSACTION ------------------", null, self::LOG_FILE);
        Mage::log("------------------ REQUEST ------------------", null, self::LOG_FILE);
        Mage::log(print_r($this->feedUrl . $this->motoUrl . $additional, true), null, self::LOG_FILE);
        Mage::log("------------------ RESPONSE ------------------", null, self::LOG_FILE);
        Mage::log(print_r($result, true), null, self::LOG_FILE);
        Mage::log("------------------ END SOAP TRANSACTION ------------------", null, self::LOG_FILE);

        return $result;

    }

}
