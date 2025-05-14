{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div id="postfinancecheckout_notifications" style="display:none;">
	<li id="postfinancecheckout_manual_notif"class="dropdown">	
		<a href="javascript:void(0);" class="dropdown-toggle postfinancecheckout_manual_notif notifs" data-toggle="dropdown">
			<i class="icon-bullhorn"></i>
				<svg class="postfinancecheckout-icon-bullhorn-svg" viewBox="24 16 64 64">
					<path fill="#6c868e" d="M71.6 40.1c1.1 0 2 0.4 2.8 1.2 0.8 0.8 1.2 1.7 1.2 2.8 0 1.1-0.4 2-1.2 2.8 -0.8 0.8-1.7 1.2-2.8 1.2v11.8c0 1.1-0.4 2-1.2 2.8 -0.8 0.8-1.7 1.2-2.8 1.2 -8.5-7.1-16.8-11-24.9-11.7 -1.2 0.4-2.1 1.1-2.8 2 -0.7 1-1 2-1 3.1 0 1.1 0.5 2 1.2 2.8 -0.4 0.7-0.6 1.3-0.7 2 -0.1 0.7 0 1.3 0.2 1.8 0.2 0.5 0.5 1.1 1 1.7s1 1.1 1.5 1.5c0.5 0.4 1.1 0.9 1.9 1.5 -0.6 1.2-1.7 2-3.4 2.5 -1.7 0.5-3.4 0.6-5.2 0.4 -1.8-0.3-3.1-0.8-4.1-1.7 -0.1-0.5-0.4-1.4-0.9-2.7 -0.5-1.3-0.8-2.3-1-2.9 -0.2-0.6-0.4-1.5-0.7-2.7s-0.4-2.2-0.5-3.1c0-0.9 0-1.9 0.1-3 0.1-1.2 0.3-2.3 0.7-3.4h-3.7c-1.4 0-2.5-0.5-3.5-1.4 -1-1-1.4-2.1-1.4-3.5v-5.9c0-1.4 0.5-2.5 1.4-3.5s2.1-1.4 3.5-1.4h14.7c8.9 0 18.1-3.9 27.5-11.8 1.1 0 2 0.4 2.8 1.2 0.8 0.8 1.2 1.7 1.2 2.8V40.1zM67.6 58.7V29.4c-8.1 6.2-15.9 9.7-23.6 10.5v8.3C51.8 49.1 59.6 52.5 67.6 58.7z"/></svg>
				{if $manualTotal > 0}					
					<span id="postfinancecheckout_manual_notif_number_wrapper" class="notifs_badge">
						<span id="postfinancecheckout_manual_notif_value">{$manualTotal|escape:'html':'UTF-8'}</span>
					</span>
				{/if}
		</a>
		<div class="dropdown-menu notifs_dropdown" id="dropdown_postfinancecheckout_manual">
			<section id="postfinancecheckout_manual_notif_wrapper" class="notifs_panel" style="width:250px">
				<div class="notifs_panel_header">
					<h3>Manual Tasks</h3>
				</div>
				<div id="list_postfinancecheckout_manual_notif" class="list_notif">
					{if $manualTotal > 0}
						<a href="{$manualUrl|escape:'html':'UTF-8'}" target="_blank">
							<p>{if $manualTotal > 1}
								{l s='There are %s manual tasks that need your attention.' sprintf=$manualTotal mod='postfinancecheckout'}
							{else}
								{l s='There is a manual task that needs your attention.' mod='postfinancecheckout'}
							{/if}
							</p>
						</a>
					{else}
						<span class="no_notifs">
						{l s='There are no manual tasks.' mod='postfinancecheckout'}
						</span>
					{/if}
					
				</div>
			</section>
		</div>
	</li>
</div>