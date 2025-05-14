{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div sytle="display:none" class="postfinancecheckout-method-data" data-method-id="{$methodId|escape:'html':'UTF-8'}" data-configuration-id="{$configurationId|escape:'html':'UTF-8'}"></div>
<section>
  {if !empty($description)}
    {* The description has to be unfiltered to dispaly html correcty. We strip unallowed html tags before we assign the variable to smarty *}
    <p>{postfinancecheckout_clean_html text=$description}</p>
  {/if}
  {if !empty($surchargeValues)}
	<span class="postfinancecheckout-surcharge postfinancecheckout-additional-amount"><span class="postfinancecheckout-surcharge-text postfinancecheckout-additional-amount-test">{l s='Minimum Sales Surcharge:' mod='postfinancecheckout'}</span>
		<span class="postfinancecheckout-surcharge-value postfinancecheckout-additional-amount-value">
			{if $priceDisplayTax}
				{Tools::displayPrice($surchargeValues.surcharge_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='postfinancecheckout'}
	        {else}
	        	{Tools::displayPrice($surchargeValues.surcharge_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='postfinancecheckout'}
	        {/if}
       </span>
   </span>
  {/if}
  {if !empty($feeValues)}
	<span class="postfinancecheckout-payment-fee postfinancecheckout-additional-amount"><span class="postfinancecheckout-payment-fee-text postfinancecheckout-additional-amount-test">{l s='Payment Fee:' mod='postfinancecheckout'}</span>
		<span class="postfinancecheckout-payment-fee-value postfinancecheckout-additional-amount-value">
			{if ($priceDisplayTax)}
	          	{Tools::displayPrice($feeValues.fee_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='postfinancecheckout'}
	        {else}
	          	{Tools::displayPrice($feeValues.fee_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='postfinancecheckout'}
	        {/if}
       </span>
   </span>
  {/if}
  
</section>
