<?php

class Pureclarity_Core_Adminhtml_RunFeedNowController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Run the feeds that were selected in the "run feed now" box
     *
     * @return void
     */
    public function runselectedAction()
    {
        session_write_close();
        // These feeds regularly report progress to a file, the file can be queried with getprogressAction()
        // This actions is called once for each selected feed. the 'feedtype' parameter determines which feed this is
        $selection = $this->getRequest()->getParam('feedtype');
        $feeds = ["product", "category", "brand"];
        try {
            if (in_array($selection, $feeds)) {
                $model = Mage::getModel('pureclarity_core/cron');
                switch ($selection) {
                    case "product":
                        $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT);
                        $model->fullProductFeed($progressFileName);
                        break;
                    case "category":
                        $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY);
                        $model->fullCategoryFeed($progressFileName);
                        break;
                    case "brand":
                        $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND);
                        $model->fullBrandFeed($progressFileName);
                        break;
                }
            }
        }
        catch (\Exception $e){
            // When a feed cannot start running, because it is already running, it raises an exception
            // This action will then return a 409 (Conflict) to indicate that the feed is already running.
            // The UI handles this by updating the progress bars to show the feed that started first.
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('HTTP/1.0', 409, true)
                ->setHeader('Content-Type', 'text/html') // can be changed to json, xml...
                ->setBody('Conflict');
        }
    }

    /* Get the current progress of a feed.
     *
     */
    public function getprogressAction(){
        $selection = $this->getRequest()->getParam('feedtype');
        $feeds = ["product", "category", "brand"];
        if (in_array($selection, $feeds)){
            $progressFileName = null;
            // First determine the name of the progress file
            switch ($selection){
                case "product":
                    $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT);
                    break;
                case "category":
                    $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY);
                    break;
                case "brand":
                    $progressFileName = Pureclarity_Core_Helper_Data::progressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND);
                    break;
            }
            // Then just send the entire contents of that file as the response.
            if ($progressFileName != null){
                echo file_get_contents($progressFileName);
            }
        }
    }
}