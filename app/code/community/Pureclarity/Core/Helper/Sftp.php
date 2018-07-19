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

set_include_path(get_include_path() . PATH_SEPARATOR . Mage::getBaseDir('lib') . '/phpseclib107');
require_once('Net/SFTP.php');

class Pureclarity_Core_Helper_Sftp
{
    const LOG_FILE = "pureclarity_sftp.log";
    
    public function send($host, $port, $username, $password, $filename, $payload, $directory = null)
    {
        $path = '/' . ($directory?$directory.'/':'') . $filename;
        try {
            $sftp = new Net_SFTP($host, $port, 10);
            if (!$sftp->login($username, $password)){
                throw new Exception(sprintf(__("Unable to open SFTP connection as %s@%s:%s", $username, $host, $port)));
            }
            $sftp->put($path, $payload, NET_SFTP_LOCAL_FILE);
            $sftp->disconnect();
            return true;
        } catch(Exception $e) {
            Mage::log("ERROR: Processing SFTP transfer: " . $src, null, self::LOG_FILE);
            Mage::logException($e);
            $sftp->disconnect();
            return false;
        }
        
    }
}
