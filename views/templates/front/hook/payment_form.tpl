{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2021 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html'}" class="postfinancecheckout-payment-form" data-method-id="{$methodId|escape:'html':'UTF-8'}">
	<div id="postfinancecheckout-{$methodId|escape:'html':'UTF-8'}">
		<input type="hidden" id="postfinancecheckout-iframe-possible-{$methodId|escape:'html':'UTF-8'}" name="postfinancecheckout-iframe-possible-{$methodId|escape:'html':'UTF-8'}" value="false" />
		<div id="postfinancecheckout-loader-{$methodId|escape:'html':'UTF-8'}" class="postfinancecheckout-loader"></div>
	</div>
</form>