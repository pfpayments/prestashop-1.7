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

/**
 * Webhook processor to handle token state transitions.
 */
class PostFinanceCheckout_Webhook_Token extends PostFinanceCheckout_Webhook_Abstract {

	public function process(PostFinanceCheckout_Webhook_Request $request){
		$tokenService = PostFinanceCheckout_Service_Token::instance();
		$tokenService->updateToken($request->getSpaceId(), $request->getEntityId());
	}
}