{**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div class="panel col-lg-12">	
	<div class="panel-heading">
		<i class="icon-list-ul"></i>
		PostFinance Checkout {l s='CronJobs' mod='postfinancecheckout'}
	</div>
		<div class="table-responsive-row clearfix">
			<table class="table">
				<thead>
					<tr class="nodrag nodrop">
						<th class="fixed-width-xs text-center">
							<span class="title_box">{l s='ID' mod='postfinancecheckout'}</span>
						</th>
						<th class="fixed-width-s text-center">
							<span class="title_box">{l s='State' mod='postfinancecheckout'}</span>
						</th>
						<th class="fixed-width-m text-center">
							<span class="title_box">{l s='Scheduled' mod='postfinancecheckout'}</span>
						</th>
						<th class="fixed-width-m text-center">
							<span class="title_box">{l s='Started' mod='postfinancecheckout'}</span>
						</th>
						<th class="fixed-width-m text-center">
							<span class="title_box">{l s='Finished' mod='postfinancecheckout'}</span>
						</th>
						<th class="fixed-width-l center">
							<span class="title_box">{l s='Message' mod='postfinancecheckout'}</span>
						</th>
					</tr>
				
				</thead>
				<tbody>
				{if isset($jobs) && count($jobs) > 0 }
					{foreach from=$jobs item=job}
						<tr class="">
							<td class=" fixed-width-xs text-center">{$job.id_cron_job|escape:'html':'UTF-8'}</td>
							<td class=" fixed-width-s text-center">{$job.state|escape:'html':'UTF-8'}</td>
							<td class=" fixed-width-m text-center">{$job.date_scheduled|date_format:'%Y-%m-%d %H:%M:%S'|escape:'html':'UTF-8'}</td>
							<td class=" fixed-width-m text-center">
								{if !empty($job.date_started) }
									{$job.date_started|date_format:'%Y-%m-%d %H:%M:%S'|escape:'html':'UTF-8'}
								{else}
								 	--
								{/if}								
							</td>
							<td class=" fixed-width-m text-center">
								{if !empty($job.date_finished) }
									{$job.date_finished|date_format:'%Y-%m-%d %H:%M:%S'|escape:'html':'UTF-8'}
								{else}
								 	--
								{/if}								
							</td>
							<td class=" fixed-width-l text-center">
								{if !empty($job.error_msg) }
									{$job.error_msg|escape:'html':'UTF-8'}
								{else}
								 	--
								{/if}
							</td>
							
						</tr>
					{/foreach}
				{else}
					<tr>
						<td class="text-center" colspan="6">
							{l s='No cron available yet.' mod='postfinancecheckout'}
						</td>
					</tr>
				{/if}
				</tbody>
			</table>
		</div>
	</div>
</div>
