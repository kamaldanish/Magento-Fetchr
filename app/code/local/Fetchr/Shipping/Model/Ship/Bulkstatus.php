<?php

/**
  * Fetchr.
  *
  * NOTICE OF LICENSE
  *
  * This source file is subject to the Open Software License (OSL 3.0)
  * It is also available through the world-wide-web at this URL:
  * https://fetchr.zendesk.com/hc/en-us/categories/200522821-Downloads
  * If you did not receive a copy of the license and are unable to
  * obtain it through the world-wide-web, please send an email
  * to ws@fetchr.us so we can send you a copy immediately.
  *
  * DISCLAIMER
  *
  * Do not edit or add to this file if you wish to upgrade Fetchr Magento Extension to newer
  * versions in the future. If you wish to customize Fetchr Magento Extension (Fetchr Shipping) for your
  * needs please refer to http://www.fetchr.us for more information.
  *
  * @author     Danish Kamal
  * @copyright  Copyright (c) 2015 Fetchr (http://www.fetchr.us)
  * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
  */
class Fetchr_Shipping_Model_Ship_Bulkstatus
{
  public function run($force_order_update=false)
    {
    Mage::log('Get bulk status started!', null, 'fetchr.log');  
        if(!Mage::getStoreConfig('carriers/fetchr/active'))
            return;
        // if(!$force_order_update) {
        //     if(Mage::getStoreConfig('fetchr_shipping/settings/order_push') == '')
        //         return;
        // }
        $this->accountType = Mage::getStoreConfig('carriers/fetchr/accounttype');
        $accountType = $this->accountType;

        switch ($accountType) {
          case 'live':
          $baseurl = Mage::getStoreConfig('fetchr_shipping/settings/liveurl');
          break;
          case 'staging':
          $baseurl = Mage::getStoreConfig('fetchr_shipping/settings/stagingurl');
        }

        $this->userName     = Mage::getStoreConfig('carriers/fetchr/username');
        $this->password     = Mage::getStoreConfig('carriers/fetchr/password');

        $collection   =   Mage::getModel('sales/order')->getCollection()->addFieldToFilter('main_table.status', array(
                                array(
                                    'nin' => array(
                                        'complete',
                                        'closed',
                                        'canceled'
                                    ),
                                ),
                            ));

        $result = $tracking_numbers = $order_tracking_numbers  = array();

        if ($collection->getData()) {
            //echo "<pre>";print_r($collection->getData());die;
            foreach ($collection as $value) {
                $order = Mage::getModel('sales/order')->load($value->getId());
                foreach($order->getShipmentsCollection() as $shipment) {
                    foreach($shipment->getAllTracks() as $tracknum) {
                        //echo $tracknum->getNumber().'<br />';
                        $order_tracking_numbers[$value->getId()][] = $tracknum->getNumber();
                    }
                }
            }
        }
        
        foreach ($order_tracking_numbers as $otn) {
            $tracking_numbers[] = end($otn);
        }
      
        $data   =   array(  'username' => $this->userName,
                'password' => $this->password,
                'method' => 'get_status_bulk',
                'data' =>  $tracking_numbers
                );
  
        $data_string  = json_encode($data) ;
        $ch           = curl_init();
        $url          = $baseurl.'/api/get-status/';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        $results = curl_exec($ch);
        $results = json_decode($results, true);

        $orderComments  = array();
        foreach ($results['response'] as $result) {
            $erpStatus  = $result['package_state'];
            $orderId    = $result['client_order_ref'];

            //Check if the order ID has client username as prefix
            if(strpos($orderId, '_') !== false){
              $oids     = explode('_', $orderId);
              $orderId  = end($oids);
            }

            $order      = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            $comments   = $order->getStatusHistoryCollection();
            
            //Get All The Comments
            foreach ($comments as $c) {
                $orderComments[$orderId][]    = $c->getData();
            }
        
            //Get Fetchr Comments Only
            foreach ($orderComments[$orderId] as $key => $comment) {
                $sw_fetchr    = strpos($comment['comment'], 'Fetchr');
                if($sw_fetchr != false){
                    $fetchrComments[$orderId][] = $comment;
                }
            }
            
            $lastFetchrComment  = $fetchrComments[$orderId][0]['comment'];
            $status_mapping = array(
                                'Scheduled for delivery',
                                'Order dispatched',
                                'Returned to Client',
                                'Customer care On hold',
                                );
    
            $statusdiff     = strpos($lastFetchrComment, $erpStatus);
            $paymentType    = $order->getPayment()->getMethodInstance()->getCode();          
            
            if(strstr($erpStatus, 'Delivered') && $lastFetchrComment != null){
                $deliveryDate = explode(' ', $erpStatus);

                $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->setStatus('complete')->save();
                $order->addStatusHistoryComment('<strong>Delivered By Fetchr On: </strong>'.$deliveryDate[2], false)->save();
                
                if($paymentType == 'cashondelivery' || $paymentType == 'phoenix_cashondelivery'){
                    $order->setBaseTotalInvoiced($order->getBaseGrandTotal());
                    $order->setBaseTotalPaid($order->getBaseGrandTotal());
                    $order->setTotalPaid($order->getBaseGrandTotal());  
                }
                $order->save();
                
                foreach ($order->getInvoiceCollection() as $inv) {
                    $inv->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)->save();
                }
            }elseif($erpStatus != 'Order Created' && $statusdiff === false ){
                $order->setStatus('processing')->save();
                $order->addStatusHistoryComment('<strong>Fetchr Status: </strong>'.$erpStatus, false)->save();
            }
        }
    Mage::log('Get bulk status completed', null, 'fetchr.log');
    return $results;
    }
}
