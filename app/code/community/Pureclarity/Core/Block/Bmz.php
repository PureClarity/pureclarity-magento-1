<?php
class Pureclarity_Core_Block_Bmz extends Mage_Core_Block_Template
{

    protected $debug;
    protected $reference;
    protected $content;
    protected $classes;
    protected $extraAttrs;

    public function _construct()
    {
        return parent::_construct();
    }

    public function prepare(){
        // Get some parameters
        $this->debug = Mage::helper('pureclarity_core')->isBMZDebugActive();
        $this->reference = $this->escapeHtml($this->getData('pc_bmz_reference'));

        if ($this->reference == null or $this->reference == ""){
            Mage::log("Pureclarity BMZ block instantiated without a reference.", Zend_Log::ERR);
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
            $debugContent = "<p>Pureclarity BMZ. Reference: $this->reference.</p>";
        }

        // Get the fallback content
        $fallbackContent = $this->getData('pc_bmz_fallback_content');
        $fallbackCmsBlock = $this->getData('pc_bmz_fallback_cms_block');

        if ($fallbackContent && $fallbackCmsBlock){
            Mage::log("Pureclarity BMZ block '$this->reference' instantiated with both 'pc_bmz_fallback_content' and ".
                "'pc_bmz_fallback_cms_block'. A BMZ must only have one fallback option set. ".
                "CMS block fallback option will take priority.", Zend_Log::ERR);
        }

        if ($fallbackCmsBlock) {
            if (!Mage::getModel('cms/block')->load($fallbackCmsBlock)->getIsActive()){
                Mage::log("Pureclarity BMZ block '$this->reference' specifies a fallback CMS block that is not active.", Zend_Log::ERR);
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
            $content = "<script>console.error(\"Pureclarity BMZ $this->reference is set to desktop-only and "
                ."mobile-only. This BMZ will be hidden.\");</script>".$content;
            Mage::log("Pureclarity BMZ block '$this->reference' is set to desktop-only and mobile-only. "
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

    public function getReference()
    {
        return $this->reference;
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