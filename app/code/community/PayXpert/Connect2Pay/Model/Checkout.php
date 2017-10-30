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

class PayXpert_Connect2Pay_Model_Checkout extends Mage_Payment_Model_Method_Abstract {

    protected $_code  = 'connect2pay';
    protected $_paymentMethod = 'shared';
    
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('connect2pay/redirect');
    }

    //get payment page URL
    public function getUrl() {
        $url = trim($this->getConfigData('url'));
        if (empty($url)) {
          $url = "https://connect2.payxpert.com";
        }
        return $url;
    }

    //get originator ID
    public function getOriginator() {
        return $this->getConfigData('originator');
    }

    //get password
    public function getPassword() {
        return $this->getConfigData('password');
    }

    //get order
    public function getQuote() {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        return $order;
    }

    //get HTML form data
    public function getFormFields() {
        $order_id = $this->getCheckout()->getLastRealOrderId();
        $order    = Mage::getModel('sales/order')->loadByIncrementId($order_id);

        $connect2Pay = array();
        $shopper = $this->getQuote()->getBillingAddress();
        $connect2Pay['orderID']             = $order_id;
        $connect2Pay['shopperID']           = Mage::getSingleton('customer/session')->getId();
        $connect2Pay['shopperEmail']        = $order->getData('customer_email');
        $connect2Pay['shopperFirstName']    = $shopper->getFirstname();
        $connect2Pay['shopperLastName']		  = $shopper->getLastname();
        $connect2Pay['shopperPhone']			  = $shopper->getTelephone();
        $connect2Pay['shopperCountryCode']	= $shopper->getCountry();
        $connect2Pay['shopperAddress']		  = trim($shopper->getStreet1() . " " . $shopper->getStreet2());
        $connect2Pay['shopperCity']					= $shopper->getCity();
        $connect2Pay['shopperState']        = $shopper->getRegion();
        $connect2Pay['shopperZipcode']			= $shopper->getPostcode();

        $amount   = ceil($order->getGrandTotal() * 100);
        $connect2Pay['amount']              = $amount;
        $connect2Pay['currency']            = $order->getOrderCurrencyCode();
        $connect2Pay['customerIP']          = Mage::helper('core/http')->getRemoteAddr();
        $connect2Pay["description"]         = "Order #" . $order_id;
        
        $shipping = $this->getQuote()->getShippingAddress();
        if ($shipping) {
          $connect2Pay['hasShippingAddress']  = true;
          $connect2Pay['shippingFirstName']   = $shipping->getFirstname();
          $connect2Pay['shippingLastName']		= $shipping->getLastname();
          $connect2Pay['shippingPhone']			  = $shipping->getTelephone();
          $connect2Pay['shippingCountryCode']	= $shipping->getCountry();
          $connect2Pay['shippingAddress']		  = trim($shipping->getStreet1() . " " . $shipping->getStreet2());
          $connect2Pay['shippingCity']				= $shipping->getCity();
          $connect2Pay['shippingState']       = $shipping->getRegion();
          $connect2Pay['shippingZipcode']			= $shipping->getPostcode();
        } else {
          $connect2Pay['hasShippingAddress']  = false;
        }
        $connect2Pay['callbackURL'] = Mage::getUrl('connect2pay/redirect/callback', array('_secure' => true));
        $connect2Pay['redirectURL'] = Mage::getUrl('connect2pay/redirect/success', array('_secure' => true));

        return $connect2Pay;
    }

}
