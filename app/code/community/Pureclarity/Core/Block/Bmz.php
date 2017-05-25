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

class Pureclarity_Core_Block_Bmz extends Mage_Core_Block_Template
{

    protected $debug;
    protected $bmzId;
    protected $content;
    protected $classes;
    protected $extraAttrs;

    public function _construct()
    {
        return parent::_construct();
    }

    protected function _toHtml()
    {
        if (Mage::helper('pureclarity_core')->isMerchActive()){
            return parent::_toHtml();
        }
        return '';
    }

    public function _beforeToHtml(){

        // Get some parameters
        $this->debug = Mage::helper('pureclarity_core')->isBMZDebugActive();
        $this->bmzId = $this->escapeHtml($this->getData('bmz_id'));

        if ($this->bmzId == null or $this->bmzId == ""){
            Mage::log("Pureclarity BMZ block instantiated without a BMZ Id.", Zend_Log::ERR);
        }

        // Generate the extra attributes string
        $extraAttrsString = '';
        $extraAttrs = $this->getData('pc_bmz_attrs');
        if ($extraAttrs){
            $extraAttrsString = ' ' . $extraAttrs;
        }
        $this->extraAttrs = $extraAttrsString;

        // Generate debug text if needed
        $debugContent = '';
        if ($this->debug){
            $debugContent = "<p>PureClarity BMZ: $this->bmzId</p>";
        }

        // Get the fallback content
        $fallbackContent = $this->getData('bmz_fallback_content');
        $fallbackCmsBlock = $this->getData('bmz_fallback_cms_block');

        if ($fallbackContent && $fallbackCmsBlock){
            Mage::log("Pureclarity BMZ block '$this->bmzId' instantiated with both 'bmz_fallback_content' and ".
                "'bmz_fallback_cms_block'. A BMZ must only have one fallback option set. ".
                "CMS block fallback option will take priority.", Zend_Log::ERR);
        }

        if ($fallbackCmsBlock) {
            if (!Mage::getModel('cms/block')->load($fallbackCmsBlock)->getIsActive()){
                Mage::log("Pureclarity BMZ block '$this->bmzId' specifies a fallback CMS block that is not active.", Zend_Log::ERR);
            }
            else {
                $fallbackBlock = $this->getLayout()->createBlock('cms/block')->setBlockId($fallbackCmsBlock);
                $fallbackContent = $fallbackBlock->toHtml();
                if ($this->debug) {
                    $debugContent .= "<p>Fallback block: $fallbackCmsBlock.</p>";
                }
            }
        }

        // The actual content is the debug content followed by the fallback content.
        // In most cases; content will be an empty string
        $content = $debugContent . $fallbackContent;

        // Get a list of the custom classes for this BMZs div tag
        $customClasses = $this->getData('pc_bmz_classes');
        if ($customClasses){
            $allClasses = explode(",", $customClasses);
        }
        else {
            $allClasses = [];
        }

        // Check for desktop-specific or mobile-specific BMZs
        $isMobile = $this->getData('pc_bmz_is_mobile_only') == "true";
        $isDesktop = $this->getData('pc_bmz_is_desktop_only') == "true";

        // Add more classes to the class list where they are needed to identify desktop-specific or mobile-specific BMZs
        if ($isMobile  && !$isDesktop){$allClasses[] = "pureclarity_magento_mobile";}
        if ($isDesktop && !$isMobile) {$allClasses[] = "pureclarity_magento_desktop";}
        if ($isDesktop &&  $isMobile){
            // If this is only-desktop and only-mobile then somebody has done something wrong.
            // Give this the conflict class and produce an error message in the browser console and magento log.
            $allClasses[] = "pureclarity_magento_media_conflict";
            $content = "<script>console.error(\"Pureclarity BMZ $this->bmzId is set to desktop-only and "
                ."mobile-only. This BMZ will be hidden.\");</script>".$content;
            Mage::log("Pureclarity BMZ block '$this->bmzId' is set to desktop-only and mobile-only. "
                ."This BMZ will be hidden.", Zend_Log::ERR);
        }

        // Content is now final
        $this->content = $content;

        // Classes are now final
        $allClassesStr = implode(" ", $allClasses);
        $allClassesStr = $this->escapeHtml($allClassesStr);
        $this->classes = $allClassesStr;
    }

    public function getDebugMode()
    {
        return $this->debug;
    }

    public function getBmzId()
    {
        return $this->bmzId;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getClasses()
    {
        return $this->classes;
    }

    public function getExtraAttrs()
    {
        return $this->extraAttrs;
    }

}