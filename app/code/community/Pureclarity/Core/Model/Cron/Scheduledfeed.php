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
 * @copyright Copyright (c) 2019 PureClarity Technologies Ltd
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *****************************************************************************************/

/**
 * Controls running of PureClarity manual feeds
 */
class Pureclarity_Core_Model_Cron_Scheduledfeed
{
    /**
     * Runs feeds that have been scheduled by a button press in admin
     * called via cron every minute (see /etc/config.xml)
     */
    public function execute()
    {
        $pcDir = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS;
        $scheduleFilePath = Pureclarity_Core_Helper_Data::getPureClarityBaseDir() . DS . 'scheduled_feed';
        
        $fileHandler = new Varien_Io_File();
        $fileHandler->open(array('path' => $pcDir));
        if ($fileHandler->fileExists($scheduleFilePath)) {
            $scheduleData = $fileHandler->read($scheduleFilePath);
            $scheduleData = $fileHandler->read($scheduleFilePath);
            $schedule = (array)json_decode($scheduleData);
            $fileHandler->rm($scheduleFilePath);
            if (!empty($schedule) && isset($schedule['store']) && isset($schedule['feeds'])) {
                $feedRunner = Mage::getModel('pureclarity_core/cron');
                $feedRunner->selectedFeeds($schedule['store'], $schedule['feeds']);
            }
        }
    }
    
}
