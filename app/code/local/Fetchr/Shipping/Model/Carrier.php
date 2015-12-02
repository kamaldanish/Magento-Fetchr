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
 * versions in the future. If you wish to customize Fetchr Magento Extension (Fetchr Shipping) for your
 * needs please refer to http://www.fetchr.us for more information.
 *
 * @author     Islam Khalil
 * @package    Fetchr Shipping
 * Used in creating options for live|staging config value selection
 * @copyright  Copyright (c) 2015 Fetchr (http://www.fetchr.us)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fetchr_Shipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
  protected $_code = 'fetchr';
 
  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
  {
    if (!Mage::getStoreConfig('carriers/'.$this->_code.'/active')) {
        return false;
    }
    $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling');
    $result   = Mage::getModel('shipping/rate_result');
    $method   = Mage::getModel('shipping/rate_result_method');
    
    $method->setCarrier($this->_code);
    $method->setMethod('standard');
    $method->setCarrierTitle($this->getConfigData('title'));
    $method->setMethodTitle('Standard');
    $method->setPrice('10');
    $method->setCost(0);
    $result->append($method);
 
    return $result;
  }
 
  public function getAllowedMethods()
  {
    return array(
      'standard' => 'Standard',
    );
  }

  public function isTrackingAvailable()
  {
      return true;
  }

  public function getTrackingInfo($tracking)
  {
      $track = Mage::getModel('shipping/tracking_result_status');
      $track->setUrl('http://track.menavip.com/track.php?tracking_number=' . $tracking)
          ->setTracking($tracking)
          ->setCarrierTitle($this->getConfigData('name'));
      return $track;
  }
}
