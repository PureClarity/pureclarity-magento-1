<?php

class Pureclarity_Core_Adminhtml_RunFeedBoxController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Show the run-a-feed-now box
     *
     * @return void
     */
    public function runfeedAction()
    {
        // This just returns the popup box with the controls for running a feed.
        $this->loadLayout();
        $this->getLayout()->getBlock('root')->setTemplate('pureclarity/system/config/run_feed_box.phtml');
        $this->renderLayout();
    }
}