<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Vuleticd
 * @package     Vuleticd_Assist
 * @copyright   Copyright (c) 2015 Vuletic Dragan (http://www.vuleticd.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Vuleticd_Assist_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function debug($debugData, $level = null, $action = '' )
    {
        if (Mage::getStoreConfigFlag('payment/assist/debug')) {
            Mage::log($debugData, $level, 'assist' . ($action ? '-' . $action : '') . '.log', true);
        }
    }

    public function isOrderSecuredMd5()
    {
    	$secureOrderConfig = Mage::getStoreConfig('payment/assist/secure_order');
    	if (in_array($secureOrderConfig, array(1,3))) {
    		return true;
    	}
    	return false;
    }

    public function isOrderSecuredPgp()
    {
    	$secureOrderConfig = Mage::getStoreConfig('payment/assist/secure_order');
    	$merchantKey = Mage::getStoreConfig('payment/assist/merchant_key');
    	if (in_array($secureOrderConfig, array(2,3)) && $merchantKey) {
    		return true;
    	}
    	return false;
    }

    public function isResponseSecuredMd5()
    {
    	$secureResponseConfig = Mage::getStoreConfig('payment/assist/secure_response');
    	if ($secureResponseConfig == 1) {
    		return true;
    	}
    	return false;
    }

    public function isResponseSecuredPgp()
    {
    	$secureResponseConfig = Mage::getStoreConfig('payment/assist/secure_response');
    	$assistKey = Mage::getStoreConfig('payment/assist/assist_key');
    	if ($secureResponseConfig == 2 && $assistKey) {
    		return true;
    	}
    	return false;
    }

    public function isServiceSecured()
    {
    	$secureServiceConfig = Mage::getStoreConfig('payment/assist/secure_services');
    	$assistKey = Mage::getStoreConfig('payment/assist/assist_key');
    	if ($secureServiceConfig && $assistKey) {
    		return true;
    	}
    	return false;
    }
}