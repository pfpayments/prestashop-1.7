{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
{if (isset($showAuthorizedActions) && $showAuthorizedActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-default postfinancecheckout-management-btn"  id="postfinancecheckout_void">
			<i class="icon-remove"></i>
			{l s='Void' mod='postfinancecheckout'}
		</a>
		<a class="btn btn-default postfinancecheckout-management-btn"  id="postfinancecheckout_completion">
			<i class="icon-check"></i>
			{l s='Completion' mod='postfinancecheckout'}
		</a>	
	</div>
	
	{addJsDefL name=postfinancecheckout_void_title}{l s='Are you sure?' mod='postfinancecheckout' js=1}{/addJsDefL}
	{addJsDefL name=postfinancecheckout_void_btn_confirm_txt}{l s='Void Order'  mod='postfinancecheckout' js=1}{/addJsDefL}
	{addJsDefL name=postfinancecheckout_void_btn_deny_txt}{l s='No' mod='postfinancecheckout' js=1}{/addJsDefL}
	<div id="postfinancecheckout_void_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also void the following orders:' mod='postfinancecheckout' js=1}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='postfinancecheckout' js=1}
						</a>
					</li>
				{/foreach}
			</ul>
			{l s='If you only want to void this order, we recommend to remove all products from this order.' mod='postfinancecheckout' js=1}
		{else}
			{l s='This action cannot be undone.' mod='postfinancecheckout' js=1}
		{/if}
	</div>
	
	{addJsDefL name=postfinancecheckout_completion_title}{l s='Are you sure?' mod='postfinancecheckout' js=1}{/addJsDefL}
	{addJsDefL name=postfinancecheckout_completion_btn_confirm_txt}{l s='Complete Order'  mod='postfinancecheckout' js=1}{/addJsDefL}
	{addJsDefL name=postfinancecheckout_completion_btn_deny_txt}{l s='No' mod='postfinancecheckout' js=1}{/addJsDefL}
	<div id="postfinancecheckout_completion_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also complete the following orders:' mod='postfinancecheckout'}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
								{l s='Order %d' sprintf=$other mod='postfinancecheckout'}
						</a>
					</li>
				{/foreach}
			</ul>
		{else}
			{l s='This finalizes the order, it no longer can be changed.' mod='postfinancecheckout'}			
		{/if}		
	</div>
{/if}
  
{if (isset($showUpdateActions) && $showUpdateActions)}
<div style="display:none;" class="hidden-print">
	<a class="btn btn-default postfinancecheckout-management-btn" id="postfinancecheckout_update">
		<i class="icon-refresh"></i>
		{l s='Update' mod='postfinancecheckout'}
	</a>
</div>
{/if}


{addJsDefL name=postfinancecheckout_msg_general_error}{l s='The server experienced an unexpected error, please try again.'  mod='postfinancecheckout' js=1}{/addJsDefL}
{addJsDefL name=postfinancecheckout_msg_general_title_succes}{l s='Success'  mod='postfinancecheckout' js=1}{/addJsDefL}
{addJsDefL name=postfinancecheckout_msg_general_title_error}{l s='Error'  mod='postfinancecheckout' js=1}{/addJsDefL}
{addJsDefL name=postfinancecheckout_btn_info_confirm_txt}{l s='OK'  mod='postfinancecheckout' js=1}{/addJsDefL}

{if isset($isPostFinanceCheckoutTransaction)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_is_transaction"></div>
{/if}

{if isset($editButtons)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_remove_edit"></div>
{/if}

{if isset($cancelButtons)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_remove_cancel"></div>
{/if}

{if isset($refundChanges)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_changes_refund">
<p id="postfinancecheckout_refund_online_text_total">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_offline_text_total" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_no_text_total" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_offline_span_total" class="checkbox" style="display: none;">
	<label for="postfinancecheckout_refund_offline_cb_total">
		<input type="checkbox" id="postfinancecheckout_refund_offline_cb_total" name="postfinancecheckout_offline">
		{l s='Send as offline refund to %s.' sprintf='PostFinance Checkout' mod='postfinancecheckout'}
	</label>
</p>

<p id="postfinancecheckout_refund_online_text_partial">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_offline_text_partial" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_no_text_partial" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='PostFinance Checkout' mod='postfinancecheckout'}</p>
<p id="postfinancecheckout_refund_offline_span_partial" class="checkbox" style="display: none;">
	<label for="postfinancecheckout_refund_offline_cb_partial">
		<input type="checkbox" id="postfinancecheckout_refund_offline_cb_partial" name="postfinancecheckout_offline">
		{l s='Send as offline refund to %s.' sprintf='PostFinance Checkout' mod='postfinancecheckout'}
	</label>
</p>
</div>
{/if}

{if isset($completionPending)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_completion_pending">
	<span class="span label label-inactive postfinancecheckout-management-info">
		<i class="icon-refresh"></i>
		{l s='Completion in Process' mod='postfinancecheckout'}
	</span>
</div>
{/if}

{if isset($voidPending)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_void_pending">
	<span class="span label label-inactive postfinancecheckout-management-info">
		<i class="icon-refresh"></i>
		{l s='Void in Process' mod='postfinancecheckout'}
	</span>

</div>
{/if}

{if isset($refundPending)}
<div style="display:none;" class="hidden-print" id="postfinancecheckout_refund_pending">
	<span class="span label label-inactive postfinancecheckout-management-info">
		<i class="icon-refresh"></i>
		{l s='Refund in Process' mod='postfinancecheckout'}
	</span>
</div>
{/if}


<script type="text/javascript">
var isVersionGTE177 = false;
{if isset($voidUrl)}
	var postFinanceCheckoutVoidUrl = "{$voidUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($voidUrl)}
	var postFinanceCheckoutCompletionUrl = "{$completionUrl|escape:'javascript':'UTF-8'}";
{/if}
{if isset($updateUrl)}
	var postFinanceCheckoutUpdateUrl = "{$updateUrl|escape:'javascript':'UTF-8'}";
{/if}

</script>