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
     * Run selected feeds
     *
     * @return void
     */
    public function runselectedAction()
    {
        try {
            Mage::log("PureClarity: In Pureclarity_Core_Adminhtml_RunFeedNowController->runselectedAction()");
            $storeId =  (int)$this->getRequest()->getParam('storeid');
            /** @var Pureclarity_Core_Model_Cron $model */
            $model = Mage::getModel('pureclarity_core/cron');
            $feeds = array();
            if ($this->getRequest()->getParam('product') == 'true') {
                $feeds[] = 'product';
            }
            
            if ($this->getRequest()->getParam('category') == 'true') {
                $feeds[] = 'category';
            }
            
            if ($this->getRequest()->getParam('brand') == 'true') {
                $feeds[] = 'brand';
            }
            
            if ($this->getRequest()->getParam('user') == 'true') {
                $feeds[] = 'user';
            }
            
            if ($this->getRequest()->getParam('orders') == 'true') {
                $feeds[] = 'orders';
            }
            
            $pcDir = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS;
            $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName();
            
            $fileHandler = new Varien_Io_File();
            $fileHandler->open(array('path' => $pcDir));
            
            if ($progressFileName != null && $fileHandler->fileExists($progressFileName)) {
                $fileHandler->rm($progressFileName);
            }
            
            $model->scheduleSelectedFeeds($storeId, $feeds);
        }
        catch (\Exception $e){
            $this->getResponse()
            ->clearHeaders()
            ->setHeader('HTTP/1.0', 409, true)
            ->setHeader('Content-Type', 'text/html')
            ->setBody($e->getMessage());
        }
    }

    public function getprogressAction()
    {
        $contents = "";
        
        $progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName();
        $pcDir = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS;
        
        $fileHandler = new Varien_Io_File();
        $fileHandler->open(array('path' => $pcDir));
        
        if ($progressFileName != null && $fileHandler->fileExists($progressFileName)) {
            $contents = $fileHandler->read($progressFileName);
            Mage::log("In getprogressAction(): " . $contents);
        }

        if (empty($contents)) {
            $contents = "{}";
        }

        try {
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-type', 'application/json')
                ->setBody($contents);
        }
        catch (\Exception $e){
            Mage::log($e->getMessage());
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('HTTP/1.0', 409, true)
                ->setHeader('Content-Type', 'text/html')
                ->setBody($e->getMessage());
        }
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/config');
    }
}
