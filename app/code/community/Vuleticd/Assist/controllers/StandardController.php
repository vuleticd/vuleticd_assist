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
class Vuleticd_Assist_StandardController extends Mage_Core_Controller_Front_Action
{
    protected $_order = null;
    protected $_paymentInst = null;

    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getOrder($incrementId= null)
    {
        if (!$this->_order) {
            $incrementId = $incrementId ? $incrementId : $this->_getCheckoutSession()->getLastRealOrderId();
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    public function redirectAction()
    {
        try {
            if (!$this->_getOrder()->getId()) {
                Mage::throwException(Mage::helper('assist')->__('No order for processing found'));
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::helper('assist')->debug('error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    public function ipnAction()
    {
        $error = false;
        $request = $this->getRequest()->getPost();
        try {
            if (!$this->getRequest()->isPost() || empty($request)) {
                Mage::throwException('Invalid or empty request type.');
            }
            $request = $this->_checkIpnRequest();
            // process based on order state
            switch ($request['orderstate']) {
                case 'Approved':
                    $this->_processApproved($request);
                    break;
                case 'Delayed':
                    $this->_processDelayed($request);
                    break;
            }
        } catch(Mage_Core_Exception  $e) {
            $this->_getCheckoutSession()->addError($e->getMessage());
            $error = true;
            Mage::helper('assist')->debug('IPN error: ' . $e->getMessage());
        }

        $xml = $this->_generateIpnResponse($error, $request);
        $this->getResponse()->setHeader('Content-Type', 'text/xml; charset=utf-8')->setBody($xml);
        Mage::helper('assist')->debug($xml);
    }

    protected function _processApproved($request)
    {
        try {
            if ($this->_paymentInst->getConfigPaymentAction() != Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                Mage::throwException('Wrong payment action.');
            }
            // save transaction information
            $this->_order->getPayment()
                ->setTransactionId($request['billnumber'])
                ->setLastTransId($request['billnumber']);

            if (isset($request['approvalcode'])) {
                $this->_order->getPayment()->setAdditionalInformation('approvalcode', $request['approvalcode']);
            }

            $this->_processInvoice(); 
            $this->_order->save();
        } catch(Mage_Core_Exception  $e) {
            throw $e;
        }
    }

    protected function _processDelayed($request)
    {
        try {
            if ($this->_paymentInst->getConfigPaymentAction() != Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
                Mage::throwException('Wrong payment action.');
            }
            
            // save transaction information
            $this->_order->getPayment()
                ->setTransactionId($request['billnumber'])
                ->setLastTransId($request['billnumber'])
                ->authorize(true,$request['orderamount']);

            if (isset($request['approvalcode'])) {
                $this->_order->getPayment()->setAdditionalInformation('approvalcode', $request['approvalcode']);
            }

            $this->_processInvoice(); 
            $this->_order->save();
        } catch(Mage_Core_Exception  $e) {
            throw $e;
        }
    }

    protected function _processInvoice()
    {   
        if ($this->_order->canInvoice()) {
            $invoice = $this->_order->prepareInvoice();
            switch ($this->_paymentInst->getConfigPaymentAction()) {
                case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE:
                    $invoice->register();
                    $this->_order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'authorized');
                    break;
                
                case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE:
                    $this->_paymentInst->setAssistCaptureResponse(true);
                    $invoice->register()->capture();
                    break;
            }
            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $invoice->sendEmail();
        } elseif ($this->_order->isCanceled()) {
            Mage::throwException('Order canceled');
        } else {
            Mage::throwException('Order paid');
        }
    }

    protected function _checkIpnRequest()
    {
        $request = $this->getRequest()->getPost();
        $this->_order = $this->_getOrder($request['ordernumber']);
        // check order ID
        $orderId = $this->_order->getRealOrderId();
        if ($orderId !=  $request['ordernumber']) {
            Mage::throwException('Order not found');
        }

        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
        // check merchant ID
        if ($this->_paymentInst->getConfigData('merchant') != $request['merchant_id']) {
            Mage::throwException('Invalid merchant ID: ' . $request['merchant_id']);
        }
        // failed operation
        if ('AS000' !=  $request['responsecode']) {
            Mage::throwException($this->_paymentInst->getAssistErrors($request['responsecode']));
        }
        // wrong test mode
        if ($this->_paymentInst->getConfigData('mode') !=  $request['testmode']) {
            Mage::throwException('Wrong Test Mode.');
        }
        // accept only Approve operations, cancel and capture are processed real time
        if ('100' !=  $request['operationtype']) {
            Mage::throwException('Wrong Operation Type. Only Approve is supported by IPN.');
        }
        // check currency
        if ($this->_order->getBaseCurrencyCode() != $request['ordercurrency']) {
            Mage::throwException('Invalid currency: ' . $request['ordercurrency']);
        }
        // check amount
        $orderAmount = number_format($this->_order->getGrandTotal(), 2, '.', '');
        if ($orderAmount != $request['orderamount']) {
            Mage::throwException('Invalid amount: ' . $request['orderamount']);
        }

        if (Mage::helper('assist')->isResponseSecuredMd5()) {
            $x = array(
                $this->_paymentInst->getConfigData('merchant'),
                $request['ordernumber'],
                $request['amount'],
                $request['currency'],
                $request['orderstate']
            );
            if ($this->_paymentInst->secreyKey(implode("", $x)) != $request['checkvalue']) {
                Mage::throwException('Incorrect Checkvalue: ' . $request['checkvalue']);
            }
        }

        if (Mage::helper('assist')->isResponseSecuredPgp()) {
            $y = implode("", array(
                $this->_paymentInst->getConfigData('merchant'),
                $request['ordernumber'],
                $request['amount'], 
                $request['currency'],
                $request['orderstate']
            ));

            $keyFile = Mage::getBaseDir('var') . DS . 'assist' . DS . $this->_paymentInst->getConfigData('assist_key');
            if ($this->_paymentInst->sign($y, $keyFile) != $request['signature']) {
                Mage::throwException('Incorrect Signature.');
            }
        }
        
        return $request;
    }

    protected function _generateIpnResponse($error, $data)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $rootNode = $dom->createElement('pushpaymentresult');
        $firstcode = $dom->createAttribute('firstcode');
        $firstcode->value = $error ? '1' : '0';
        $rootNode->appendChild($firstcode);
        $secondcode = $dom->createAttribute('secondcode');
        $secondcode->value = $error ? '1' : '0';
        $rootNode->appendChild($secondcode);
        $dom->appendChild($rootNode);
        if (!$error) {
            $order = $dom->createElement('order');
            $rootNode->appendChild($order);
            $billnumber = $dom->createElement('billnumber', $data['billnumber']);
            $packetdate = $dom->createElement('packetdate', $data['packetdate']);
            $order->appendChild($billnumber);
            $order->appendChild($packetdate);
        }

        return $dom->saveXML();
    }

    public function cancelAction()
    {
        $params = $this->getRequest()->getParams();
        $orderId = $params['ordernumber'];
        $order = $this->_getOrder($orderId);
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        $txnId = $params['billnumber'];
        Mage::helper('assist')->debug('CANCEL');
        Mage::helper('assist')->debug($params);

        if ($order->getBaseTotalDue() && $quote->getId()) {
            $order->setState(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Mage::helper('assist')->__('Customer payment on ASSIST failed.')
                )->save();

            $quote->setIsActive(true)->save();
            $this->_getCheckoutSession()->setQuoteId($quote->getId());
            $this->_getCheckoutSession()->addError(Mage::helper('assist')->__('Sorry, your transaction is failed and cannot be'
                                                            . ' processed, please choose another payment method'
                                                            . ' or contact Customer Care to complete'
                                                            . ' your order.'));
            $this->_redirect('checkout/payment', array('_secure' => true));
        }
    }

    public function  successAction()
    {
        $params = $this->getRequest()->getParams();
        $orderId = $params['ordernumber'];
        $order = $this->_getOrder($orderId);
        $txnId = $params['billnumber'];

        $paymentInst = $order->getPayment()->getMethodInstance();

        try {
            $session = $this->_getCheckoutSession();
            if ($session->getLastRealOrderId() != $orderId) {
                Mage::throwException('Order not in session');
            }

            if (Mage_Sales_Model_Order::STATE_CANCELED == $order->getState()) {
                Mage::throwException('Order already canceled');
            }
            
            $state = $paymentInst->orderstate($order);

            $session->getQuote()->setIsActive(false)->save();
            $session->clear();
            // success payments URL
            $this->_redirect('checkout/onepage/success', array('_secure' => true));
            return;

        } catch(Mage_Core_Exception $e) {
            Mage::helper('assist')->debug('SUCCESS Error: ' . $e->getMessage());
        }
        // if this fails redirect to home page
        $this->_redirectUrl(Mage::getBaseUrl());
        return;
    }
}