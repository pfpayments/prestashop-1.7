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
 * Webhook processor to handle token version state transitions.
 */
class PostFinanceCheckout_Webhook_TokenVersion extends PostFinanceCheckout_Webhook_Abstract
{

    public function process(PostFinanceCheckout_Webhook_Request $request)
    {
        $tokenService = PostFinanceCheckout_Service_Token::instance();
        $tokenService->updateTokenVersion($request->getSpaceId(), $request->getEntityId());
    }
}
