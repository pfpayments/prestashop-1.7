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
 * Webhook processor to handle transaction completion state transitions.
 */
class PostFinanceCheckoutBackendStrategy1774 extends PostFinanceCheckoutBackendDefaultstrategy
{

    public function isVoucherOnlyPostFinanceCheckout(Order $order, array $postData)
    {
        return isset($postData['cancel_product']['voucher']) && $postData['cancel_product']['voucher'] == 1;
    }
}
