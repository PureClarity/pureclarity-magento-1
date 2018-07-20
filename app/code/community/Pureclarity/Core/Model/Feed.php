<?php

class Pureclarity_Core_Model_Feed extends Mage_Core_Model_Abstract
{
    // Process the product feed and update the progress file, in page sizes of 20 (or other if overriden)
    public function processProductFeed($productExportModel, $progressFileName, $feedFile, $pageSize = 20)
    {
        $currentPage = 0;
        $pages = 0;
        $feedProducts = array();
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', 0, 1);

        fwrite($feedFile, '"Products":[');
        $firstProduct = true;
        do {
            $result = $productExportModel->getFullProductFeed($pageSize, $currentPage);
            $pages = $result["Pages"];

            foreach ($result["Products"] as $product) {
                $json = Mage::helper('pureclarity_core')->formatFeed($product, 'json');

                if (!$firstProduct) {
                    fwrite($feedFile, ',');
                }
                fwrite($feedFile, $json);
                $firstProduct = false;
            }

            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', $currentPage, $pages, "false");
            $currentPage++;
        } while ($currentPage <= $pages);

        fwrite($feedFile, ']');

        // fwrite($feedFile, '],"Pages":');
        // fwrite($feedFile, $pages);
        // fwrite($feedFile, '}');

        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'product', $currentPage, $pages, "true");
    }

    public function getFullCatFeed($progressFileName, $storeId)
    {
        $feedCategories = '"Categories":[';
        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->addUrlRewriteToResult();

        $maxProgress = count($categoryCollection);
        $currentProgress = 0;
        $isFirst = true;
        foreach ($categoryCollection as $category) {

            // Get image
            $firstImage = $category->getImageUrl();
            if ($firstImage != "") {
                $imageURL = $firstImage;
            } else {
                $imageURL = Mage::helper('pureclarity_core')->getCategoryPlaceholderUrl($storeId);
                if (!$imageURL) {
                    $imageURL = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                    if (!$imageURL) {
                        $imageURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . "frontend/base/default/images/pureclarity_core/PCPlaceholder250x250.jpg";
                    }
                }
            }
            $imageURL = str_replace(array("https:", "http:"), "", $imageURL);

            // Get Second Image
            $imageURL2 = null;
            $secondImage = $category->getData('pureclarity_secondary_image');
            if ($secondImage != "") {
                $imageURL2 = sprintf("%scatalog/category/%s", Mage::getBaseUrl('media'), $secondImage);
            } else {
                $imageURL2 = Mage::helper('pureclarity_core')->getSecondaryCategoryPlaceholderUrl($storeId);
                if (!$imageURL2) {
                    $imageURL2 = $this->getSkinUrl('images/pureclarity_core/PCPlaceholder250x250.jpg');
                }
            }
            $imageURL2 = str_replace(array("https:", "http:"), "", $imageURL2);

            if (!$category->getName()){
                continue;
            }

            // Build Data
            $categoryData = array(
                "Id" => $category->getId(),
                "DisplayName" => $category->getName(),
                "Image" => $imageURL,
                "Link" => sprintf("/%s", str_replace(Mage::getUrl('', array('_secure' => true)), '', $category->getUrl($category))),
            );

            // Check if to ignore this category in recommenders
            if ($category->getData('pureclarity_hide_from_feed') == '1') {
                $categoryData["ExcludeFromRecommenders"] = true;
            }

            //Check if category is active
            if (!$category->getIsActive()) {
                $categoryData["IsActive"] = false;
            }

            if ($category->getLevel() > 1) {
                $categoryData["ParentIds"] = array($category->getParentCategory()->getId());
            }

            if ($imageURL2 != null) {
                $categoryData["PCImage"] = $imageURL2;
            }

            if (!$isFirst) {
                $feedCategories .= ',';
            }

            $isFirst = false;
            $feedCategories .= Mage::helper('pureclarity_core')->formatFeed($categoryData, 'json');

            $currentProgress += 1;
            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'category', $currentProgress, $maxProgress, "false");
        }

        $feedCategories .= ']';
        return $feedCategories;
    }

    public function getFullBrandFeed($progressFileName, $storeId)
    {
        $feedBrands = '"Brands":[';        
        $brandCategoryId = Mage::helper('pureclarity_core')->getBrandParentCategory($storeId);

        if ($brandCategoryId && $brandCategoryId != "-1"){

            $category = Mage::getModel('catalog/category')->load($brandCategoryId);

            $subcategories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('image')
                ->addIdFilter($category->getChildren());

            $maxProgress = count($subcategories);
            $currentProgress = 0;
            $isFirst = true;
            foreach($subcategories as $subcategory) {
                $thisBrand = array(
                    "Id" => $subcategory->getId(),
                    "DisplayName" =>  $subcategory->getName()
                );
                
                $imageURL = $subcategory->getImageUrl();
                if ($imageURL){
                    $imageURL = str_replace(array("https:", "http:"), "", $imageURL);
                    $thisBrand['Image'] = $imageURL;
                }

                if (!$isFirst)
                    $feedBrands .= ',';
            
                $isFirst = false;
                $feedBrands .= Mage::helper('pureclarity_core')->formatFeed($thisBrand, 'json');
                $currentProgress += 1;
                Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'brand', $currentProgress, $maxProgress, "false");
            }
            $feedBrands .= ']';
            return $feedBrands;
        }
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'brand', 1, 1);
        return "";
        
    }

    function BrandFeedArray($storeId){
        $feedBrands = array();
        $brandCategoryId = Mage::helper('pureclarity_core')->getBrandParentCategory($storeId);

        if ($brandCategoryId && $brandCategoryId != "-1"){
            $category = Mage::getModel('catalog/category')->load($brandCategoryId);
            $subcategories = $category->getChildrenCategories();
            foreach($subcategories as $subcategory) {
                $feedBrands[$subcategory->getId()] = $subcategory->getName();
            }
            return $feedBrands;
        }
        return array();
        
    }

    public function UserFeed($progressFileName, $storeId)
    {

        $currentStore = Mage::getModel('core/store')->load($storeId);
        try {
            $customerCollection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToFilter("website_id", array("eq" => $currentStore->getWebsiteId()))
                ->addAttributeToSelect("*");
        } catch (\Exception $e) {
            Mage::log($e->getMessage());
        }

        $users = '"Users":[';

        $maxProgress = count($customerCollection);
        $currentProgress = 0;
        $isFirst = true;
        foreach ($customerCollection as $customer) {

            $data = [
                'UserId' => $customer->getId(),
                'Email' => $customer->getEmail(),
                'FirstName' => $customer->getFirstname(),
                'LastName' => $customer->getLastname(),
            ];
            if ($customer->getPrefix()) {
                $data['Salutation'] = $customer->getPrefix();
            }
            if ($customer->getDob()) {
                $data['DOB'] = $customer->getDob();
            }
            if ($customer->getGroupId() && $customerGroups[$customer->getGroupId()]) {
                $data['Group'] = $customerGroups[$customer->getGroupId()]['label'];
                $data['GroupId'] = $customer->getGroupId();
            }
            if ($customer->getGender()) {
                switch ($customer->getGender()) {
                    case 1: // Male
                        $data['Gender'] = 'M';
                        break;
                    case 2: // Female
                        $data['Gender'] = 'F';
                        break;
                }
            }

            $address = null;
            if ($customer->getDefaultShipping()) {
                $address = $customer->getAddresses()[$customer->getDefaultShipping()];
            } else if ($customer->getAddresses() && sizeof(array_keys($customer->getAddresses())) > 0) {
                $address = $customer->getAddresses()[array_keys($customer->getAddresses())[0]];
            }
            if ($address) {
                if ($address->getCity()) {
                    $data['City'] = $address->getCity();
                }

                if ($address->getRegion()) {
                    $data['State'] = $address->getRegion();
                }

                if ($address->getCountry()) {
                    $data['Country'] = $address->getCountry();
                }

            }

            if (!$isFirst) {
                $users .= ',';
            }

            $isFirst = false;

            $users .= Mage::helper('pureclarity_core')->formatFeed($data, 'json');

            $currentProgress += 1;
            Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'user', $currentProgress, $maxProgress, "false");
        }
        $users .= ']';
        return $users;
    }

    // Process the Order History feed
    public function OrderFeed($storeId, $progressFileName, $orderFilePath)
    {

        // Open the file
        $orderFile = @fopen($orderFilePath, "w+");

        // Write the header
        fwrite($orderFile, "OrderId,UserId,Email,DateTimeStamp,ProdCode,Quantity,UnityPrice,LinePrice" . PHP_EOL);

        if ((!$orderFile) || !flock($orderFile, LOCK_EX | LOCK_NB)) {
            throw new Exception("Error: Cannot open feed file for writing under var/pureclarity directory. It could be locked or there maybe insufficient permissions to write to the directory. You must delete locked files and ensure PureClarity has permission to write to the var directory. File: " . $orderFilePath);
        }

        // Get the collection
        $fromDate = date('Y-m-d H:i:s', strtotime("-6 month"));
        $toDate = date('Y-m-d H:i:s', strtotime("now"));

        $orderCollection = Mage::getModel("sales/order")
            ->getCollection()
            ->addAttributeToFilter('store_id', $storeId)
            ->addAttributeToFilter('created_at', array('from'=>$fromDate, 'to'=>$toDate))
            ->addAttributeToFilter('status', array('eq' => 'complete'));

        // Set size and initiate vars
        $maxProgress = count($orderCollection);

        $currentProgress = 0;
        $counter = 0;
        $data = "";

        // Reset Progress file
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'orders', 0, 1, "false");

        // Build Data
        foreach ($orderCollection as $orderData) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderData->getIncrementId());

            if ($order) {
                $id = '"' . $order->getIncrementId() . '"';

                $customerId = $order->getCustomerId();
                if ($customerId) {
                    $customerId =  '"' . $customerId . '"';
                    $email = $order->getCustomerEmail();
                    $date = $order->getCreatedAt();

                    $orderItems = $orderData->getAllVisibleItems();
                    foreach ($orderItems as $item) {
                        $productId = $item->getProductId();
                     
                        $product = Mage::getModel('catalog/product')->load($productId);
                        if (!$product){
                            continue;
                        }
                        $sku = $product->getData('sku');
                        if (!$sku){
                            continue;
                        }
                        $quantity = $item->getQtyOrdered();
                        $price = $item->getPriceInclTax();
                        $linePrice = $item->getRowTotalInclTax();
                        if ($price > 0 && $linePrice > 0) {
                            $data .= "$id,$customerId,$email,$date,$sku,$quantity,$price,$linePrice" . PHP_EOL;
                        }
                    }
                    $counter += 1;
                }
            }
            // Incremement counters
            $currentProgress += 1;

            if ($counter >= 10) {
                // Every 10, write to the file.
                fwrite($orderFile, $data);
                $data = "";
                $counter = 0;
                Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'orders', $currentProgress, $maxProgress, "false");
            }
        }

        // Final write
        fwrite($orderFile, $data);
        fclose($orderFile);
        Mage::helper('pureclarity_core')->setProgressFile($progressFileName, 'orders', 1, 1, "true");
    }
}
