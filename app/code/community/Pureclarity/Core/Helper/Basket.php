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
* PureClarity Basket Data Helper
*/
class Pureclarity_Core_Helper_Basket extends Mage_Core_Helper_Abstract
{
    
    public function getBasketChanges()
    {
        $basket = false;
        try {
            /** @var Mage_Core_Model_Session $coreSession */
            $coreSession = Mage::getModel('core/session');
            $changed = $coreSession->getPCBasketChanged();
            
            if ($changed) {
                // get basket info here
                $currentBasket = $this->getBasketData();
                $lastBasket = $coreSession->getPCLastBasket();
                if ($currentBasket !== $lastBasket) {
                    $coreSession->setPCLastBasket($currentBasket);
                    $basket = $currentBasket;
                }
                
                $changed = $coreSession->setPCBasketChanged(false);
            }
        } catch (Exception $e) {
            Mage::log('PC set_basket data error: ' . $e->getMessage());
        }
        
        return $basket;
    }
    
    /**
     * Prepares data for the set_basket event based on the customers cart contents
     *
     * @return void
     */
    protected function getBasketData()
    {
        /** @var Mage_Checkout_Model_Session $coreSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $items = array();
        $cart = $checkoutSession->getQuote();
        $visibleItems = $cart->getAllVisibleItems();
        foreach ($visibleItems as $item) {
            $items[$item->getItemId()] = array(
                'id' => $item->getProductId(),
                'qty' => $item->getQty(),
                'unitprice' => $item->getPrice(),
                'refid' => $item->getItemId(),
                'children' => array()
            );
        }
        
        $allItems = $cart->getAllItems();
        foreach ($allItems as $item) {
            if ($item->getParentItemId() && isset($items[$item->getParentItemId()])) {
                $items[$item->getParentItemId()]['children'][] = array(
                    "sku" => $item->getSku(), "qty" => $item->getQty()
                );
            }
        }

        return array_values($items);
    }
}
