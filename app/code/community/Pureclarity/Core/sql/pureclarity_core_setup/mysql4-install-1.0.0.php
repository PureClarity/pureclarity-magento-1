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


$installer = $this;

$installer->startSetup();

// Create Product Feed Table
$table = $installer->getTable('pureclarity_core/pureclarity_productfeed');


$installer->run("
    DROP TABLE IF EXISTS {$table};
    CREATE TABLE `{$table}` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Auto Increment ID',
        `product_id` varchar(255) NOT NULL COMMENT 'Changed Product',
        `oldsku` varchar(255) DEFAULT NULL COMMENT 'oldsku',
        `token` varchar(255) DEFAULT NULL COMMENT 'Token',
        `status_id` smallint(6) DEFAULT NULL COMMENT 'Status',
        `store_id` smallint(6) DEFAULT NULL COMMENT 'Store Id',
        PRIMARY KEY (`id`),
        UNIQUE KEY `IDX_PURECLARITY_PRODUCTFEED_ID` (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='PureClarity Delta Table';
");



// adding attribute group
$installer->addAttributeGroup(Mage_Catalog_Model_Category::ENTITY, 'Default', 'PureClarity', 1000);

// Make sure the attribute for the category override image is added
$installer->addAttribute(
    Mage_Catalog_Model_Category::ENTITY, 
    'pureclarity_secondary_image', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'image',
        'type'          => 'varchar',
        'backend'       => 'catalog/category_attribute_backend_image',
        'label'         => 'Override image',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE
    )
);

// Add attribute for hiding product from recommenders
$installer->addAttribute(
    Mage_Catalog_Model_Category::ENTITY, 
    'pureclarity_hide_from_feed', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'select',
        'type'          => 'text',
        'backend'       => '',
        'source'        => 'eav/entity_attribute_source_boolean',
        'label'         => 'Exclude from recommenders',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '0',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);

// adding attribute group
$installer->addAttributeGroup(Mage_Catalog_Model_Product::ENTITY, 'Default', 'PureClarity', 1000);

// Add attribute for Search Tags
$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_search_tags', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'text',
        'type'          => 'text',
        'label'         => 'Search tags',
        'backend'       => '',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);

// Add attribute for exluding product from recommenders
$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_exc_rec', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'select',
        'type'          => 'text',
        'label'         => 'Exclude from recommenders',
        'backend'       => '',
        'source'        => 'eav/entity_attribute_source_boolean',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '0',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);

$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_newarrival', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'select',
        'type'          => 'text',
        'label'         => 'New arrival',
        'backend'       => '',
        'source'        => 'eav/entity_attribute_source_boolean',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '0',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);

$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_onoffer', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'select',
        'type'          => 'text',
        'label'         => 'On offer',
        'backend'       => '',
        'source'        => 'eav/entity_attribute_source_boolean',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '0',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);

// Add option for Image Overlay
$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_overlay_image', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'image',
        'type'          => 'varchar',
        'backend'       => 'pureclarity_core/product_attribute_backend_image',
        'input_renderer'=> 'pureclarity_core/adminhtml_product_image',
        'label'         => 'Overlay Image',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE
    )
);

// Add attribute for Promo Message
$installer->addAttribute(
    Mage_Catalog_Model_Product::ENTITY, 
    'pureclarity_promo_message', 
    array(
        'group'         => 'PureClarity',
        'input'         => 'text',
        'type'          => 'text',
        'label'         => 'Promotion Message',
        'backend'       => '',
        'visible'       => 1,
        'required'      => 0,
        'user_defined'  => 1,
        'default'       => '',
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible_on_front' => true
    )
);



$installer->endSetup();