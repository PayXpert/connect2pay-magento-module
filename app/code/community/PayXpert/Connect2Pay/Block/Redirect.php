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
 
class PayXpert_Connect2Pay_Block_Redirect extends Mage_Core_Block_Abstract {

  protected function _toHtml() {
    
    $connect2pay = Mage::getModel('connect2pay/checkout');
    
    $c2pClient = new Connect2PayClient($connect2pay->getUrl(), $connect2pay->getOriginator(), $connect2pay->getPassword());
    $fields = $connect2pay->getFormFields();
    
    $c2pClient->setOrderID( $fields['orderID'] );
    $c2pClient->setCustomerIP( $fields['customerIP'] );
    $c2pClient->setPaymentType( Connect2PayClient::_PAYMENT_TYPE_CREDITCARD );
    $c2pClient->setPaymentMode( Connect2PayClient::_PAYMENT_MODE_SINGLE );
    $c2pClient->setShopperID( $fields['shopperID'] );
    $c2pClient->setShippingType( Connect2PayClient::_SHIPPING_TYPE_VIRTUAL );
    $c2pClient->setAmount( $fields['amount'] );
    $c2pClient->setOrderDescription( $fields["description"] );
    $c2pClient->setCurrency( $fields['currency'] );
    
    $c2pClient->setShopperFirstName( html_entity_decode($fields['shopperFirstName'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperLastName( html_entity_decode($fields['shopperLastName'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperAddress( html_entity_decode($fields['shopperAddress'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperZipcode( html_entity_decode($fields['shopperZipcode'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperCity( html_entity_decode($fields['shopperCity'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperState( html_entity_decode($fields['shopperState'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperCountryCode( $fields['shopperCountryCode'] );
    $c2pClient->setShopperPhone( html_entity_decode($fields['shopperPhone'], ENT_QUOTES, 'UTF-8') );
    $c2pClient->setShopperEmail (html_entity_decode($fields['shopperEmail'], ENT_QUOTES, 'UTF-8') );
    
    if ($fields['hasShippingAddress']) {
      $c2pClient->setShipToFirstName( html_entity_decode($fields['shippingFirstName'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToLastName( html_entity_decode($fields['shippingLastName'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToAddress( html_entity_decode($fields['shippingAddress'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToZipcode( html_entity_decode($fields['shippingZipcode'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToCity( html_entity_decode($fields['shippingCity'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToState( html_entity_decode($fields['shippingState'], ENT_QUOTES, 'UTF-8') );
      $c2pClient->setShipToCountryCode( $fields['shippingCountryCode'] );
      $c2pClient->setShipToPhone( html_entity_decode($fields['shippingPhone'], ENT_QUOTES, 'UTF-8') );
    }
    
    $c2pClient->setCtrlRedirectURL( $fields['redirectURL'] );
    $c2pClient->setCtrlCallbackURL( $fields['callbackURL'] );
    
    $md5 = md5($fields['orderID'] . $fields['amount'] . $connect2pay->getPassword());

    $c2pClient->setCtrlCustomData ($md5);
    $html = "";
    
    if ($c2pClient->validate()) {
      if ($c2pClient->prepareTransaction()) {
      
        $_SESSION['merchantToken'] = $c2pClient->getMerchantToken();
        
        $url = $c2pClient->getCustomerRedirectURL();
        header ('Location: ' . $url);
        exit;
      } else {
        $message = "<b>PayXpert</b> payment module: Error in prepareTransaction: <br />";
        $message .= "Order id: " . $fields['orderID'] . " <br />";
        $message .= "Result code: " . $this->escapeHTML($c2pClient->getReturnCode()) . "<br />";
        $message .= "Preparation error occured: " . $this->escapeHTML($c2pClient->getClientErrorMessage()) . "<br />";
        Mage::log(strip_tags($message));
        $html = '<p>' . $message . '</p>';
      }
    } else {
      $message = "<b>PayXpert</b> payment module: Error in validate function: <br />";
      $message .= "Order id: " . $fields['orderID'] . " <br />";
      $message .= "Validation error occured: " . $this->escapeHTML($c2pClient->getClientErrorMessage()) . "<br />";
      Mage::log(strip_tags($message));
      $html = '<p>' . $message . '</p>';
    }
    Mage::helper('connect2pay/checkout')->restoreQuote();
    return $html;
  }
}

?>
