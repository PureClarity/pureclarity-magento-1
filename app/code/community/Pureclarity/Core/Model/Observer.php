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

class Pureclarity_Core_Model_Observer extends Mage_Core_Model_Abstract
{

    const PURECLARITY_FEED_MOTO_ENDPOINT = '/api/track?appid={access_key}&evt=moto_order_track';

    public function insertBmz(Varien_Event_Observer $observer){
        $name = $observer->getEvent()->getBlock()->getNameInLayout();
        $block = $observer->getBlock();
        
        $type = $block->getType();
        if ($name == 'content'){
            Mage::log(get_class($block) . ' - ' . $type);
            $block = $observer->getEvent()
                ->getBlock()
                ->getLayout()
                ->createBlock('adminhtml/template')
                ->setTemplate('pureclarity/BMZ/bmz.phtml');
            $observer->getEvent()
                ->getBlock()
                ->append($block);
        }
    }


    

    /**
     * Set data for frontend JS call when an item is added to the basket
     *
     * Observes: controller_action_predispatch_checkout_cart_add
     *
     * @param Varien_Event_Observer $observer
     */
    public function logCartAdd(Varien_Event_Observer $observer)
    {

        if(!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
            return;
        }

        $product = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect("sku")
            ->addAttributeToFilter("entity_id", array("eq" => Mage::app()->getRequest()->getParam('product', 0)))
            ->getFirstItem();

        if (!$product->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCProductToShoppingCart(
            new Varien_Object(array(
                'sku' => $product->getSku(),
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

        if(!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
            return;
        }

        $product = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect("sku")
            ->addAttributeToFilter("entity_id", array("eq" => $observer->getQuoteItem()->getProduct()->getId()))
            ->getFirstItem();

        if (!$product->getId()) {
            return;
        }

        Mage::getModel('core/session')->setPCProductRemovedShoppingCart(
            new Varien_Object(array(
                'sku' => $product->getSku()
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
        if(!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
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

        if(!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
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

        if(!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
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
        $product = $observer->getEvent()->getProduct();
        if(!Mage::helper('pureclarity_core')->isActive($product->getStoreId())) {
            return;
        }

        Mage::register('product_categories',
            $observer->getEvent()->getProduct()->getCategoryIds()
        );
    }

    public function saveDelta(Varien_Event_Observer $observer)
    {

        $product = $observer->getEvent()->getProduct();
        
        if(!Mage::helper('pureclarity_core')->isActive($product->getStoreId())) {
            return;
        }

        $deltaProduct = Mage::getModel('pureclarity_core/productFeed');
        $deltaProduct->setData(
            array(
                'product_id'    => $product->getId(),
                'oldsku'        => $product->getOrigData('sku'),
                'token'         => '',
                'status_id'     => 0,
                'store_id'      => $product->getStoreId()
            )
        );
        $deltaProduct->save();
    }

    public function motoOrder(Varien_Event_Observer $observer)
    {
        //observer gets called multiple times - only count 1st one
        //see: https://magento.stackexchange.com/questions/84979/ce-1-9-2-custom-sales-order-save-after-observer-fires-twice
        if(!Mage::registry('pureclarity_moto_prevent_observer')){

            // Assign value to registry variable
            Mage::register('pureclarity_moto_prevent_observer',true);

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
                'productcount'  => count($order->getAllVisibleItems())
            );

            Mage::helper('pureclarity_core/soap')->motoOrderGetRequest($information, $order->getAllVisibleItems());
        }
    }

}
