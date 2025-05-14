<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Provider of currency information from the gateway.
 */
class PostFinanceCheckoutProviderCurrency extends PostFinanceCheckoutProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('postfinancecheckout_currencies');
    }

    /**
     * Returns the currency by the given code.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns a list of currencies.
     *
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $currencyService = new \PostFinanceCheckout\Sdk\Service\CurrencyService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        return $currencyService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}
