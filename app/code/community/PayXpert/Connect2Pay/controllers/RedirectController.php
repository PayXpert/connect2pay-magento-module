<?php
/**
 Copyright 2016 PayXpert

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License. 
 */
 
require_once(dirname(__FILE__) . "/../Helper/Connect2PayClient.php");
 
class PayXpert_Connect2Pay_RedirectController extends Mage_Core_Controller_Front_Action {

  public function getCheckout() {
    return Mage::getSingleton('checkout/session');
  }

  protected function _expireAjax() {
    if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
        $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
        exit;
    }
  }

  public function indexAction() {
    $this->loadLayout();
    $block = $this->getLayout()->createBlock('connect2pay/redirect');
    $this->getLayout()->getBlock('content')->append($block);
    $this->renderLayout();
  }
  
  public function callbackAction() {
    $post = $this->getRequest()->getPost();
    
    $connect2pay = Mage::getModel('connect2pay/checkout');
    
    // Setup the connection and handle Callback Status
    $c2pClient = new Connect2PayClient($connect2pay->getUrl(), $connect2pay->getOriginator(), $connect2pay->getPassword());

    if ($c2pClient->handleCallbackStatus()) {
      // Get the Error code
      $status = $c2pClient->getStatus();

      // The payment status code
      $errorCode = $status->getErrorCode();
      // Custom data that could have been provided in ctrlCustomData when creating
      // the payment
      $merchantData = $status->getCtrlCustomData();
      // The transaction ID generated for this payment
      $transactionId = $status->getTransactionID();
      // The transaction ID generated for this payment
      $currency = $status->getCurrency();
      // The unique token, known only by the payment page and the merchant
      $merchantToken = $status->getMerchantToken();
      // The transaction ID generated for this payment
      $orderId = $status->getOrderID();
      // The transaction ID generated for this payment
      $amount = $status->getAmount();
      
      if (md5($orderId . $amount . $connect2pay->getPassword()) == $merchantData) {

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
      
        if ($order != null) {
          $log = "Received a new transaction status from " . $_SERVER["REMOTE_ADDR"] . ". Merchant token: " . $merchantToken . ", Status: " . $status->getStatus() .
               ", Error code: " . $errorCode;
          if ($errorCode >= 0) {
            $log .= ", Error message: " . $status->getErrorMessage();
            $log .= ", Transaction ID: " . $transactionId;
          }
          Mage::log($log);

          // errorCode = 000 => payment transaction is successful
          if ($errorCode == '000') {
            $payment = $order->getPayment();
            $payment->setTransactionId($transactionId)
                ->setLastTransId($transactionId)
                ->setCurrencyCode($currency)
                ->setPreparedMessage($log)
                ->setIsTransactionClosed(0)
                ->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED)
                ->registerCaptureNotification($amount / 100);
            $order->save();
            
            $invoice = $payment->getCreatedInvoice();
            if ($invoice && !$order->getEmailSent()) {
                // notify customer
                $message = 'Notified customer about invoice #'. $invoice->getIncrementId();
                $order->queueNewOrderEmail()->addStatusHistoryComment($message)
                    ->setIsCustomerNotified(true)
                    ->save();
                Mage::log($message);
            }
          }

          $order->addStatusHistoryComment($log);
          $order->save();
          
          // Send back a response to mark this transaction as notified on the payment
          // page
          $response = array("status" => "OK", "message" => "Status recorded");
          header("Content-type: application/json");
          echo json_encode($response);
          exit;
        } else {
          Mage::log("Error. No order found for token " . $merchantToken . " in callback from " . $_SERVER["REMOTE_ADDR"] . ".");
        }
      } else {
         Mage::log("Error. invalid token " . $merchantToken . " in callback from " . $_SERVER["REMOTE_ADDR"] . ".");
      }
    } else {
      Mage::log("Error. Received an incorrect status from " . $_SERVER["REMOTE_ADDR"] . ".");
    }

    // Send back a default error response
    $response = array("status" => "KO", "message" => "Error handling the callback");
    header("Content-type: application/json");
    echo json_encode($response);
    exit;
      
  }
  
  public function successAction() {
    
    $post = $this->getRequest()->getPost();
    
    $connect2pay = Mage::getModel('connect2pay/checkout');
    
    // We restore from the session the token info
    $merchantToken = $_SESSION['merchantToken'];

    if ($merchantToken != null) {
      // Extract data received from the payment page
      $data = $post["data"];

      if ($data != null) {
        // Setup the client and decrypt the redirect Status
        $c2pClient = new Connect2PayClient($connect2pay->getUrl(), $connect2pay->getOriginator(), $connect2pay->getPassword());
        if ($c2pClient->handleRedirectStatus($data, $merchantToken)) {
          // Get the Error code
          $status = $c2pClient->getStatus();

          $errorCode = $status->getErrorCode();
          $merchantData = $status->getCtrlCustomData();
          $transactionId = $status->getTransactionID();
          $orderId = $status->getOrderID();

          $session = Mage::getSingleton('checkout/session');
          $session->setQuoteId($orderId);
          Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
          // errorCode = 000 => payment is successful
          if ($errorCode == '000') {
            // Display the payment confirmation page
            $this->_redirect('checkout/onepage/success', array('_secure'=>true)); 
            return;
          } else {
            // Display the cart page
            if ($session->getLastRealOrderId()) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
                if ($order->getId()) {
                    $order->cancel()->save();
                }
                Mage::helper('connect2pay/checkout')->restoreQuote();
            }
          }
        }
      }
    }
    $this->_redirect('checkout/cart');               
  }
}
