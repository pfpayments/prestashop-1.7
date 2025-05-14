{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div class="panel">
	<h3>
		<i class="icon-list-ul"></i>
		{$title|escape:'html':'UTF-8'}
	</h3>
	<div class="postfinancecheckout_container_tab row">
		<div class="col-lg-12">
			{if isset($methodConfigurations) && count($methodConfigurations) > 0}
				<table class="table">
					{counter start=1  assign="count"}
					{foreach from=$methodConfigurations item=method}
						{include file='method_settings/list_line.tpl' class_row={cycle values=",row alt"}}
						{counter}
					{/foreach}
				</table>
			{else}
				<table class="table">
					<tr>
						<td>
							{l s='No payment methods available.' mod='postfinancecheckout'}
						</td>
					</tr>
				</table>
			{/if}
		</div>
	</div>
</div>
