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
 * @copyright Copyright (c) 2020 PureClarity Technologies Ltd
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *****************************************************************************************/

/**
* PureClarity Page View Context Helper
*/
class Pureclarity_Core_Helper_Pageview extends Mage_Core_Helper_Abstract
{
    /**
     * Gets the context data for the page view event
     *
     * @return false|string
     */
    public function getPageViewContext()
    {
        return json_encode($this->getPageContext($this->getPageType()));
    }

    /**
     * Works out what page type this page is, based on page module / controller / action
     *
     * @return string
     */
    protected function getPageType()
    {
        $module = $this->_getRequest()->getModuleName();
        $controller = $this->_getRequest()->getControllerName();
        $action = $this->_getRequest()->getActionName();

        $pageType = '';
        if ($this->isHomePage()) {
            $pageType = 'homepage';
        } elseif ($this->isSearchResultsPage($module, $controller, $action)) {
            $pageType = 'search_results';
        } elseif ($this->isCategoryListingPage($module, $controller, $action)) {
            $pageType = 'category_listing_page';
        } elseif ($this->isProductListingPage($module, $controller, $action)) {
            $pageType = 'product_listing_page';
        } elseif ($this->isProductPage($module, $controller, $action)) {
            $pageType = 'product_page';
        } elseif ($this->isBasketPage($module, $controller, $action)) {
            $pageType = 'basket_page';
        } elseif ($this->isAccountPage($module, $controller, $action)) {
            $pageType = 'my_account';
        } elseif ($this->isOrderCompletePage($module, $controller, $action)) {
            $pageType = 'order_complete_page';
        } elseif ($this->isContentPage($module, $controller, $action)) {
            $pageType = 'content_page';
        }

        return $pageType;
    }

    /**
     * @return string
     */
    protected function isHomePage()
    {
        $routeName = Mage::app()->getRequest()->getRouteName();
        $identifier = Mage::getSingleton('cms/page')->getIdentifier();
        return ($routeName === 'cms' && $identifier === 'home');
    }

    /**
     * Determines from the module/controller/action if this is a "Search Results" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isSearchResultsPage($module, $controller, $action)
    {
        return ($module === 'catalogsearch' && $controller === 'result' && $action === 'index') ||
               ($module === 'catalogsearch' && $controller === 'advanced' && $action === 'result');
    }

    /**
     * Determines from the module/controller/action if this is a "Category Listing" page
     * Also uses category setting as a it's not the default behaviour of a category
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isCategoryListingPage($module, $controller, $action)
    {
        $isCategoryPage = ($module === 'catalog' && $controller === 'category' && $action === 'view');

        $isAnchor = false;
        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::registry('current_category');
        if ($category) {
            $isAnchor = $category->getDisplayMode() === 'PAGE';
        }

        return $isCategoryPage && $isAnchor;
    }

    /**
     * Determines from the module/controller/action if this is a "Product Listing" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isProductListingPage($module, $controller, $action)
    {
        $isCategoryPage = ($module === 'catalog' && $controller === 'category' && $action === 'view');

        $isListing = false;
        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::registry('current_category');
        if ($category) {
            $isListing = $category->getDisplayMode() !== 'PAGE';
        }

        return $isCategoryPage && $isListing;
    }

    /**
     * Determines from the module/controller/action if this is a "Basket" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isBasketPage($module, $controller, $action)
    {
        return ($module === 'checkout' && $controller === 'cart' && $action === 'index');
    }

    /**
     * Determines from the module/controller/action if this is a "Product View" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isProductPage($module, $controller, $action)
    {
        return ($module === 'catalog' && $controller === 'product' && $action === 'view');
    }

    /**
     * Determines from the module/controller/action if this is an "Account" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isAccountPage($module, $controller, $action)
    {
        return ($module === 'customer' && $controller === 'account') ||
               ($module === 'customer' && $controller === 'address') ||
               ($module === 'sales' && $controller === 'order' && ($action === 'history' || $action === 'view')) ||
               ($module === 'sales' && $controller === 'recurring_profile') ||
               ($module === 'sales' && $controller === 'billing_agreement') ||
               ($module === 'review' && $controller === 'customer' && $action === 'index') ||
               ($module === 'wishlist' && $controller === 'index') ||
               ($module === 'oauth' && $controller === 'customer_token' && $action === 'index') ||
               ($module === 'newsletter' && $controller === 'manage' && $action === 'index') ||
               ($module === 'downloadable' && $controller === 'customer' && $action === 'products');
    }

    /**
     * Determines from the module/controller/action if this is the "Order Complete" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isOrderCompletePage($module, $controller, $action)
    {
        return ($module === 'checkout' && $controller === 'onepage' && $action === 'success') ||
               ($module === 'checkout' && $controller === 'multishipping' && $action === 'success');
    }

    /**
     * Determines from the module/controller/action if this is a "Content" page
     *
     * @param string $module -  current module name
     * @param string $controller - current controller name
     * @param string $action - current action name
     *
     * @return bool
     */
    protected function isContentPage($module, $controller, $action)
    {
        return ($module === 'cms' && $controller === 'page' && $action === 'view');
    }

    /**
     * Gets page-type specific context (e.g. product page - product ID)
     *
     * @param string $pageType Page Type - the page type currently being viewed.
     *
     * @return array
     */
    protected function getPageContext($pageType)
    {
        $context = array();

        if ($pageType) {
            $context['page_type'] = $pageType;
        }

        switch ($pageType) {
            case 'category_listing_page':
            case 'product_listing_page':
                $category = Mage::registry('current_category');
                if ($category) {
                    $context['category_id'] = $category->getId();
                }
                break;
            case 'product_page':
                $product = Mage::registry('current_product');
                if ($product) {
                    $context['product_id'] = $product->getId();
                }

                $category = Mage::registry('current_category');
                if ($category) {
                    $context['category_id'] = $category->getId();
                }
                break;
        }

        return $context;
    }

}
