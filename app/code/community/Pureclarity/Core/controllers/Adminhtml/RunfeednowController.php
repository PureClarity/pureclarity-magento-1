<?php

class Pureclarity_Core_Adminhtml_RunFeedNowController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Install the default BMZs
     *
     * @return void
     */
    public function runselectedAction()
    {
        session_write_close();
        # These feeds regularly report progress to a file, the file can be queried with getprogressAction()
        $selection = $this->getRequest()->getParam('feedtype');
        $storeId = (int)$this->getRequest()->getParam('storeid');
        $feeds = ["product", "category", "brand"];
        try {
            if (in_array($selection, $feeds)) {
                $model = Mage::getModel('pureclarity_core/cron');
                switch ($selection) {
                    case "product":
                        $model->fullProductFeed($storeId);
                        break;
                    case "category":
                        $model->fullCategoryFeed($storeId);
                        break;
                    case "brand":
                        $model->fullBrandFeed($storeId);
                        break;
                }
            }
        }
        catch (\Exception $e){
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('HTTP/1.0', 409, true)
                ->setHeader('Content-Type', 'text/html') // can be changed to json, xml...
                ->setBody('Conflict');
        }
    }

    public function getprogressAction(){
        $selection = $this->getRequest()->getParam('feedtype');
        $feeds = ["product", "category", "brand"];
        if (in_array($selection, $feeds)){
            $progressFileName = null;
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
            if ($progressFileName != null){
                $this->getResponse()->setBody(file_get_contents($progressFileName));
            }
        }
    }
}