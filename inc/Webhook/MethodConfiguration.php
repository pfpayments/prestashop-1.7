<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle payment method configuration state transitions.
 */
class PostFinanceCheckout_Webhook_MethodConfiguration extends PostFinanceCheckout_Webhook_Abstract
{

    /**
     * Synchronizes the payment method configurations on state transition.
     *
     * @param PostFinanceCheckout_Webhook_Request $request
     */
    public function process(PostFinanceCheckout_Webhook_Request $request)
    {
        $paymentMethodConfigurationService = PostFinanceCheckout_Service_MethodConfiguration::instance();
        $paymentMethodConfigurationService->synchronize();
    }
}
