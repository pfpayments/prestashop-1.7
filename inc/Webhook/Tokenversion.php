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
 * Webhook processor to handle token version state transitions.
 */
class PostFinanceCheckoutWebhookTokenversion extends PostFinanceCheckoutWebhookAbstract
{
    public function process(PostFinanceCheckoutWebhookRequest $request)
    {
        $tokenService = PostFinanceCheckoutServiceToken::instance();
        $tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}
