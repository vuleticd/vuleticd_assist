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
class Vuleticd_Assist_Model_System_Config_Source_Secure_Response
{
    public function toOptionArray()
    {
    	return array(
        	array(
	        	'value' => '1', 
	        	'label' => Mage::helper('assist')->__('MD5')
	        ),
        	array(
	        	'value' => '2', 
	        	'label' => Mage::helper('assist')->__('PGP')
	        ),
            array(
                'value' => '0', 
                'label' => Mage::helper('assist')->__('None')
            )
		);
    }

    public function toArray()
    {
        return array(
            '1' => Mage::helper('assist')->__('MD5'),
            '2' => Mage::helper('assist')->__('PGP'),
            '0' => Mage::helper('assist')->__('None')
        );
    }

}