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
class Vuleticd_Assist_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    const TEST_URL = 'https://test.paysec.by/pay/order.cfm';
    const LIVE_URL = 'https://paysec.by/pay/order.cfm';

    const CHARGE_URL = 'https://test.paysec.by/charge/charge.cfm';
    const ORDER_STATE_URL = 'https://test.paysec.by/orderstate/orderstate.cfm';
    const ORDER_RESULT_URL = 'https://test.paysec.by/orderresult/orderresult.cfm';
    const CANCEL_URL = 'https://test.paysec.by/cancel/cancel.cfm';

    const SEND_SEPARATOR = ';';

    const PEM_DIR = 'assist';

    protected $_code = 'assist';
    protected $_formBlockType = 'assist/form';
    protected $_infoBlockType = 'assist/info';

    protected $_isGateway  = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_isInitializeNeeded      = true;
    protected $_canUseForMultishipping = false;
    protected $_canVoid = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial     = false;

    protected $_order;

    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }
        return $this->_order;
    }

    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getCheckoutRedirectUrl()
    {
        return false;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('assist/standard/redirect', array('_secure' => true));
    }

    public function getUrl()
    {
        $url = self::TEST_URL;
        if ($this->getConfigData('mode') == 0) {
            $url = self::LIVE_URL;
        }
        return $url;
    }

    public function validate()
    {
        Mage::helper('assist')->debug('validate');
        return;
    }
    
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);
        $payment->setIsTransactionClosed(false);
        Mage::helper('assist')->debug('authorize');
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        parent::void($payment);
        try {
            $data = array(
                'Merchant_ID' => urlencode($this->getConfigData('merchant')),
                'Billnumber' => urlencode($payment->getParentTransactionId()),
                'Login' => urlencode($this->getConfigData('api_login')),
                'Password' => urlencode($this->getConfigData('api_password'))
            );
            $xml = $this->callAssist(self::CANCEL_URL, $data);

            if ((int)$xml['firstcode'] || (int)$xml['secondcode']) {
                Mage::throwException('error in call');
            }

            if ('AS000' != (string)$xml->orders->order->responsecode) {
                Mage::throwException($this->getAssistErrors((string)$xml->orders->order->responsecode));
            }

            if (Mage::helper('assist')->isServiceSecured()) {
                $y = implode("", array(
                            $this->getConfigData('merchant'),
                            (string)$xml->orders->order->ordernumber,
                            (string)$xml->orders->order->orderamount,
                            (string)$xml->orders->order->ordercurrency,
                            (string)$xml->orders->order->orderstate,
                            (string)$xml->orders->order->packetdate
                        ));
                $keyFile = Mage::getBaseDir('var') . DS . self::PEM_DIR . DS . $this->getConfigData('assist_key');
                if ((string)$xml->orders->order->signature != $this->sign($y, $keyFile)) {
                    Mage::throwException('Incorrect Signature.');
                }
            }
            // success
            Mage::helper('assist')->debug($xml);
        } catch (Mage_Core_Exception $e) {
            Mage::helper('assist')->debug($e->getMessage());
            throw $e;
        } 

        Mage::helper('assist')->debug('void');
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        parent::cancel($payment);
        Mage::helper('assist')->debug('cancel');
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);
        $txn = $payment->getAuthorizationTransaction();
        if ($txn) {
            try {
                $billnumber = $txn->getParentTxnId() ? $txn->getParentTxnId() : $txn->getTxnId();
                $data = array(
                    'Merchant_ID' => urlencode($this->getConfigData('merchant')),
                    'Billnumber' => urlencode($billnumber),
                    'Login' => urlencode($this->getConfigData('api_login')),
                    'Password' => urlencode($this->getConfigData('api_password'))
                );
                $xml = $this->callAssist(self::CANCEL_URL, $data);

                if ((int)$xml['firstcode'] || (int)$xml['secondcode']) {
                    Mage::throwException('error in call');
                }

                if ('AS000' != (string)$xml->orders->order->responsecode) {
                    Mage::throwException($this->getAssistErrors((string)$xml->orders->order->responsecode));
                }

                if (Mage::helper('assist')->isServiceSecured()) {
                    $y = implode("", array(
                                $this->getConfigData('merchant'),
                                (string)$xml->orders->order->ordernumber,
                                (string)$xml->orders->order->orderamount,
                                (string)$xml->orders->order->ordercurrency,
                                (string)$xml->orders->order->orderstate,
                                (string)$xml->orders->order->packetdate
                            ));
                    $keyFile = Mage::getBaseDir('var') . DS . self::PEM_DIR . DS . $this->getConfigData('assist_key');
                    if ((string)$xml->orders->order->signature != $this->sign($y, $keyFile)) {
                        Mage::throwException('Incorrect Signature.');
                    }
                }

                // success
                Mage::helper('assist')->debug($xml);
            } catch (Mage_Core_Exception $e) {
                Mage::helper('assist')->debug($e->getMessage());
                throw $e;
            } 
        }

        Mage::helper('assist')->debug('refund');
        return $this;
    }
    
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);
        $txn = $payment->getAuthorizationTransaction();    
        if ($txn) {
            try {
                $data = array(
                    'Merchant_ID' => urlencode($this->getConfigData('merchant')),
                    'Billnumber' => urlencode($txn->getTxnId()),
                    'Language' => urlencode('EN'),
                    'Login' => urlencode($this->getConfigData('api_login')),
                    'Password' => urlencode($this->getConfigData('api_password'))
                );

                $xml = $this->callAssist(self::CHARGE_URL, $data);

                if ((int)$xml['firstcode'] || (int)$xml['secondcode']) {
                    Mage::throwException('error in call');
                }

                if ('AS000' != (string)$xml->orders->order->responsecode) {
                    Mage::throwException($this->getAssistErrors((string)$xml->orders->order->responsecode));
                }

                if (Mage::helper('assist')->isServiceSecured()) {
                    $y = implode("", array(
                                $this->getConfigData('merchant'),
                                (string)$xml->orders->order->ordernumber,
                                (string)$xml->orders->order->orderamount,
                                (string)$xml->orders->order->ordercurrency,
                                (string)$xml->orders->order->orderstate,
                                (string)$xml->orders->order->packetdate
                            ));
                    $keyFile = Mage::getBaseDir('var') . DS . self::PEM_DIR . DS . $this->getConfigData('assist_key');
                    if ((string)$xml->orders->order->signature != $this->sign($y, $keyFile)) {
                        Mage::throwException('Incorrect Signature.');
                    }
                }

                // success
                Mage::helper('assist')->debug($xml);
            } catch (Mage_Core_Exception $e) {
                Mage::helper('assist')->debug($e->getMessage());
                throw $e;
            } 
        } else {
            if (!$this->getAssistCaptureResponse()) {
                $message = Mage::helper('assist')->__('Captured amount of %s offline. ASSIST does not support invoicing via web service.', $amount);
                $this->getOrder()->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $message);
            }
        }
        Mage::helper('assist')->debug('capture');
        return $this;
    }

    public function initialize($paymentAction, $stateObject)
    {
        Mage::helper('assist')->debug('initialize');
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
        return $this;
    }

    public function orderstate($order)
    {
        $orderId = $order->getIncrementId();
        $orderAmount = number_format($order->getGrandTotal(), 2, '.', '');
        $orderCurr = $order->getBaseCurrencyCode();
        $data = array(
                'Merchant_ID' => urlencode($this->getConfigData('merchant')),
                'Ordernumber' => urlencode($orderId),
                'Login' => urlencode($this->getConfigData('api_login')),
                'Password' => urlencode($this->getConfigData('api_password'))
        );

        $xml = $this->callAssist(self::ORDER_STATE_URL, $data);
       
        if ((int)$xml['firstcode'] || (int)$xml['secondcode']) {
            Mage::throwException('error in call');
        }
        
        if ($this->getConfigPaymentAction() == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE && 'Delayed' != (string)$xml->order->orderstate
            || $this->getConfigPaymentAction() == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE && 'Approved' != (string)$xml->order->orderstate) {
            Mage::throwException('Incorrect order state.');
        }

        if (Mage::helper('assist')->isResponseSecuredMd5()) {
            $x = array(
                $this->getConfigData('merchant'),
                $orderId,
                $orderAmount,
                $orderCurr,
                (string)$xml->order->orderstate
            );
            if ($this->secreyKey(implode("", $x)) != (string)$xml->order->checkvalue) {
                Mage::throwException('Incorrect Checkvalue.');
            }
        }

        if (Mage::helper('assist')->isResponseSecuredPgp()) {
            $y = implode("", array(
                $this->getConfigData('merchant'),
                $orderId,
                $orderAmount,
                $orderCurr,
                (string)$xml->order->orderstate,
                (string)$xml->order->packetdate
            ));

            $keyFile = Mage::getBaseDir('var') . DS . self::PEM_DIR . DS . $this->getConfigData('assist_key');
            if ($this->sign($y, $keyFile) != (string)$xml->order->signature) {
                Mage::throwException('Incorrect Signature.');
            }
        }
 
        // success
        Mage::helper('assist')->debug($xml);

        Mage::helper('assist')->debug('orderstate');

        return (string)$xml->order->orderstate;
    }

    public function getFormFields()
    {
        $locale = Mage::app()->getLocale()->getLocaleCode();
        $language = strtoupper(substr( $locale, 0, strpos( $locale, '_' ) ));
        $orderAmount = number_format($this->getOrder()->getGrandTotal(), 2, '.', '');
        $billingAddress = $this->getOrder()->getBillingAddress();
        $urlReturnOk = Mage::getUrl("assist/standard/success", array('_secure' => true));
        $urlReturnNo = Mage::getUrl("assist/standard/cancel", array('_secure' => true));
       
        $fields = array(
            'Merchant_ID' => $this->getConfigData('merchant'),
            'Delay' => $this->getConfigPaymentAction() == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE ? 1 : 0,
            'OrderNumber' => $this->getOrder()->getRealOrderId(),
            'Language' => $language,
            'OrderAmount' => $orderAmount,
            'OrderCurrency' => $this->getOrder()->getBaseCurrencyCode(),
            'Lastname' => $billingAddress->getLastname(),
            'Firstname' => $billingAddress->getFirstname(),
            'Email' => $this->getOrder()->getCustomerEmail(),
            'MobilePhone' => $billingAddress->getMobile(),
            'URL_RETURN_OK' => $urlReturnOk,
            'URL_RETURN_NO' => $urlReturnNo,
            'OrderComment' => '',
            'Middlename' => $billingAddress->getMiddlename(),
            'Address'   => str_replace("\n", ", ", $billingAddress->getStreetFull()),
            'HomePhone' => $billingAddress->getTelephone(),
            'WorkPhone' => $billingAddress->getWorkphone(),
            'Fax' => $billingAddress->getFax(),
            'Country' => $billingAddress->getCountryModel()->getIso3Code(),
            'State' => $billingAddress->getRegionCode(),
            'City' => $billingAddress->getCity(),
            'Zip' => $billingAddress->getPostcode(),
            'MobileDevice' => $this->getConfigData('mobile')
        );

        $fields = $this->_mergePaymentSystems($fields);
        if (Mage::helper('assist')->isOrderSecuredMd5()) {
            $x = array(
                $this->getConfigData('merchant'),
                $this->getOrder()->getRealOrderId(),
                $orderAmount,
                $this->getOrder()->getBaseCurrencyCode()
            );
            $fields['Checkvalue'] = $this->secreyKey(implode(self::SEND_SEPARATOR, $x));
        }

        if (Mage::helper('assist')->isOrderSecuredPgp()) {
            $y = md5(implode(self::SEND_SEPARATOR, array(
                $this->getConfigData('merchant'),
                $this->getOrder()->getRealOrderId(),
                $orderAmount,
                $this->getOrder()->getBaseCurrencyCode()
            )));

            $keyFile = Mage::getBaseDir('var') . DS . self::PEM_DIR . DS . $this->getConfigData('merchant_key');
            $fields['Signature'] = $this->sign($y, $keyFile);
        }

        Mage::helper('assist')->debug($fields);
        return $fields;
    }

    protected function _mergePaymentSystems($data)
    {
        $systems = explode(",", $this->getConfigData('payment_system'));
        if (in_array('Controlled', $systems)) {
            return $data;
        } 

        foreach ((array) $systems as $system) {
            $data[$system] = '1';
        }
        return $data;
    }

    public function secreyKey($x)
    {
        $key = $this->getConfigData('secret_key');
        Mage::helper('assist')->debug('secret_key: ' . $key);
        Mage::helper('assist')->debug('x: ' . $x);
        $checkvalue = strtoupper(md5(strtoupper(md5($key) . md5($x))));
        Mage::helper('assist')->debug('Checkvalue:' . $checkvalue);
        return $checkvalue;
    }

    public function sign($x, $file)
    {
        $f = file_get_contents($file);
        Mage::helper('assist')->debug($file);
        $pkeyid = openssl_get_privatekey($f);
        openssl_sign($x, $signature, $pkeyid, 'md5');
        openssl_free_key($pkeyid);

        return base64_encode($signature);
    }

    public function getAssistErrors($response)
    {
        switch ($response) {
            case 'AS100':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED');
                break;
            case 'AS101':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Incorrect card parameters');
                break;
            case 'AS102':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Insufficient cash');
                break;
            case 'AS104':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Incorrect card validity period');
                break;
            case 'AS105':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Card operations limit exceeded');
                break;
            case 'AS107':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Data reception error');
                break;
            case 'AS108':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Suspicion of fraud');
                break;
            case 'AS109':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Operations limit exceeded');
                break;
            case 'AS110':
                $error = Mage::helper('assist')->_('AUTHORIZATION DECLINED Authorization via 3D-Secure required');
                break;
            case 'AS200':
                $error = Mage::helper('assist')->_('REPEAT AUTHORIZATION');
                break;
            case 'AS300':
                $error = Mage::helper('assist')->_('OPERATION IN PROCESS. WAIT');
                break;
            case 'AS400':
                $error = Mage::helper('assist')->_('NO PAYMENTS WITH SUCH PARAMETERS EXIST');
                break;
            case 'AS998':
                $error = Mage::helper('assist')->_('SYSTEM ERROR. Connect to ASSIST');
                break;
            default:
                $error = Mage::helper('assist')->_('Unrecognized error.');
                break;
        }
        return $error;
    }

    public function callAssist($url, $data)
    {
        try {
            Mage::helper('assist')->debug($data);
            $data['Format'] = 3;
            foreach ( $data as $key => $value) {
                $chfields[] = $key . '=' . $value;
            }
            $post_string = implode ('&', $chfields);

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

            $result = curl_exec($ch);
            $xml = simplexml_load_string($result);
            curl_close($ch);
            return $xml;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }
}