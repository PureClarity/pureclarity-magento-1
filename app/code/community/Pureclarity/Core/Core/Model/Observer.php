<?php
/**
 * PureClarity Cron tasks
 *
 * @title       Pureclarity_Core_Model_Observer
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
class Pureclarity_Core_Model_Observer extends Mage_Core_Model_Abstract
{

    const PURECLARITY_FEED_MOTO_ENDPOINT = '/api/track?appid={access_key}&evt=moto_order_track';

    /**
     * Set data for frontend JS call when an item is added to the basket
     *
     * Observes: controller_action_predispatch_checkout_cart_add
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCartAdd(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        $product = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect("sku")->addAttributeToFilter("entity_id", array("eq" => Mage::app()->getRequest()->getParam('product', 0)))->getFirstItem();

        if (!$product->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCProductToShoppingCart(
            new Varien_Object(array(
                'sku' => $product->getId(),
                'qty' => Mage::app()->getRequest()->getParam('qty', 1)
            ))
        );

    }

    /**
     * Set data for frontend JS call when an item is removed to the basket
     *
     * Observes: sales_quote_remove_item
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCartRemove(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        $product = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect("sku")->addAttributeToFilter("entity_id", array("eq" => $observer->getQuoteItem()->getProduct()->getId()))->getFirstItem();

        if (!$product->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCProductRemovedShoppingCart(
            new Varien_Object(array(
                'sku' => $product->getId()
            ))
        );

    }

    /**
     * Set data for frontend JS call when an items are updated within the basket
     *
     * Observes: sales_quote_item_qty_set_after
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCheckoutUpdateItems(Varien_Event_Observer $observer)
    {
        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        $event = $observer->getEvent();

        $product = $event->getItem();

        if($product->getOrigData('qty') != $product->getData('qty')) {

            $info = Mage::getModel('core/session')->getPCCheckoutUpdateItems();
            if(is_array($info)) {
                $info[] = new Varien_Object(array(
                    'sku' => $product->getSku(),
                    'quantity' => $product->getData('qty')
                ));
            } else {
                $info = array(new Varien_Object(array(
                    'sku' => $product->getSku(),
                    'quantity' => $product->getData('qty')
                )));
            }

            Mage::getModel('core/session')->setPCCheckoutUpdateItems(
                $info
            );
        }

    }

    /**
     * Set data for frontend JS call when a customer logs in
     *
     * Observes: customer_login
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCustomerLogin(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getCustomer();

        if (!$customer->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCCustomerLogin(
            new Varien_Object(array(
                'userid' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstName(),
                'lastname' => $customer->getLastName(),
                'salutation' => $customer->getTitle(),
            ))
        );

    }

    /**
     * Set data for frontend JS call when a customer logs in
     *
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCustomerLogout(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getCustomer();

        if (!$customer->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCCustomerLogout(
            new Varien_Object(array(
                'logout' => $customer->getId()
            ))
        );
    }

    public function beforeDeltaSave(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        Mage::register('product_categories',
            $observer->getEvent()->getProduct()->getCategoryIds()
        );
    }

    public function saveDelta(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive()) {
            return;
        }

        $product = $observer->getEvent()->getProduct();

        $deltaRecord = false;
        $deleted = false;

        if($product->getData('thumbnail') != $product->getOrigData('thumbnail')) {
            $deltaRecord = true;
        }

        if($product->getData('small_image') != $product->getOrigData('small_image')) {
            $deltaRecord = true;
        }

        if($product->getData('image') != $product->getOrigData('image')) {
            $deltaRecord = true;
        }

        if($product->getData('swatch_image') != $product->getOrigData('swatch_image')) {
            $deltaRecord = true;
        }

        if($product->getData('media_gallery') != $product->getOrigData('media_gallery')) {
            $deltaRecord = true;
        }

        // process rules
        if($product->getData('sku') != $product->getOrigData('sku')) {
            $deltaRecord = true;
        }

        if($product->getData('name') != $product->getOrigData('name')) {
            $deltaRecord = true;
        }

        if($product->getData('description') != $product->getOrigData('description')) {
            $deltaRecord = true;
        }

        if($product->getData('url_key') != $product->getOrigData('url_key')) {
            $deltaRecord = true;
        }

        if($product->getCategoryIds() != Mage::registry('product_categories')) {
            $deltaRecord = true;
        }

        if($product->getData('price') != $product->getOrigData('price')) {
            $deltaRecord = true;
        }

        if($product->getData('special_price') != $product->getOrigData('special_price')) {
            $deltaRecord = true;
        }

        if($product->getData('status') == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
            $deleted = true;
        }

        if($deltaRecord == true || $deleted == true) {
            $deltaProduct = Mage::getModel('pureclarity_core/productFeed');
            $deltaProduct->setData(
                array(
                    'product_id'    => $product->getId(),
                    'deleted'       => ($deleted == true) ? 1 : 0,
                    'token'         => '',
                    'status_id'     => 0
                )
            );

            $deltaProduct->save();
        }

    }

    public function motoOrder(Varien_Event_Observer $observer)
    {

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        $additional = self::PURECLARITY_FEED_MOTO_ENDPOINT;

        $information = array(
            'orderid'       => $order->getIncrementId(),
            'firstname'     => $order->getCustomerFirstname(),
            'lastname'      => $order->getCustomerLastname(),
            'postcode'      => $order->getBillingAddress()->getPostcode(),
            'email'         => $order->getCustomerEmail(),
            'ordertotal'    => $order->getGrandTotal(),
            'productcount'  => count($order->getAllItems())
        );

        Mage::log($information);

        Mage::helper('pureclarity_core/soap')->makeMotoGetRequest($information, $order->getAllItems());

    }

}
