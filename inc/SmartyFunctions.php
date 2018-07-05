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

class PostFinanceCheckout_SmartyFunctions
{
    public static function translate($params, $smarty)
    {
        $text = $params['text'];
        return PostFinanceCheckout_Helper::translate($text);
    }
    
    
    /**
     * Returns the URL to the refund detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getRefundUrl($params, $smarty){
        $refundJob = $params['refund'];
        return PostFinanceCheckout_Helper::getRefundUrl($refundJob);
    }
    
    public static function getRefundAmount($params, $smarty){
        $refundJob = $params['refund'];
        return PostFinanceCheckout_Backend_StrategyProvider::getStrategy()->getRefundTotal($refundJob->getRefundParameters());
    }
    
    public static function getRefundType($params, $smarty){
        $refundJob = $params['refund'];
        return PostFinanceCheckout_Backend_StrategyProvider::getStrategy()->getPostFinanceCheckoutRefundType($refundJob->getRefundParameters());
    }
    
    /**
     * Returns the URL to the completion detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getCompletionUrl($params, $smarty){
        $completionJob = $params['completion'];
        return PostFinanceCheckout_Helper::getCompletionUrl($completionJob);
    }
    
    /**
     * Returns the URL to the void detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getVoidUrl($params, $smarty){
        $voidJob = $params['void'];
        return PostFinanceCheckout_Helper::getVoidUrl($voidJob);
    }
    
}