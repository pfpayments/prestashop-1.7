{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div sytle="display:none" class="postfinancecheckout-method-data" data-method-id="{$methodId}" data-configuration-id="{$configurationId}"></div>
<section>
  {if !empty($description)}
    <p>{$description nofilter}</p>
  {/if}
  {if !empty($feeValues)}
	<span class="postfinancecheckout-payment-fee"><span class="postfinancecheckout-payment-fee-text">{l s='Additional Fee:' mod='postfinancecheckout'}</span>
		<span class="postfinancecheckout-payment-fee-value">
			{if ($priceDisplayTax)}
	          	{Tools::displayPrice($feeValues.fee_total)} {l s='(tax excl.)' mod='postfinancecheckout'}
	        {else}
	          	{Tools::displayPrice($feeValues.fee_total_wt)} {l s='(tax incl.)' mod='postfinancecheckout'}
	        {/if}
       </span>
   </span>
{/if}
  
</section>
