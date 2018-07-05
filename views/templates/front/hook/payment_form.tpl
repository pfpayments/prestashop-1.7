{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html'}" class="postfinancecheckout-payment-form" data-method-id="{$methodId}">
	<div id="postfinancecheckout-{$methodId}">
		<div id="postfinancecheckout-loader-{$methodId}" class="postfinancecheckout-loader"></div>
	</div>
</form>