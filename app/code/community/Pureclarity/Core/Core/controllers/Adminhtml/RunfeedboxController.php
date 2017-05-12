<?php

class Pureclarity_Core_Adminhtml_RunFeedBoxController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Install the default BMZs
     *
     * @return void
     */
    public function runfeedAction()
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('root')->setTemplate('pureclarity/system/config/run_feed_box.phtml');
        $this->renderLayout();
    }
}