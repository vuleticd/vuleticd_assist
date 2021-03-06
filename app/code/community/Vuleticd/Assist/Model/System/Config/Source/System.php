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
class Vuleticd_Assist_Model_System_Config_Source_System
{
	protected $_options;

    public function toOptionArray($isMultiselect=false)
    {
    	if (!$this->_options) {
            $this->_options = array(
            	array(
		        	'value' => 'Controlled', 
		        	'label' => Mage::helper('assist')->__('Controlled in ASSIST account')
		        ),
            	array(
		        	'value' => 'CardPayment', 
		        	'label' => Mage::helper('assist')->__('Credit Card')
		        ),
		        array(
		        	'value' => 'YMPayment', 
		        	'label'=>Mage::helper('assist')->__('YandexMoney')
		        ),
		        array(
		        	'value' => 'WMPayment', 
		        	'label'=>Mage::helper('assist')->__('WebMoney')
		        ),
		        array(
		        	'value' => 'QIWIPayment', 
		        	'label'=>Mage::helper('assist')->__('QIWI payment')
		        ),
		        array(
		        	'value' => 'QIWIMtsPayment', 
		        	'label'=>Mage::helper('assist')->__('Mobile phone money (MTS)')
		        ),
		        array(
		        	'value' => 'QIWIMegafonPayment', 
		        	'label'=>Mage::helper('assist')->__('Mobile phone money (Megafon)')
		        ),
		        array(
		        	'value' => 'QIWIBeelinePayment', 
		        	'label'=>Mage::helper('assist')->__('Mobile phone money (Beeline)')
		    	));
        }

    	if(!$isMultiselect){
            array_unshift($this->_options, array('value'=>'', 'label'=> Mage::helper('adminhtml')->__('--Please Select--')));
        }

        return $this->_options;
    }

}