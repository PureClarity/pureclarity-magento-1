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

class PureClarity_Core_ExportController extends Mage_Core_Controller_Front_Action
{
    public function productsAction()
    {
        $pageSize = (int)$this->getRequest()->getParam('size', 100000);
        $currentPage = (int)$this->getRequest()->getParam('page', 1);

        $model = Mage::getModel('pureclarity_core/productExport');
        $model->init(Mage::app()->getStore()->getId());
        $result = $model->getFullProductFeed($pageSize, $currentPage);
        $formatType = 'json';
        $contentType = 'application/octet-stream';
        if ((strnatcmp(phpversion(),'5.4.0') >= 0) && $this->getRequest()->getParam('pretty', 'false') === 'true'){
            $formatType = 'jsonpretty';
            $contentType = 'text/html';
        }
        $json = Mage::helper('pureclarity_core')->formatFeed($result, $formatType);
        //$this->getResponse()->setHeader('Content-type', $contentType);
        $this->getResponse()->setBody($json);
    }

    public function deltasAction()
    {
        $model = Mage::getModel('pureclarity_core/cron');
        $requests = $model->deltaFeed(null, true);
        $formatType = 'json';
        $contentType = 'application/octet-stream';
        if ((strnatcmp(phpversion(),'5.4.0') >= 0) && $this->getRequest()->getParam('pretty', 'false') === 'true'){
            $formatType = 'jsonpretty';
            $contentType = 'text/html';
        }
        $json = Mage::helper('pureclarity_core')->formatFeed($requests, $formatType);
        $this->getResponse()->setBody($json);
    }

    public function feedAction(){
        $body = json_decode($this->getRequest()->getRawBody(), TRUE);
        $storeId = (int)$this->getRequest()->getParam('storeid', -1);
        $type =  $this->getRequest()->getParam('type', null);
        if ($body != null && is_array($body) && $storeId > -1 && $type != null){
            $correctAccessKey = $body['AccessKey'] == Mage::helper('pureclarity_core')->getAccessKey($storeId);
            $correctSecretKey = $body['SecretKey'] == Mage::helper('pureclarity_core')->getSecretKey($storeId);
            if ($correctAccessKey && $correctSecretKey) {
                $store = Mage::getModel('core/store')->load($storeId);
                if ($store != null){
                    $feedFilePath = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . Pureclarity_Core_Helper_Data::getFileNameForFeed($type, $store->getCode());
                    if ($feedFilePath != null){
                        $file = file_get_contents($feedFilePath);
                        if ($file != null){
                            $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json');
                            $this->getResponse()->setBody($file);
                            return;
                        }
                    }
                }
            }
            else{
                $this->getResponse()->setHeader('HTTP/1.0','403',true);
                return;
            }
        }
        $this->getResponse()->setHeader('HTTP/1.0','404',true);
    }
}