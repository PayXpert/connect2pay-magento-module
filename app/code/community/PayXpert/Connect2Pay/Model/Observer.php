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

class PayXpert_Connect2Pay_Model_Observer extends Mage_Core_Block_Abstract {

    public function issue_creditmemo_refund(Varien_Object $payment) {

      $order = $payment->getCreditmemo()->getOrder();
      $creditmemo = $payment->getCreditmemo()->getOrder()->getData();
      $creditmemo_amount = $payment->getCreditmemo()->getData();
      
      $connect2pay = Mage::getModel('connect2pay/checkout');
    
      $client = new GatewayClient($connect2pay->getUrlApi(), $connect2pay->getOriginator(), $connect2pay->getPassword());
      $transaction = $client->newTransaction('Refund');
      $transaction->setReferralInformation($creditmemo['payxpert_transaction_id'], ceil($creditmemo_amount['grand_total'] * 100));
      
      $response    = $transaction->send();
	
      $order->addStatusHistoryComment($response);
      $order->save();
      
    }
}
?>
