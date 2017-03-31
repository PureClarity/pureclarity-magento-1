<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

// This upgrade script makes sure that the BMZ block has permission to be used in a CMS block
// and that the secondary image attribute is available

$getIsAllowed    = "SELECT `is_allowed` FROM `permission_block` WHERE `block_name`='pureclarity_core/bmz'";
$createIsAllowed = "INSERT INTO `permission_block` (`block_name`, `is_allowed`) VALUES ('pureclarity_core/bmz', 1);";
$setIsAllowed    = "UPDATE `permission_block` SET `is_allowed`=1 WHERE  `block_name`='pureclarity_core/bmz';";

$installer->startSetup();
// Check the current state
$allowed = $installer->getConnection()->fetchOne($getIsAllowed);
if ($allowed == null){
    // Create this record
    $installer->run($createIsAllowed);
}
elseif ($allowed != "1"){
    // Update the existing record so this is allowed.
    $installer->run($setIsAllowed);
}

// Make sure the attribute for the secondary image is added
$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'pureclarity_secondary_image', array(
    'group'         => 'General Information',
    'input'         => 'image',
    'type'          => 'varchar',
    'backend'       => 'catalog/category_attribute_backend_image',
    'label'         => 'Pureclarity Secondary Image',
    'visible'       => 1,
    'required'      => 0,
    'user_defined'  => 1,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE
));

// Make sure the attribute for the secondary image is added
$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'pureclarity_hide_from_feed', array(
    'group'         => 'General Information',
    'input'         => 'select',
    'type'          => 'text',
    'backend'       => '',
    'source'        => 'eav/entity_attribute_source_boolean',
    'label'         => 'Hide from Pureclarity recommenders',
    'visible'       => 1,
    'required'      => 0,
    'user_defined'  => 1,
    'default'       => '0',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible_on_front' => true
));

$installer->endSetup();

