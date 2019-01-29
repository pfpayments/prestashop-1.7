{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html'}" class="postfinancecheckout-payment-form" data-method-id="{$methodId}">
	<div id="postfinancecheckout-{$methodId}">
		<input type="hidden" id="postfinancecheckout-iframe-possible-{$methodId}" name="postfinancecheckout-iframe-possible-{$methodId}" value="false" />
		<div id="postfinancecheckout-loader-{$methodId}" class="postfinancecheckout-loader"></div>
	</div>
</form>