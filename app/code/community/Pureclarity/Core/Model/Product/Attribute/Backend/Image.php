<?php
class Pureclarity_core_Model_Product_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
        
    public function beforeSave($object)
    {
        parent::beforeSave($object);
         
        $name = $this->_getName();
        $imageData = $object->getData($name);
         
        if(isset($imageData['delete']) && (bool) $imageData['delete']) {
            return $this->_removeImage($object, $imageData['value']);
        }else{
            return $this->_uploadImage($object);
        }
    }
     
    protected function _getHelper()
    {
        return Mage::helper('pureclarity_core');
    }
     
    protected function _getName()
    {
        return $this->getAttribute()->getName();
    }
     
    protected function _removeImage($object, $fileName)
    {
        $file = $this->_getHelper()->getPlaceholderDir() . $fileName;
        $name = $this->_getName();
         
        if(file_exists($file)) {
            unlink($file);
        }
         
        $object->setData($name, '');
    }
     
    protected function _uploadImage($object)
    {
        $name = $this->_getName();
          
        if(!isset($_FILES[$name]) || (int) $_FILES[$name]['size'] <= 0) {
            return;
        }
         
        $path = $this->_getHelper()->getPlaceholderDir();
         
        $uploader = new Varien_File_Uploader($_FILES[$name]);
        $uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
        // Allow Magento to create a name for this upload!
        $uploader->setAllowRenameFiles(true);
         
        $result = $uploader->save($path);
         
        $object->setData($name, $result['file']);
    }
     
}