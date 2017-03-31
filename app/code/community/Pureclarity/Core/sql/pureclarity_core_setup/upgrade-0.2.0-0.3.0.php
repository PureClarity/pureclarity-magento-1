<?php
/**
 * PureClarity Product Delta Table Setup
 *
 * @title       Pureclarity_Core_Sql
 * @category    Pureclarity
 * @package     Pureclarity_Core
 * @author      Douglas Radburn <douglas.radburn@purenet.co.uk>
 * @copyright   Copyright (c) 2016 Purenet http://www.purenet.co.uk
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$table = $installer->getTable('pureclarity_core/pureclarity_productfeed');

if ($installer->tableExists($table)) {
    $installer->getConnection()->dropTable($table);
}

/** @var $ddlTable Varien_Db_Ddl_Table */
$ddlTable = $installer->getConnection()->newTable($table);

$ddlTable->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'primary'  => true,
    'identity' => true,
    'unsigned' => true,
    'nullable' => false,
), 'Auto Increment ID')
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false
    ), 'Changed Product')
    ->addColumn('deleted', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => true
    ), 'Was this product Deleted?')
    ->addColumn('token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true
    ), 'Token')
    ->addColumn('status_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => true
    ), 'Status')
    ->addIndex(
        $installer->getIdxName($table, array('id')),
        array('id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    )
    ->setComment('PureClarity Delta Table');

$installer->getConnection()->createTable($ddlTable);

$installer->endSetup();