<?php
/**
 * Fetchr
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
 * versions in the future. If you wish to customize Fetchr Magento Extension (Fetchr Shiphappy) for your
 * needs please refer to http://www.fetchr.us for more information.
 *
 * @author     Danish Kamal
 * @package    Fetchr Shiphappy
 * Used in pusing order from Magento Store to Fetchr
 * @copyright  Copyright (c) 2015 Fetchr (http://www.fetchr.us)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

 class Fetchr_Shiphappy_Model_Ship_Push
{
    public function run()
    {
        $collection = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('main_table.status', array(
            array(
                'in' => array(
                    'processing'
                )
            )
        ));
        $store          = Mage::app()->getStore();
        $storeTelephone = Mage::getStoreConfig('general/store_information/phone');
        $storeAddress = Mage::getStoreConfig('general/store_information/address');
        if ($collection->getData()) {
            $resource = Mage::getSingleton('core/resource');
            $adapter  = $resource->getConnection('core_read');
            try {
                foreach ($collection as $value) {
                    $order = Mage::getModel('sales/order')->load($value->getId());
                    $paymentType = $order->getPayment()->getMethodInstance()->getId();
                    if (!$order->canInvoice()) {
                        continue;
                    }
                    $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);
                    $transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
                    if ($invoice->getId()) {
                        $paymentType = $order->getPayment()->getMethodInstance()->getCode();
                        // Get Items Ordered Name
                        foreach ($order->getAllItems() as $item) {
                            if ($item['product_type'] == 'configurable') {
                                $itemArray[] = array(
                                    'client_ref' => $order->getIncrementId(),
                                    'name' => $item['name'],
                                    'sku' => $item['sku'],
                                    'quantity' => $item['qty_ordered'],
                                    'merchant_details' => array(
                                        "mobile" => $storeTelephone,
                                        "phone" => $storeTelephone,
                                        "name" => $store->getFrontendName(),
                                        "address" => $storeAddress
                                    ),
                                    'COD' => $order->getShippingAmount(),
                                    'price' => $item['price'],
                                    "is_voucher" => "No"
                                );
                                break;
                            } else {
                                $itemArray[] = array(
                                    'client_ref' => $order->getIncrementId(),
                                    'name' => $item['name'],
                                    'sku' => $item['sku'],
                                    'quantity' => $item['qty_ordered'],
                                    'merchant_details' => array(
                                        "mobile" => $storeTelephone,
                                        "phone" => $storeTelephone,
                                        "name" => $store->getFrontendName(),
                                        "address" => $storeAddress
                                    ),
                                    'COD' => $order->getShippingAmount(),
                                    'price' => $item['price'],
                                    "is_voucher" => "No"
                                );
                            }
                        }
                        $discountAmount = 0;
                        if ($order->getDiscountAmount()) {
                            $discountAmount = abs($order->getDiscountAmount());
                        }
                        $address = $order->getShippingAddress()->getData();
                        switch ($paymentType) {
                            case 'cashondelivery':
                                case 'phoenix_cashondelivery':
                                $paymentType = 'COD';
                                $grandtotal  = $order->getGrandTotal();
                                $discount    = $discountAmount;
                                break;
                            default:
                                $paymentType = 'cd';
                                $grandtotal  = 0;
                                $discount    = 0;
                        }
                        $this->serviceType = Mage::getStoreConfig('shiphappy/settings/servicetype');
                        $this->userName    = Mage::getStoreConfig('shiphappy/settings/username');
                        $this->password    = Mage::getStoreConfig('shiphappy/settings/password');
                        $ServiceType = $this->serviceType;
                        switch ($ServiceType) {
                            case 'fulfilment':
                                $dataErp[] = array(
                                    "order" => array(
                                        "items" => $itemArray,
                                        "details" => array(
                                            "status" => "",
                                            "discount" => $discount,
                                            "grand_total" => $grandtotal,
                                            "customer_email" => $address['email'],
                                            "order_id" => $order->getIncrementId(),
                                            "customer_firstname" => $address['firstname'],
                                            "payment_method" => $paymentType,
                                            "customer_mobile" => $address['telephone'],
                                            "customer_lastname" => $address['lastname'],
                                            "order_country" => $address['country_id'],
                                            "order_address" => $address['street'] . ', ' . $address['city'] . ', ' . $address['country_id']
                                        )
                                    )
                                );
                                break;
                            case 'delivery':
                                $dataErp = array(
                                    "username" => $this->userName,
                                    "password" => $this->password,
                                    "method" => 'create_orders',
                                    "pickup_location" => 'Dubai',
                                    "data" => array(
                                        array(
                                            "order_reference" => $order->getIncrementId(),
                                            "name" => $address['firstname'] . ' ' . $address['lastname'],
                                            "email" => $address['email'],
                                            "phone_number" => $address['telephone'],
                                            "address" => $address['street'],
                                            "city" => $address['city'],
                                            "payment_type" => $paymentType,
                                            "amount" => $grandtotal,
                                            "description" => 'No',
                                            "comments" => 'No'
                                        )
                                    )
                                );
                        }
                        echo '<pre>';
                        print_r($dataErp);

                        $this->_sendDataToErp($dataErp, $order->getIncrementId());
                        unset($dataErp, $itemArray);
                    }
                }
            }
            catch (Exception $e) {
                echo (string) $e->getMessage();
            }
        }
    }
    protected function _sendDataToErp($data, $orderId)
    {
        try {
            $this->accountType = Mage::getStoreConfig('shiphappy/settings/accounttype');
            $this->serviceType = Mage::getStoreConfig('shiphappy/settings/servicetype');
            $this->userName    = Mage::getStoreConfig('shiphappy/settings/username');
            $this->password    = Mage::getStoreConfig('shiphappy/settings/password');
            $ServiceType       = $this->serviceType;
            $accountType       = $this->accountType;
            switch ($accountType) {
                case 'live':
                    $baseurl = Mage::getStoreConfig('shiphappy/settings/liveurl');
                    break;
                case 'staging':
                    $baseurl = Mage::getStoreConfig('shiphappy/settings/stagingurl');
            }
            switch ($ServiceType) {
                case 'fulfilment':
                    $ERPdata = "ERPdata=" . json_encode($data);
                    $ch      = curl_init();
                    $url     = $baseurl . "/client/gapicurl/";
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $ERPdata . "&erpuser=" . $this->userName . "&erppassword=" . $this->password . "&merchant_name=" . $this->userName);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    print_r($response);
                    if ($response['response']['awb'] == 'SKU not found') {
                        $store = Mage::app()->getStore();
                        $cname = $store->getFrontendName();
                        $ch    = curl_init();
                        $url   = "http://www.menavip.com/custom/smail.php";
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, "orderId=" . $orderId . "&cname=" . $cname);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $output = curl_exec($ch);
                        curl_close($ch);
                    }
                    if ($response['response']['tracking_no'] != '0') {
                        $o_status = 'fetchr_shipping';
                        $order    = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                        $order->setStatus($o_status)->setResponseMessage('Confirmation Received from Fetchr')->save();
                        return $this;
                    }
                    break;
                case 'delivery':
                    $data_string = "args=" . json_encode($data);
                    $ch          = curl_init();
                    $url         = $baseurl . "/client/api/";
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    $results = curl_exec($ch);
                    print_r($results);
                    $varshipID     = $results;
                    $shipidexpStr  = explode("status", $varshipID);
                    $ResValShip    = $shipidexpStr[0];
                    $RevArray      = array(
                        '"',
                        '{'
                    );
                    $varshipIDTrim = str_replace($RevArray, '', $ResValShip);
                    $datas         = rtrim($varshipIDTrim, ',');
                    $array1        = explode(",", $datas);
                    foreach ($array1 as $val) {
                        $array2 = explode(":", $val);
                        if ($array2['1'] != '') {
                            $o_status = 'fetchr_shipping';
                            $order    = Mage::getModel('sales/order')->loadByIncrementId($array2['0']);
                            $order->setStatus($o_status)->setResponseMessage('Confirmation Received from Fetchr')->save();
                            return $this;
                        }
                    }
            }
        }
        catch (Exception $e) {
            echo (string) $e->getMessage();
        }
        Mage::log('Order Pushed', null, 'fetchr.log');
    }
}