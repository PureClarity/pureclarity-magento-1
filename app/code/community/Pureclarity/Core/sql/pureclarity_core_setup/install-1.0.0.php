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

if ($installer->tableExists($table)) {
    $installer->getConnection()->dropTable($table);
}

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
    ->addColumn('token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => true
    ), 'Token')
    ->addColumn('status_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => true
    ), 'Status')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'nullable' => true
    ), 'Store Id')
    ->addIndex(
        $installer->getIdxName($table, array('id')),
        array('id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
    )
    ->setComment('PureClarity Delta Table');

$installer->getConnection()->createTable($ddlTable);


// // This upgrade script makes sure that the BMZ block has permission to be used in a CMS block
// // and that the secondary image attribute is available
// $getIsAllowed    = "SELECT `is_allowed` FROM `permission_block` WHERE `block_name`='pureclarity_core/bmz'";
// $createIsAllowed = "INSERT INTO `permission_block` (`block_name`, `is_allowed`) VALUES ('pureclarity_core/bmz', 1);";
// $setIsAllowed    = "UPDATE `permission_block` SET `is_allowed`=1 WHERE  `block_name`='pureclarity_core/bmz';";

// // Check the current state
// $allowed = $installer->getConnection()->fetchOne($getIsAllowed);
// if ($allowed == null){
//     // Create this record
//     $installer->run($createIsAllowed);
// }
// elseif ($allowed != "1"){
//     // Update the existing record so this is allowed.
//     $installer->run($setIsAllowed);
// }

// Make sure the attribute for the secondary image is added
$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'pureclarity_secondary_image', array(
    'group'         => 'General Information',
    'input'         => 'image',
    'type'          => 'varchar',
    'backend'       => 'catalog/category_attribute_backend_image',
    'label'         => 'Pureclarity Image',
    'visible'       => 1,
    'required'      => 0,
    'user_defined'  => 1,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE
));

// Add attribute for hiding product from recommenders
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



// $installer->run("
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Shop By Room (PureclarityBMZ)', 'shop_by_room_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-04\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-04\" pc_bmz_attrs=\"style=\'display:inline-block;width:50%;float:left\'\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"shop_by_room\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-05\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-05\" pc_bmz_attrs=\"style=\'display:inline-block;width:50%;float:left\'\" pc_bmz_is_desktop_only=\"true\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-06\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-06\" pc_bmz_attrs=\"style=\'display:inline-block;width:50%;float:left\'\" pc_bmz_is_desktop_only=\"true\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-07\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-07\" pc_bmz_attrs=\"style=\'display:inline-block;width:50%;float:left\'\" pc_bmz_is_desktop_only=\"true\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-04\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-04\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"shop_by_room\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-05\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-05\" pc_bmz_is_mobile_only=\"true\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-06\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-06\" pc_bmz_is_mobile_only=\"true\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-07\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-07\" pc_bmz_is_mobile_only=\"true\"}}\r\n<div style=\"clear:both\"></div>', '2016-12-22 15:00:43', '2017-01-04 16:46:52', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Shop By Style (Pureclarity BMZ)', 'room_style_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-08\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-08\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"room_style_PC_FALLBACK\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-08\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-08\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"room_style_PC_FALLBACK\"}}', '2016-12-22 16:28:56', '2017-01-21 16:24:23', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Shop by Brand (Pureclarity BMZ)', 'shop_by_brand_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-10\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-10\" pc_bmz_fallback_cms_block=\"shop_by_brand\"}}', '2016-12-22 16:35:37', '2016-12-28 15:49:17', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('News and Offers (Pureclarity BMZ)', 'news_and_offers_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-11\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-11\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-11\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-11\" pc_bmz_is_mobile_only=\"true\"}}', '2016-12-22 16:40:08', '2017-01-04 16:22:28', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Go Retro (Pureclarity BMZ)', 'go_retro_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-02\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"go_retro\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-02\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"go_retro\"}}', '2016-12-22 16:44:48', '2017-01-04 16:22:45', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Be Inspired (Pureclarity BMZ)', 'be_inspired_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-03\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"be_inspired\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HPM-03\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"be_inspired\"}}', '2016-12-22 16:45:21', '2017-01-04 16:22:48', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Selected for you (Pureclarity BMZ)', 'selected_for_you_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-HP-09\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"HP-09\"}}', '2016-12-28 09:27:53', '2016-12-28 15:50:22', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Default Pureclarity BMZ', 'default_pc_bmz', '<p>{{block type=\"pureclarity_core/bmz\" name=\"bmz-AA-00\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"AA-00\"}}</p>', '2016-12-28 15:48:19', '2017-01-25 13:31:46', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Subcategory Landing Page Header (Pureclarity BMZ)', 'subcat_landing_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-CP-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"CP-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-CPM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"CPM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-04 10:55:31', '2017-01-04 16:23:07', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Header Promotion (Pureclarity BMZ)', 'header_promotion_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-GH-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"GH-01\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"header_promotion\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-GHM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"GHM-01\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"header_promotion\"}}', '2017-01-04 13:48:09', '2017-01-04 16:23:22', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Interest Free (Pureclarity BMZ)', 'interest_free_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PL-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PL-02\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"interest_free\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-PLM-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PLM-02\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"interest_free\"}}', '2017-01-04 14:03:05', '2017-01-04 16:23:26', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Customer reviews (Pureclarity BMZ)', 'customer_reviews_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PL-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PL-03\" pc_bmz_is_desktop_only=\"true\" pc_bmz_fallback_cms_block=\"customer_reviews\"}}\r\n{{block type=\"pureclarity_core/bmz\" name=\"bmz-PLM-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PLM-03\" pc_bmz_is_mobile_only=\"true\" pc_bmz_fallback_cms_block=\"customer_reviews\"}}', '2017-01-04 14:04:05', '2017-01-04 16:23:40', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Product listing header (Pureclarity BMZ)', 'product_listing_header_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PL-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PL-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-PLM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PLM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-04 15:36:32', '2017-01-04 16:23:45', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Product listing footer (Pureclarity BMZ)', 'product_listing_footer_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PL-04\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PL-04\"}}', '2017-01-04 15:51:13', '2017-01-04 15:51:13', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Product Page Header (Pureclarity BMZ)', 'product_page_header_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PP-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PP-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-PPM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PPM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-05 12:39:52', '2017-01-05 12:39:52', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Product Page Footer (Pureclarity BMZ)', 'product_page_footer_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-PP-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PP-02\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-PP-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PP-03\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-PP-04\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"PP-04\"}}', '2017-01-05 12:43:16', '2017-01-05 12:43:16', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Basket page header (Pureclarity BMZ)', 'basket_page_header_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-BP-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"BP-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-BPM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"BPM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-05 12:56:13', '2017-01-05 12:56:13', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Basket page footer (Pureclarity BMZ)', 'basket_page_footer_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-BP-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"BP-02\"}}', '2017-01-05 13:02:33', '2017-01-05 13:02:33', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Interest Free - Search results page (Pureclarity BMZ)', 'interest_free_SR_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-SR-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SR-02\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-SRM-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SRM-02\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-04 14:03:05', '2017-01-05 14:27:18', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Customer reviews - Search results page (Pureclarity BMZ)', 'customer_reviews_SR_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-SR-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SR-03\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-SRM-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SRM-03\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-04 14:04:05', '2017-01-05 14:27:48', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Search results header (Pureclarity BMZ)', 'search_results_header_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-SR-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SR-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-SRM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"SRM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-05 14:35:43', '2017-01-05 14:35:43', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Shop By Style (Pureclarity Fallback wrapper)', 'room_style_PC_FALLBACK', '<p><span class=\"bg\">Shop By<span class=\"subheading\">Style</span></span></p>\r\n<p>{{widget type=\"cms/widget_block\" template=\"cms/widget/static_block/default.phtml\" block_id=\"36\"}}{{widget type=\"cms/widget_block\" template=\"cms/widget/static_block/default.phtml\" block_id=\"37\"}}</p>', '2017-01-21 15:43:52', '2017-01-21 16:44:27', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Homepage Banner (Pureclarity Fallback wrapper)', 'homepage_banner_PC_FALLBACK', '{{block type=\"easybanner/banner\" name=\"banner\" template=\"easybanner/main_banner.phtml\" banner_id=\"main_banner\" }}', '2017-01-21 16:54:51', '2017-01-21 16:54:51', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Checkout Success Header (Pureclarity BMZ)', 'checkout_success_header_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-OC-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OC-01\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-OCM-01\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OCM-01\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-25 13:29:01', '2017-01-25 13:29:01', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Checkout Success Footer (Pureclarity BMZ)', 'checkout_success_footer_1_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-OC-03\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OC-03\"}}', '2017-01-25 13:32:52', '2017-01-25 13:32:52', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Checkout Success Footer 2 (Pureclarity BMZ)', 'checkout_success_footer_2_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-OC-04\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OC-04\"}}', '2017-01-25 13:32:52', '2017-01-25 13:33:49', 1);
// INSERT INTO `cms_block` (`title`, `identifier`, `content`, `creation_time`, `update_time`, `is_active`) VALUES ('Checkout Success Sidebar (Pureclarity BMZ)', 'checkout_success_sidebar_PC_BMZ', '{{block type=\"pureclarity_core/bmz\" name=\"bmz-OC-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OC-02\" pc_bmz_is_desktop_only=\"true\"}}{{block type=\"pureclarity_core/bmz\" name=\"bmz-OCM-02\" template=\"pureclarity/BMZ/bmz.phtml\" pc_bmz_reference=\"OCM-02\" pc_bmz_is_mobile_only=\"true\"}}', '2017-01-25 13:32:52', '2017-01-25 13:47:48', 1);

// insert into cms_block_store(block_id, store_id) select block_id, 1 from cms_block where identifier like \"%_PC_%\";

// UPDATE `cms_page` SET `layout_update_xml`='<!-- Old easybanner block -->\r\n<!--\r\n<reference name=\"before_content_start\">\r\n	<block type=\"easybanner/banner\" name=\"banner\" template=\"easybanner/main_banner.phtml\">\r\n		<action method=\"setBannerId\"><banner_id>main_banner</banner_id></action>\r\n	</block>\r\n</reference>\r\n-->\r\n\r\n<reference name=\"before_content_start\">\r\n    <block type=\"pureclarity_core/bmz\" name=\"bmz-HP-01\" template=\"pureclarity/BMZ/bmz.phtml\">\r\n        <action method=\"setData\">\r\n            <name>pc_bmz_reference</name>\r\n            <value>HP-01</value>\r\n        </action>\r\n        <action method=\"setData\">\r\n            <name>pc_bmz_is_desktop_only</name>\r\n            <value>true</value>\r\n        </action>\r\n        <action method=\"setData\">\r\n          <name>pc_bmz_fallback_cms_block</name>\r\n          <value>homepage_banner_PC_FALLBACK</value>\r\n        </action>\r\n    </block>\r\n    <block type=\"pureclarity_core/bmz\" name=\"bmz-HPM-01\" template=\"pureclarity/BMZ/bmz.phtml\">\r\n        <action method=\"setData\">\r\n            <name>pc_bmz_reference</name>\r\n            <value>HPM-01</value>\r\n        </action>\r\n        <action method=\"setData\">\r\n            <name>pc_bmz_is_mobile_only</name>\r\n            <value>true</value>\r\n        </action>\r\n        <action method=\"setData\">\r\n          <name>pc_bmz_fallback_cms_block</name>\r\n          <value>homepage_banner_PC_FALLBACK</value>\r\n        </action>\r\n    </block>\r\n</reference>' WHERE  `page_id`=2;

// INSERT INTO `permission_block` (`block_name`, `is_allowed`) VALUES ('easybanner/banner', 1);

// ");

$installer->endSetup();