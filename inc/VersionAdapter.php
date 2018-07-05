<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

use PrestaShop\PrestaShop\Adapter\ServiceLocator;

class PostFinanceCheckout_VersionAdapter
{   
    
    public static function getConfigurationInterface(){
        return ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\ConfigurationInterface');
        
    }
    
    public static function getAddressFactory(){
        return ServiceLocator::get('\\PrestaShop\\PrestaShop\\Adapter\\AddressFactory');        
    }
    
    public static function clearCartRuleStaticCache(){
        if(version_compare(_PS_VERSION_, '1.7.3' , '>=')){
            CartRule::resetStaticCache();
        }        
    }
}