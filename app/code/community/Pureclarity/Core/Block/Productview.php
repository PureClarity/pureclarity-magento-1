<?php
/**
 * PureClarity Product View Block
 *
 * @title       Pureclarity_Core_Block_Productview
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
class Pureclarity_Core_Block_Productview extends Mage_Core_Block_Template
{

    protected $_product;

    public function _construct()
    {
        $this->_product = Mage::registry("current_product");
    }

}