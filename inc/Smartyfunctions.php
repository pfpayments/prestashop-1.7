<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckoutSmartyfunctions
{
    public static function translate($params, $smarty)
    {
        $text = $params['text'];
        return PostFinanceCheckoutHelper::translate($text);
    }

    /**
     * Returns the URL to the refund detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getRefundUrl($params, $smarty)
    {
        $refundJob = $params['refund'];
        return PostFinanceCheckoutHelper::getRefundUrl($refundJob);
    }

    public static function getRefundAmount($params, $smarty)
    {
        $refundJob = $params['refund'];
        return PostFinanceCheckoutBackendStrategyprovider::getStrategy()->getRefundTotal(
            $refundJob->getRefundParameters()
        );
    }

    public static function getRefundType($params, $smarty)
    {
        $refundJob = $params['refund'];
        return PostFinanceCheckoutBackendStrategyprovider::getStrategy()->getPostFinanceCheckoutRefundType(
            $refundJob->getRefundParameters()
        );
    }

    /**
     * Returns the URL to the completion detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getCompletionUrl($params, $smarty)
    {
        $completionJob = $params['completion'];
        return PostFinanceCheckoutHelper::getCompletionUrl($completionJob);
    }

    /**
     * Returns the URL to the void detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function getVoidUrl($params, $smarty)
    {
        $voidJob = $params['void'];
        return PostFinanceCheckoutHelper::getVoidUrl($voidJob);
    }
    
    /**
     * Returns the URL to the void detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function cleanHtml($params, $smarty)
    {
        return strip_tags($params['text'], '<a><b><strong><i><img><span><div>');
    }
    
    /**
     * Returns the URL to the void detail view in PostFinance Checkout.
     *
     * @return string
     */
    public static function outputMethodForm($params, $smarty)
    {
        return $params['form'];
    }
}
