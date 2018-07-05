{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<div id="postFinanceCheckoutTransactionInfo" class="panel">
	<div class="panel-heading">
		<i class="icon-rocket"></i>
		PostFinance Checkout {l s="Transaction Information" mod="postfinancecheckout"}
	</div>
	<div class="postfinancecheckout-transaction-data-column-container">
		<div class="postfinancecheckout-transaction-column">
			<p>
				<strong>{l s="General Details" mod="postfinancecheckout"}</strong>
			</p>
			<dl class="well list-detail">
				<dt>{l s="Payment Method" mod="postfinancecheckout"}</dt>
				<dd>{$configurationName}
			{if !empty($methodImage)} 
			 	<br /><img
						src="{$methodImage|escape:'html'}"
						width="50" />
			{/if}
				</dd>
				<dt>{l s="Transaction State" mod="postfinancecheckout"}</dt>
				<dd>{$transactionState}</dd>
			{if !empty($failureReason)} 
            	<dt>{l s="Failure Reason" mod="postfinancecheckout"}</dt>
				<dd>{$failureReason}</dd>
			{/if}
        		<dt>{l s="Authorization Amount" mod="postfinancecheckout"}</dt>
				<dd>{displayPrice price=$authorizationAmount}</dd>
				<dt>{l s="Transaction" mod="postfinancecheckout"}</dt>
				<dd>
					<a href="{$transactionUrl|escape:'html'}" target="_blank">
						{l s="View" mod="postfinancecheckout"}
					</a>
				</dd>
			</dl>
		</div>
		{if !empty($labelsByGroup)}
			{foreach from=$labelsByGroup item=group}
			<div class="postfinancecheckout-transaction-column">
				<div class="postfinancecheckout-payment-label-container" id="postfinancecheckout-payment-label-container-{$group.id}">
					<p class="postfinancecheckout-payment-label-group">
						<strong>
						{$group.translatedTitle}
						</strong>
					</p>
					<dl class="well list-detail">
						{foreach from=$group.labels item=label}
	                		<dt>{$label.translatedName}</dt>
							<dd>{$label.value}</dd>
						{/foreach}
					</dl>
				</div>
			</div>
			{/foreach}
		{/if}
	</div>
	{if !empty($completions)}
		<div class="postfinancecheckout-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-check"></i>
					PostFinance Checkout {l s="Completions" mod="postfinancecheckout"}
			</div>
			<div class="table-responsive">
				<table class="table" id="postfinancecheckout_completion_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Completion Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="postfinancecheckout"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$completions item=completion}
						<tr>
							<td>{$completion->getId()}</td>
							<td>{if ($completion->getCompletionId() != 0)}
									{$completion->getCompletionId()}
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}	
							</td>
							<td>{$completion->getState()}</td>
							<td>{if !empty($completion->getFailureReason())}
									{assign var='failureReason' value="{postfinancecheckout_translate text=$completion->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="postfinancecheckout"}
								{/if}
							</td>
							<td>
								{if ($completion->getCompletionId() != 0)}
									{assign var='completionUrl' value="{postfinancecheckout_completion_url completion=$completion}"}
									<a href="{$completionUrl|escape:'html'}" target="_blank">
										{l s="View" mod="postfinancecheckout"}
									</a>
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($void)}
		<div class="postfinancecheckout-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-remove"></i>
					PostFinance Checkout {l s="Voids" mod="postfinancecheckout"}
			</div>
			<div class="table-responsive">
				<table class="table" id="postfinancecheckout_void_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Void Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="postfinancecheckout"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$voids item=voidItem}
						<tr>
							<td>{$voidItem->getId()}</td>
							<td>{if ($voidItem->getVoidId() != 0)}
									{$voidItem->getVoidId()}
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}		
							</td>
							<td>{$voidItem->getState()}</td>
							<td>{if !empty($voidItem->getFailureReason())}
									{assign var='failureReason' value="{postfinancecheckout_translate text=$voidItem->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="postfinancecheckout"}
								{/if}
							</td>
							<td>
								{if ($voidItem->getVoidId() != 0)}
									{assign var='voidUrl' value="{postfinancecheckout_void_url void=$voidItem}"}
									<a href="{$voidUrl|escape:'html'}" target="_blank">
										{l s="View" mod="postfinancecheckout"}
									</a>
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		{if !empty($refunds)}
		<div class="postfinancecheckout-transaction-data-column-container panel">
			<div class="panel-heading">
				<i class="icon-exchange"></i>
					PostFinance Checkout {l s="Refunds" mod="postfinancecheckout"}
			</div>
			<div class="table-responsive">
				<table class="table" id="postfinancecheckout_refund_table">
					<thead>
						<tr>
							<th>
								<span class="title_box ">{l s="Job Id" mod="postfinancecheckout"}</span>
							</th>
							
							<th>
								<span class="title_box ">{l s="External Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Refund Id" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Amount" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Type" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Status" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Error Message" mod="postfinancecheckout"}</span>
							</th>
							<th>
								<span class="title_box ">{l s="Links" mod="postfinancecheckout"}</span>
							</th>
						</tr>
					</thead>
					<tbody>
					{foreach from=$refunds item=refund}
						<tr>
							<td>{$refund->getId()}</td>
							<td>{$refund->getExternalId()}</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{$refund->getRefundId()}
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}	
							</td>
							<td>
								{assign var='refundAmount' value="{postfinancecheckout_refund_amount refund=$refund}"}
								{displayPrice price=$refundAmount currency=$currency->id}
							</td>
							<td>
								{assign var='refundType' value="{postfinancecheckout_refund_type refund=$refund}"}
								{$refundType}
							</td>
							<td>{$refund->getState()}</td>
							<td>{if !empty($refund->getFailureReason())}
									{assign var='failureReason' value="{postfinancecheckout_translate text=$refund->getFailureReason()}"}
									{$failureReason}
								{else}
									{l s="(None)" mod="postfinancecheckout"}
								{/if}
							</td>
							<td>
								{if ($refund->getRefundId() != 0)}
									{assign var='refundURl' value="{postfinancecheckout_refund_url refund=$refund}"}
									<a href="{$refundURl|escape:'html'}" target="_blank">
										{l s="View" mod="postfinancecheckout"}
									</a>
								{else}
									{l s="Not available" mod="postfinancecheckout"}
								{/if}	
							</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
		

</div>