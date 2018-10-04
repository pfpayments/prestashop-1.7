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

/**
 * Webhook processor to handle manual task state transitions.
 */
class PostFinanceCheckout_Webhook_ManualTask extends PostFinanceCheckout_Webhook_Abstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param PostFinanceCheckout_Webhook_Request $request
     */
    public function process(PostFinanceCheckout_Webhook_Request $request)
    {
        $manualTaskService = PostFinanceCheckout_Service_ManualTask::instance();
        $manualTaskService->update();
    }
}
