<?php

class Pureclarity_Core_Model_Feed extends Pureclarity_Core_Model_Model
{
    protected $accessKey;
    protected $secretKey;
    protected $storeId;
    protected $progressFileName;
    protected $problemFeeds = array();

    private $uniqueId;

    const FEED_TYPE_BRAND = "brand";
    const FEED_TYPE_CATEGORY = "category";
    const FEED_TYPE_PRODUCT = "product";
    const FEED_TYPE_ORDER = "orders";
    const FEED_TYPE_USER = "user";

    public function __construct()
    {
        $this->progressFileName = Pureclarity_Core_Helper_Data::getProgressFileName();
        parent::__construct();
    }

    /**
     * Process the product feed and update the progress file, in page sizes 
     * of 100 by default
     * @param $pageSize integer
     */
    function sendProducts($pageSize = 100)
    {
        try{
            if (!$this->isInitialised()) {
                return false;
            }
            
            Mage::log("PureClarity: In Feed->sendProducts()");
            $productExportModel = Mage::getModel('pureclarity_core/productExport');
            Mage::log("PureClarity: In Feed->sendProducts(): Got the product export model, about to initialise");
            Mage::log("PureClarity: In Feed->sendProducts(): Store id is " . $this->storeId);
            $productExportModel->init($this->storeId);
            Mage::log("PureClarity: In Feed->sendProducts(): Initialised ProductExport");

            $currentPage = 1;
            $pages = 0;
            Mage::log("PureClarity: Set progress");

            // loop through products, POSTing string for each page as it loops through
            $writtenProduct = false;
            
            do {
                $result = $productExportModel->getFullProductFeed($pageSize, $currentPage);
                
                if (!empty($result["Products"])) {
                    if (!$writtenProduct) {
                        $this->start(self::FEED_TYPE_PRODUCT);
                    }
                    
                    Mage::log("PureClarity: Got result from product export model");

                    $pages = $result["Pages"];
                    Mage::log("PureClarity: {$pages} pages");

                    $json = (!$writtenProduct ? ',"Products":[' : "");
                    foreach ($result["Products"] as $product) {
                        if ($writtenProduct) { 
                            $json .= ',';
                        }

                        $writtenProduct = true;
                        $json .= $this->coreHelper->formatFeed($product, 'json');
                    }

                    $parameters = $this->getParameters($json, self::FEED_TYPE_PRODUCT);

                    if ($writtenProduct) {
                        $this->send("feed-append", $parameters);
                    }
                    
                    $this->coreHelper->setProgressFile(
                        $this->progressFileName, self::FEED_TYPE_PRODUCT, $currentPage, $pages
                    );
                }
                
                $currentPage++;
            } while ($currentPage <= $pages);
        
            $this->endFeedAppend(self::FEED_TYPE_PRODUCT, $writtenProduct);
            
            if ($writtenProduct) {
                $this->end(self::FEED_TYPE_PRODUCT);
            } else {
                Mage::log("PureClarity: Could not find any product to upload");
            }

            Mage::log("PureClarity: Finished sending product data");
        }
        catch(\Exception $e) {
            Mage::log("PureClarity: In Feed->sendProducts(): Exception caught: " . $e->getMessage());
        }
    }

    public function sendCategories()
    {
        if (! $this->isInitialised()) {
            return false;
        }

        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($this->storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->addAttributeToSelect('pureclarity_secondary_image')
            ->addAttributeToSelect('pureclarity_hide_from_feed')
            ->addUrlRewriteToResult();

        $maxProgress = count($categoryCollection);
        $currentProgress = 0;
        $writtenCategories = false;
        
        Mage::log("There are {$maxProgress} categories");

        if ($maxProgress > 0) {
            $this->start(self::FEED_TYPE_CATEGORY);
            
            foreach ($categoryCollection as $category) {
                $currentProgress++;

                if (! $category->getName()) {
                    continue;
                }

                $feedCategories = (!$writtenCategories ? ',"Categories":[' : "");


                // Get first image
                $categoryImage = $category->getImageUrl();
                if ($categoryImage != "") {
                    $categoryImageUrl = $categoryImage;
                } else {
                    $categoryImageUrl = $this->coreHelper->getCategoryPlaceholderUrl($this->storeId);
                    if (! $categoryImageUrl) {
                        $categoryImageUrl = $this->getSkinUrl(self::PLACEHOLDER_IMAGE_URL);
                        if (! $categoryImageUrl) {
                            $categoryImageUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) 
                                              . "frontend/base/default/" 
                                              . self::PLACEHOLDER_IMAGE_URL;
                        }
                    }
                }

                $categoryImageUrl = $this->removeUrlProtocol($categoryImageUrl);

                // Get override image
                $overrideImageUrl = null;
                $overrideImage = $category->getData('pureclarity_secondary_image');
                Mage::log("Override image: " . $overrideImage);
                if ($overrideImage != "") {
                    $overrideImageUrl = sprintf("%scatalog/category/%s", Mage::getBaseUrl('media'), $overrideImage);
                } else {
                    $overrideImageUrl = $this->coreHelper->getSecondaryCategoryPlaceholderUrl($this->storeId);
                    if (! $overrideImageUrl) {
                        $overrideImageUrl = $this->getSkinUrl(self::PLACEHOLDER_IMAGE_URL);
                    }
                }

                $overrideImageUrl = $this->removeUrlProtocol($overrideImageUrl);

                // Build data
                $categoryData = array(
                    "Id" => $category->getId(),
                    "DisplayName" => $category->getName(),
                    "Image" => $categoryImageUrl,
                    "Link" => sprintf(
                        "/%s", str_replace(
                            Mage::getUrl(
                                '', array(
                                '_secure' => true
                                )
                            ), '', $category->getUrl($category)
                        )
                    ),
                );

                if ($category->getLevel() > 1) {
                    $categoryData["ParentIds"] = array(
                            $category->getParentCategory()->getId()
                        );
                }

                // Check whether to ignore this category in recommenders
                if ($category->getData('pureclarity_hide_from_feed') == '1') {
                    $categoryData["ExcludeFromRecommenders"] = true;
                }

                // Check if category is active
                if (! $category->getIsActive()) {
                    $categoryData["IsActive"] = false;
                }

                if ($overrideImageUrl != null) {
                    $categoryData["OverrideImage"] = $overrideImageUrl;
                }

                if ($writtenCategories) {
                    $feedCategories .= ',';
                }

                $writtenCategories = true;

                $feedCategories .= $this->coreHelper->formatFeed($categoryData, 'json');

                $parameters = $this->getParameters($feedCategories, self::FEED_TYPE_CATEGORY);
                $this->send("feed-append", $parameters);

                $this->coreHelper->setProgressFile(
                    $this->progressFileName, self::FEED_TYPE_CATEGORY, $currentProgress, $maxProgress
                );
            }
            
            $this->endFeedAppend(self::FEED_TYPE_CATEGORY, $writtenCategories);
            Mage::log("In Feed.php->sendCategories(): about to call end()");
            $this->end(self::FEED_TYPE_CATEGORY);
        } else {
            Mage::log("PureClarity: Could not find any product categories to upload.");
        }
    }

    /**
     * Sends brands feed.
     */
    function sendBrands()
    {
        if (! $this->isInitialised()) {
            return false;
        }

        Mage::log("PureClarity: In Feed->sendBrands()");

        $brandParentCategoryId = $this->coreHelper->getBrandParentCategory($this->storeId);

        if ($brandParentCategoryId && $brandParentCategoryId != "-1") {
            $brandParentCategory = Mage::getModel('catalog/category')->load($brandParentCategoryId);

            $brands = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('image')
                ->addAttributeToSelect('pureclarity_secondary_image')
                ->addAttributeToSelect('pureclarity_hide_from_feed')
                ->addIdFilter($brandParentCategory->getChildren());

            $maxProgress = count($brands);
            $currentProgress = 0;
            $writtenBrands = false;
            if ($maxProgress > 0) {
                $this->start(self::FEED_TYPE_BRAND);
                
                foreach ($brands as $brand) {
                    $feedBrands = (!$writtenBrands ? ',"Brands":[' : "");
                    
                    $brandData = array(
                        "Id" => $brand->getId(),
                        "DisplayName" =>  $brand->getName()
                    );
                    
                    $imageUrl = $brand->getImageUrl();
                    if ($imageUrl) {
                        $brandData['Image'] = $this->removeUrlProtocol($imageUrl);
                    }

                    // Get override image
                    $overrideImageUrl = null;
                    $overrideImage = $brand->getData('pureclarity_secondary_image');
                    Mage::log("Override image: " . $overrideImage);
                    if ($overrideImage != "") {
                        $overrideImageUrl = sprintf("%scatalog/category/%s", Mage::getBaseUrl('media'), $overrideImage);
                    } else {
                        $overrideImageUrl = $this->coreHelper->getSecondaryCategoryPlaceholderUrl($this->storeId);
                        if (! $overrideImageUrl) {
                            $overrideImageUrl = $this->getSkinUrl(self::PLACEHOLDER_IMAGE_URL);
                        }
                    }

                    $overrideImageUrl = $this->removeUrlProtocol($overrideImageUrl);

                    if ($overrideImageUrl != null) {
                        $brandData["OverrideImage"] = $overrideImageUrl;
                    }

                    $brandData["Link"] = $this->removeUrlProtocol($brand->getUrl($brand));

                    // Check whether to ignore this brand in recommenders
                    if ($brand->getData('pureclarity_hide_from_feed') == '1') {
                        $brandData["ExcludeFromRecommenders"] = true;
                    }

                    if ($writtenBrands) {
                        $feedBrands .= ',';
                    }

                    $writtenBrands = true;
                    $feedBrands .= $this->coreHelper->formatFeed($brandData, 'json');
                    $currentProgress++;

                    $parameters = $this->getParameters($feedBrands, self::FEED_TYPE_BRAND);
                    
                    $this->send("feed-append", $parameters);  
                    
                    $this->coreHelper->setProgressFile(
                        $this->progressFileName, self::FEED_TYPE_BRAND, $currentProgress, $maxProgress
                    );
                }

                $this->endFeedAppend(self::FEED_TYPE_BRAND, $writtenBrands);
                $this->end(self::FEED_TYPE_BRAND);
            } else {
                Mage::log("PureClarity: Could not find any brands to upload.");
            }
        } else {
            $this->coreHelper->setProgressFile($this->progressFileName, self::FEED_TYPE_BRAND, 1, 1);
        }
    }

    function getBrandFeedArray($storeId)
    {
        Mage::log("PureClarity: In Feed->getBrandFeedArray()");
        $feedBrands = array();
        $brandParentCategoryId = $this->coreHelper->getBrandParentCategory($storeId);

        if ($brandParentCategoryId && $brandParentCategoryId != "-1") {
            Mage::log("PureClarity: In Feed->getBrandFeedArray(): got brandParentCategoryId " . $brandParentCategoryId);
            $brandParentCategory = Mage::getModel('catalog/category')->load($brandParentCategoryId);
            $brands = $brandParentCategory->getChildrenCategories();
            foreach ($brands as $brand) {
                $feedBrands[$brand->getId()] = $brand->getName();
            }
        }

        Mage::log("PureClarity: In Feed->getBrandFeedArray(): about to return brands array");
        return $feedBrands;
    }

    /**
     * Sends users feed
     */
    function sendUsers()
    {
        if (! $this->isInitialised()) {
            return false;
        }

        Mage::log("PureClarity: In Feed->sendUsers()");
        $currentStore = Mage::getModel('core/store')->load($this->storeId);

        try {
            $customerCollection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToFilter(
                    "website_id", array(
                        "eq" => $currentStore->getWebsiteId()
                    )
                )
                ->addAttributeToSelect("*");
        } 
        catch (\Exception $e) {
            Mage::log($e->getMessage());
        }

        $maxProgress = count($customerCollection);
        $currentProgress = 0;
        $writtenCustomers = false;
        Mage::log("PureClarity: {$maxProgress} users");
        if ($maxProgress > 0) {
            $this->start(self::FEED_TYPE_USER);
            
            foreach ($customerCollection as $customer) {
                $users = (!$writtenCustomers ? ',"Users":[' : "");

                $data = array(
                    'UserId' => $customer->getId(),
                    'Email' => $customer->getEmail(),
                    'FirstName' => $customer->getFirstname(),
                    'LastName' => $customer->getLastname(),
                );
                
                if ($customer->getPrefix()) {
                    $data['Salutation'] = $customer->getPrefix();
                }

                if ($customer->getDob()) {
                    $data['DOB'] = $customer->getDob();
                }

                if ($customer->getGroupId()) {
                    $customerGroup = Mage::getModel('customer/group')
                        ->load($customer->getGroupId());
                    if ($customerGroup) {
                        $data['Group'] = $customerGroup->getCustomerGroupCode();
                        $data['GroupId'] = $customer->getGroupId();
                    }
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
                } elseif ($customer->getAddresses() && count(array_keys($customer->getAddresses())) > 0) {
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

                if ($writtenCustomers) {
                    $users .= ',';
                }

                $writtenCustomers = true;

                $users .= $this->coreHelper->formatFeed($data, 'json');

                $currentProgress++;

                $parameters = $this->getParameters($users, self::FEED_TYPE_USER);
                $this->send("feed-append", $parameters);

                $this->coreHelper->setProgressFile($this->progressFileName, self::FEED_TYPE_USER, $currentProgress, $maxProgress);
            }

            $this->endFeedAppend(self::FEED_TYPE_USER, $writtenCustomers);
            if ($writtenCustomers) {
                $this->end(self::FEED_TYPE_USER);
            }
        } 
    }

    /**
     * Sends orders feed.
     */
    function sendOrders()
    {
        if (! $this->isInitialised()) {
            return false;
        }

        Mage::log("PureClarity: In Feed->sendOrders()");

        // Get the collection
        $fromDate = date('Y-m-d H:i:s', strtotime("-12 month"));
        $toDate = date('Y-m-d H:i:s', strtotime("now"));
        Mage::log("PureClarity: About to initialise orderCollection");
        $orderCollection = Mage::getModel("sales/order")
            ->getCollection()
            ->addAttributeToFilter('store_id', $this->storeId)
            ->addAttributeToFilter(
                'created_at', array(
                    'from' => $fromDate, 
                    'to' => $toDate
                )
            )
            ->addAttributeToFilter(
                'status', array(
                    'eq' => Mage_Sales_Model_Order::STATE_COMPLETE
                )
            );
            
        Mage::log("PureClarity: Initialised orderCollection");

        // Set size and initiate vars
        $maxProgress = count($orderCollection);
        $currentProgress = 0;
        $counter = 0;
        $data = "";
        
        Mage::log("PureClarity: {$maxProgress} items");

        if ($maxProgress > 0) {
            $this->start(self::FEED_TYPE_ORDER, true);
            // Reset Progress file
            $this->coreHelper->setProgressFile($this->progressFileName, self::FEED_TYPE_ORDER, 0, 1);
            
            // Build Data
            foreach ($orderCollection as $orderData) {
                $order = Mage::getModel('sales/order')
                    ->loadByIncrementId($orderData->getIncrementId());
                if ($order) {
                    $id = $order->getIncrementId();
                    $customerId = $order->getCustomerId();
                    if ($customerId) {
                        $email = $order->getCustomerEmail();
                        $date = $order->getCreatedAt();

                        $orderItems = $orderData->getAllVisibleItems();
                        foreach ($orderItems as $item) {
                            $productId = $item->getProductId();
                            $product = Mage::getModel('catalog/product')->load($productId);
                            if (! $product) {
                                continue;
                            }

                            $sku = $product->getData('sku');
                            if (! $sku) {
                                continue;
                            }

                            $quantity = $item->getQtyOrdered();
                            $price = $item->getPriceInclTax();
                            $linePrice = $item->getRowTotalInclTax();
                            if ($price > 0 && $linePrice > 0) {
                                $data .= "{$id},{$customerId},{$email},{$date},{$sku}" 
                                      .  ",{$quantity},{$price},{$linePrice}" . PHP_EOL;
                            }
                        }

                        $counter ++;
                    }
                }

                // Increment counters
                $currentProgress ++;

                // latter to ensure something comes through, if historic orders less than 10 we'll still get a feed
                if ($counter >= 10 || $maxProgress < 10) {
                    // Every 10, send the data
                    $parameters = $this->getParameters($data, self::FEED_TYPE_ORDER);
                    $this->send("feed-append", $parameters);
                    $data = "";
                    $counter = 0;
                    $this->coreHelper->setProgressFile(
                        $this->progressFileName, self::FEED_TYPE_ORDER, $currentProgress, $maxProgress
                    );
                }
            }
            
            // check if there any leftovers to send
            if ($counter > 0) {
                $parameters = $this->getParameters($data, self::FEED_TYPE_ORDER);
                $this->send("feed-append", $parameters);
                $this->coreHelper->setProgressFile(
                    $this->progressFileName, self::FEED_TYPE_ORDER, $currentProgress, $maxProgress
                );
            }
            
            $this->end(self::FEED_TYPE_ORDER, true);
        }
        
        Mage::log("PureClarity: Finished sending order data");
    }

    /**
     * Starts the feed by sending first bit of data to feed-create end point. For orders,
     * sends first row of CSV data, otherwise sends opening string of json.
     * @param $feedType string One of the Feed::FEED_TYPE_... constants
     */
    protected function start($feedType) 
    {
        $this->coreHelper->setProgressFile($this->progressFileName, $feedType, 0, 1);

        if ($feedType == self::FEED_TYPE_ORDER) {
            $startJson = "OrderId,UserId,Email,DateTimeStamp,ProdCode,Quantity,UnityPrice,LinePrice" . PHP_EOL;
        } else {
            $startJson = '{"Version": 2';
        }

        $parameters = $this->getParameters($startJson, $feedType, true);
        $this->send("feed-create", $parameters);
        Mage::log("PureClarity: Started feed");
    }

    /**
     * End the feed by sending any closing data to the feed-close end point. For order feeds,
     * no closing data is sent, the end point is simply called. For others, it's simply a closing
     * bracket.
     * @param $feedType string One of the Feed::FEED_TYPE_... constants
     */
    protected function end($feedType) 
    {
        $data = ( $feedType == self::FEED_TYPE_ORDER ? '' : '}' );
        $this->send("feed-close", $this->getParameters($data, $feedType));
        // Ensure progress file is set to complete
        $this->coreHelper->setProgressFile($this->progressFileName, 'N/A', 1, 1, "true", "false");
    }

    protected function endFeedAppend($feedType, $hasSentItemData)
    {
        /*
         * Close the array if we've had at least one unit of data
         */    
        if ($hasSentItemData) {
            $parameters = $this->getParameters(']', $feedType);
            $this->send("feed-append", $parameters);
        }
    }

    /**
     * Sends the data to the specified end point, i.e. sends feed to PureClarity
     * @param $endPoint string
     * @param $parameters array
     */
    protected function send($endPoint, $parameters)
    {
        
        $url = $this->coreHelper->getFeedBaseUrl($this->storeId) . $endPoint;
        
        Mage::log("PureClarity: About to send data to {$url}: " . print_r($parameters, true));

        $postFields = http_build_query($parameters);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 10000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if (! empty($postFields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt(
                $ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/x-www-form-urlencoded', 
                    'Content-Length: ' . strlen($postFields)
                )
            );
        } 
        else {
            curl_setopt($ch, CURLOPT_POST, false);
        }

        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Mage::log('PureClarity: Error: ' . curl_error($ch));
            $feedTypeParts = explode("-", $parameters['feedName']);
            $feedType = $feedTypeParts[0];
            if(! in_array($feedType, $this->problemFeeds)){
                $this->problemFeeds[] = $feedType;
            }
        }

        curl_close($ch);
    
        Mage::log("PureClarity: Response: " . print_r($response, true));
        Mage::log("PureClarity: At end of send");
    }

    /**
     * Returns parameters ready for POSTing. A unique id is added to the feed type
     * so that each feed request is always treated uniquely on the server. For example,
     * you could have two people initialising feeds at the same time, which would otherwise
     * cause overlapping, corrupted data.
     * @param $data string
     * @param $feedType string One of Feed::FEED_TYPE... constants
     */
    protected function getParameters($data, $feedType, $debugData = false)
    {
        if (! $this->isInitialised()) {
            return false;
        }

        $parameters = array(
            "accessKey" => $this->accessKey,
            "secretKey" => $this->secretKey,
            "feedName" => $feedType . "-" . $this->getUniqueId()
        );

        if (! empty($data)) {
            $parameters["payLoad"] = $data;
        }

        if($debugData) {
            $parameters["php"] = phpversion();
            $parameters["browser"] = $_SERVER['HTTP_USER_AGENT'];
        }

        return $parameters;
    }

    private function getUniqueId()
    {
        if(is_null($this->uniqueId)){
            $this->uniqueId = uniqid();
        }

        return $this->uniqueId;
    }

    /**
     * Initialises Feed with store id. Call after creating via factory.
     * @param $storeId integer
     */
    public function initialise($storeId)
    {
        $this->storeId = $storeId;
        $this->accessKey = $this->coreHelper->getAccessKey($this->storeId);
        $this->secretKey = $this->coreHelper->getSecretKey($this->storeId);
        if (empty($this->accessKey) || empty($this->secretKey)) {
            $this->coreHelper->setProgressFile($this->progressFileName, 'N/A', 1, 1, "false", "false", "", "Access Key and Secret Key must be set.");
            return false;
        }

        return $this;
    }

    /**
     * Returns true if Feed has been correctly initialised. storeId needs
     * to be set on instantiation, access and secret keys need to be set 
     * in Magento.
     * @return boolean
     */
    protected function isInitialised()
    {
        if (empty($this->accessKey) 
            || empty($this->secretKey)
            || empty($this->storeId)
            ) {
                if (empty($this->accessKey) 
                    || empty($this->secretKey)) {
                        Mage::log("PureClarity: No access key or secret key, call initialise() on Model/Feed.php");
                }

                if (empty($this->storeId)) {
                        Mage::log("PureClarity: No store id, call initialise() on Model/Feed.php");
                }

                return false;
        }
        else{
            return true;
        }
    }

    /**
     * Checks whether the POSTing of feeds has been successful and displays
     * appropriate message
     */
    public function checkSuccess()
    {
        $problemFeedCount = count($this->problemFeeds);
        Mage::log("Problem feeds count: " . $problemFeedCount);
        if ($problemFeedCount){
            $errorMessage = "There was a problem uploading the ";
            $counter = 1;
            foreach ($this->problemFeeds as $problemFeed) {
                $errorMessage .= $problemFeed;
                if ($counter < ($problemFeedCount - 1) && $problemFeedCount !== 2) {
                    $errorMessage .= ", ";
                } elseif ($problemFeedCount >= 2 && $counter == ($problemFeedCount - 1)) {
                    $errorMessage .= " and ";
                }

                $counter++;
            }

            $errorMessage .= " feed" . ($problemFeedCount > 1 ? "s" : "");
            $errorMessage .= ". Please see error logs for more information.";
            $this->coreHelper->setProgressFile($this->progressFileName, 'N/A', 1, 1, "true", "false", $errorMessage);
        }
        else {
            // Set to uploaded
            $this->coreHelper->setProgressFile($this->progressFileName, 'N/A', 1, 1, "true", "true");
        }
    }
}
