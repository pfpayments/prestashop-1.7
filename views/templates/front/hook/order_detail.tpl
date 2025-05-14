{*
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div id="postfinancecheckout_documents" style="display:none">
{if !empty($postFinanceCheckoutInvoice)}
	<a target="_blank" href="{$postFinanceCheckoutInvoice|escape:'html':'UTF-8'}">{l s='Download your %name% invoice as a PDF file.' sprintf=['%name%' => 'PostFinance Checkout'] mod='postfinancecheckout'}</a>
{/if}
{if !empty($postFinanceCheckoutPackingSlip)}
	<a target="_blank" href="{$postFinanceCheckoutPackingSlip|escape:'html':'UTF-8'}">{l s='Download your %name% packing slip as a PDF file.' sprintf=['%name%' => 'PostFinance Checkout'] mod='postfinancecheckout'}</a>
{/if}
</div>
