<?php


class PureClarity_Core_ExportController extends Mage_Core_Controller_Front_Action
{
    public function productsAction()
    {
        //echo Mage::app()->getStore()->getCode();

        $pageSize = (int)$this->getRequest()->getParam('size', 100000);
        $currentPage = (int)$this->getRequest()->getParam('page', 1);

        $model = Mage::getModel('pureclarity_core/productexport');
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
}