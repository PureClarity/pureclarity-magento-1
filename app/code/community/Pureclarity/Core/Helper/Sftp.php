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

class Pureclarity_Core_Helper_Sftp
{
    
    const LOG_FILE = "pureclarity_sftp.log";
    
    public function send($host, $port, $username, $password, $filename, $src)
    {
        $sftpDumpFile = new Varien_Io_Sftp();
        try {
            $sftp->open(
                array(
                    'host'      => $host.':'.$port,
                    'username'  => $username,
                    'password'  => $password
                )
            );
            $sftp->write($filename, $src);
            $sftp->close();
        } catch(Exception $e) {
            Mage::log("ERROR: Processing SFTP transfer: " . $src, null, self::LOG_FILE);
            Mage::logException($e);   
        }
    }



}
