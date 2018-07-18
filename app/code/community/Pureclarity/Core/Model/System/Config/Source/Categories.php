<?php

class Pureclarity_Core_Model_System_Config_Source_Categories
{

    protected $categories = [
        [
            "label" => "  ",
            "value" => "-1"
        ]
    ];

    public function buildCategories()
    {
        $rootCategories = [];
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    if (!in_array($store->getRootCategoryId(), $rootCategories)){
                        $rootCategories[] = $store->getRootCategoryId();
                        $this->GetSubGategories($store->getRootCategoryId());
                    }
                }
            }
        }
    }

    function GetSubGategories($id, $prefix = '') {

        $category = Mage::getModel('catalog/category')->load($id);
        $label = $prefix . $category->getName();
        $this->categories[] = [
            "value" => $category->getId(),
            "label" => $label
        ];
        $subcategories = $category->getChildrenCategories();
        foreach($subcategories as $subcategory) {
            $this->GetSubGategories($subcategory->getId(), $label . ' -> ');
        }
    }

    public function toOptionArray()
    {
        $this->buildCategories();
        return $this->categories;
    }


}