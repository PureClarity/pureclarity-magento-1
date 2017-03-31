<?php
/**
 * PureClarity Core Model Product Feed Resource Model
 *
 * @title       Pureclarity_Core_Model_Resource_ProductFeed
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
class Pureclarity_Core_Model_Resource_ProductFeed extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('pureclarity_core/pureclarity_productfeed', 'id');
    }

}