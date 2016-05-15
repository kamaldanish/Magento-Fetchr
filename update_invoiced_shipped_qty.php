<?php

/*
This Script is used to updated the old orders that have Qty invoiced and Qty Shipped equal to 0
the script need to be on the root directory of your magento store, and you can just run it manually 
by adding the name of the file right after the website url : www.example.com/update_invoices.php
*/

//require 'app/bootstrap.php';
require_once('app/Mage.php');
umask(0);
Mage::app();

$allOrders = Mage::getModel('sales/order')->getCollection()
    ->addFieldToFilter('status', 'complete')
    ->addAttributeToFilter('total_due', array('neq' => 'AED0.00'));

foreach ($allOrders as $value) {
	$order = Mage::getModel('sales/order')->load($value->getId());
	$order->setTotalPaid($order->getBaseGrandTotal()); 
	$order->save();
	foreach ($order->getAllVisibleItems() as $item) {
		$qtyOrderd  = $item->getQtyOrdered();
		$itemId		= $item->getItemId();
		$item->setQtyInvoiced($qtyOrderd);
		$item->setQtyShipped($qtyOrderd);
		$item->save();
	}
}
die("Batata!");

?>