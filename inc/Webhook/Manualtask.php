<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle manual task state transitions.
 */
class PostFinanceCheckoutWebhookManualtask extends PostFinanceCheckoutWebhookAbstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param PostFinanceCheckoutWebhookRequest $request
     */
    public function process(PostFinanceCheckoutWebhookRequest $request)
    {
        $manualTaskService = PostFinanceCheckoutServiceManualtask::instance();
        $manualTaskService->update();
    }
}
