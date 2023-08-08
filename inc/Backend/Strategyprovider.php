<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This provider allows to create a PostFinanceCheckout_ShopRefund_IStrategy.
 * The implementation of
 * the strategy depends on the actual prestashop version.
 */
class PostFinanceCheckoutBackendStrategyprovider
{
    private static $supported_strategies = [
        '1.7.7.4' => PostFinanceCheckoutBackendStrategy1774::class
    ];

    /**
     * Returns the refund strategy to use
     *
     * @return PostFinanceCheckoutBackendIstrategy
     */
    public static function getStrategy()
    {
        if (isset(self::$supported_strategies[_PS_VERSION_])) {
            return new self::$supported_strategies[_PS_VERSION_];
        }
        return new PostFinanceCheckoutBackendDefaultstrategy();
    }
}
