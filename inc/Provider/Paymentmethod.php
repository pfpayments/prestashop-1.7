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
 * Provider of payment method information from the gateway.
 */
class PostFinanceCheckoutProviderPaymentmethod extends PostFinanceCheckoutProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('postfinancecheckout_methods');
    }

    /**
     * Returns the payment method by the given id.
     *
     * @param int $id
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment methods.
     *
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $methodService = new \PostFinanceCheckout\Sdk\Service\PaymentMethodService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}
