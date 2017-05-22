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
                        if (!Mage::helper('pureclarity_core')->isBrandFeedEnabled($storeId)){
                            $this->getResponse()
                                ->clearHeaders()->setHeader('HTTP/1.0', 405, true)
                                ->setHeader('Content-Type', 'text/html')
                                ->setBody('The brand feed for the selected store is disabled. Please enable it before running.');
                        }
                        else 
                            $model->fullBrandFeed($storeId);
                        break;
                }
            }
        }
        catch (\Exception $e){
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('HTTP/1.0', 409, true)
                ->setHeader('Content-Type', 'text/html')
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
                    $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_PRODUCT);
                    break;
                case "category":
                    $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_CATEGORY);
                    break;
                case "brand":
                    $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName(Pureclarity_Core_Helper_Data::FEED_TYPE_BRAND);
                    break;
            }
            if ($progressFileName != null){
                $contents = '';
                if (file_exists($progressFileName))
                    $contents = file_get_contents($progressFileName);
                $this->getResponse()->setBody($contents);
            }
        }
    }
}