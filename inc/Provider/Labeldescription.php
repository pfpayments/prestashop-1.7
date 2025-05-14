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
 * Provider of label descriptor information from the gateway.
 */
class PostFinanceCheckoutProviderLabeldescription extends PostFinanceCheckoutProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('postfinancecheckout_label_description');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $id
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService = new \PostFinanceCheckout\Sdk\Service\LabelDescriptionService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
