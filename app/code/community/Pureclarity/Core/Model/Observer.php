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

    public function insertBmz(Varien_Event_Observer $observer)
    {
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
    public function logCartChange(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('pureclarity_core')->isActive(Mage::app()->getStore()->getId())) {
            return;
        }

        Mage::getModel('core/session')->setPCBasketChanged(true);
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
            new Varien_Object(
                array(
                'userid' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'salutation' => $customer->getPrefix()
                )
            )
        );
        
        Mage::getModel('core/session')->setPCBasketChanged(true);

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
            new Varien_Object(
                array(
                'logout' => $customer->getId()
                )
            )
        );
        Mage::getModel('core/session')->setPCBasketChanged(true);
    }

    public function beforeDeltaSave(Varien_Event_Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        if(!Mage::helper('pureclarity_core')->isActive($product->getStoreId())) {
            return;
        }

        Mage::register(
            'product_categories',
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

    /**
     * Add deltas for affected products if the category being updated is excluded from the feed
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function categoryChangeProducts(Varien_Event_Observer $observer)
    {
        $category = $observer->getEvent()->getCategory();
        
        if (!Mage::helper('pureclarity_core')->isDeltaNotificationActive($category->getStoreId())) {
            return;
        }
        
        $excludedCats = explode(
            ',',
            Mage::helper('pureclarity_core')->getExcludedProductCategories($category->getStoreId())
        );
        
        if (!in_array($category->getId(), $excludedCats)) {
            return;
        }
        
        $products = $observer->getEvent()->getProductIds();

        foreach ($products as $productId) {
            $deltaProduct = Mage::getModel('pureclarity_core/productFeed');
            $deltaProduct->setData(
                array(
                    'product_id'    => $productId,
                    'oldsku'        => $this->getSkuById($productId),
                    'token'         => '',
                    'status_id'     => 0,
                    'store_id'      => $category->getStoreId()
                )
            );
            $deltaProduct->save();
        }
        
    }
    
    /**
     * Gets the product SKU from ID
     *
     * @param integer $productId
     * @return void
     */
    protected function getSkuById($productId)
    {
        $productResourceModel = Mage::getResourceModel('catalog/product');
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from($productResourceModel->getEntityTable(), 'sku')
            ->where('entity_id = :entity_id');
        $bind = array(':entity_id' => (string)$productId);
        $productSku = $adapter->fetchOne($select, $bind);
        return $productSku;
    }

    public function motoOrder(Varien_Event_Observer $observer)
    {
        //observer gets called multiple times - only count 1st one
        //see: https://magento.stackexchange.com/questions/84979/ce-1-9-2-custom-sales-order-save-after-observer-fires-twice
        if(!Mage::registry('pureclarity_moto_prevent_observer')){
            // Assign value to registry variable
            Mage::register('pureclarity_moto_prevent_observer', true);

            /** @var Mage_Sales_Model_Order $order */
            $order = $observer->getEvent()->getOrder();

            $additional = self::PURECLARITY_FEED_MOTO_ENDPOINT;

            $information = array(
                'orderid'       => $order->getIncrementId(),
                'firstname'     => $order->getCustomerFirstname(),
                'lastname'      => $order->getCustomerLastname(),
                'postcode'      => $order->getBillingAddress()->getPostcode(),
                'email'         => $order->getCustomerEmail(),
                'userid'        => $order->getCustomerId(),
                'ordertotal'    => $order->getGrandTotal(),
                'productcount'  => count($order->getAllVisibleItems())
            );

            // Construct order items
            $orderItems = Mage::helper('pureclarity_core')->getOrderItems($order);
            Mage::helper('pureclarity_core/soap')->motoOrderGetRequest($order->getStoreId(), $information, $orderItems);
        }
    }

}
