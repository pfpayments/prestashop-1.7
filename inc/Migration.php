<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckout_Migration extends PostFinanceCheckout_AbstractMigration
{
   
    protected static function getMigrations()
    {
        return array(
            '1.0.0' => 'initializeTables',
            '1.0.1' => 'orderStatusUpdate',
            '1.0.2' => 'tokenInfoImproved',
            '1.0.3' => 'updateImageBase',
            '1.0.4' => 'userFailureMessage',
            
        );
    }

    public static function initializeTables()
    {
        static::installTableBase();
    }
    
    public static function orderStatusUpdate()
    {
        static::installOrderStatusConfigBase();
        static::installOrderPaymentSaveHookBase();
    }
    
    public static function tokenInfoImproved()
    {
        static::updateCustomerIdOnTokenInfoBase();
    }
    
    public static function userFailureMessage() 
    {
    	static::userFailureMessageBase();
    }
}
