<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This provider allows to create a PostFinanceCheckout_ShopRefund_IStrategy. The implementation of 
 * the strategy depends on the actual prestashop version.
 */
class PostFinanceCheckout_Backend_StrategyProvider {

    
    /**
     * Returns the refund strategy to use
     * 
     * @return PostFinanceCheckout_Backend_IStrategy
     */
    public static function getStrategy(){
        
        return new PostFinanceCheckout_Backend_DefaultStrategy();
       
    }
    
    
    
}
    
    