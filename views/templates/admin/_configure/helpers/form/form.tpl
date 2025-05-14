{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
{extends file='helpers/form/form.tpl'}
{block name="input" append}
{if $input.type == 'postfinancecheckout_password'}
	{if isset($input.lang) AND $input.lang}
		{if $languages|count > 1}
			<div class="form-group">
		{/if}
		{foreach $languages as $language}
			{assign var='value_text' value=$fields_value[$input.name][$language.id_lang]}
			{if $languages|count > 1}
				<div class="translatable-field lang-{$language.id_lang|escape:'html':'UTF-8'}" {if $language.id_lang != $defaultFormLanguage}style="display:none"{/if}>
				<div class="col-lg-9">
			{/if}
			{if isset($input.prefix) || isset($input.suffix)}
						<div class="input-group{if isset($input.class)} {$input.class|escape:'html':'UTF-8'}{/if}">
			{/if}
			{if isset($input.prefix)}
				<span class="input-group-addon">
				  {$input.prefix|escape:'html':'UTF-8'}
				</span>
			{/if}
			<input type="password"
				id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}_{$language.id_lang|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}_{$language.id_lang|escape:'html':'UTF-8'}{/if}"
				name="{$input.name|escape:'html':'UTF-8'}_{$language.id_lang|escape:'html':'UTF-8'}"
				class="{if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if}"
				value="{$value_text|escape:'html':'UTF-8'}"
				onkeyup="if (isArrowKey(event)) return ;updateFriendlyURL();"
				{if isset($input.size)} size="{$input.size|escape:'html':'UTF-8'}"{/if}
				{if isset($input.readonly) && $input.readonly} readonly="readonly"{/if}
				{if isset($input.disabled) && $input.disabled} disabled="disabled"{/if}
				{if isset($input.autocomplete) && !$input.autocomplete} autocomplete="off"{/if}
				{if isset($input.required) && $input.required} required="required" {/if}
			 />
			{if isset($input.suffix)}
				<span class="input-group-addon">
					{$input.suffix|escape:'html':'UTF-8'}
				</span>
			{/if}
			{if isset($input.prefix) || isset($input.suffix)}
				</div>
			{/if}
			{if $languages|count > 1}
				</div>
				<div class="col-lg-2">
					<button type="button" class="btn btn-default dropdown-toggle" tabindex="-1" data-toggle="dropdown">
						{$language.iso_code|escape:'html':'UTF-8'}
						<i class="icon-caret-down"></i>
					</button>
					<ul class="dropdown-menu">
						{foreach from=$languages item=language}
							<li><a href="javascript:hideOtherLanguage({$language.id_lang|escape:'html':'UTF-8'});" tabindex="-1">{$language.name|escape:'html':'UTF-8'}</a></li>
						{/foreach}
					</ul>
				</div>
				</div>
			{/if}
		{/foreach}
		{if $languages|count > 1}
			</div>
		{/if}
	{else}
		{assign var='value_text' value=$fields_value[$input.name]}
		{if isset($input.prefix) || isset($input.suffix)}
			<div class="input-group{if isset($input.class)} {$input.class|escape:'html':'UTF-8'}{/if}">
		{/if}
		{if isset($input.prefix)}
			<span class="input-group-addon">
				{$input.prefix|escape:'html':'UTF-8'}
			</span>
		{/if}
		<input type="password"
			name="{$input.name|escape:'html':'UTF-8'}"
			id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}{/if}"
			value="{$value_text|escape:'html':'UTF-8'}"
			class="{if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if}"
			{if isset($input.size)} size="{$input.size|escape:'html':'UTF-8'}"{/if}
			{if isset($input.readonly) && $input.readonly} readonly="readonly"{/if}
			{if isset($input.disabled) && $input.disabled} disabled="disabled"{/if}
			{if isset($input.autocomplete) && !$input.autocomplete} autocomplete="off"{/if}
			{if isset($input.required) && $input.required } required="required" {/if}
		/>
		{if isset($input.suffix)}
			<span class="input-group-addon">
				{$input.suffix|escape:'html':'UTF-8'}
			</span>
		{/if}
	
		{if isset($input.prefix) || isset($input.suffix)}
			</div>
		{/if}
	{/if}
{/if}
{/block}