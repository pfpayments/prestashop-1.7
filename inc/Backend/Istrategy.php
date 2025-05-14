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
 * Interface for handing the different requests in the backend of the shop for different shop system version
 */
interface PostFinanceCheckoutBackendIstrategy
{

    /**
     * This function returns true, if the given data should used to update the line items on the transaction.
     * If this function returns false, the request has to processed like a refund.
     *
     * @param Order $order
     * @param array $postData
     * @return boolean
     *
     */
    public function isCancelRequest(Order $order, array $postData);

    /**
     * This function process the given $postData as cancel
     * This function is called if {@link #isCancelRequest} returns true.
     *
     * @param Order $order
     * @param array $postData
     */
    public function processCancel(Order $order, array $postData);

    public function isVoucherOnlyPostFinanceCheckout(Order $order, array $postData);

    /**
     * This methods valdiates the submitted refund, and will throw an exception if it fails.
     * This method has to return the parsed data, that will be stored in the database and used in the other methods as
     * input.
     *
     * @param Order $order
     * @param array $postData
     *            the $_POST $request
     * @return array the parsed refund data
     * @throws Exception, if the data can not be validated
     */
    public function validateAndParseData(Order $order, array $postData);

    public function simplifiedRefund(array $postData);

    public function getRefundTotal(array $parsedData);

    public function getPostFinanceCheckoutRefundType(array $parsedData);

    /**
     * This method creates the reduction items from the parsed data
     *
     * @param Order $order
     * @param array $parsedData
     * @return \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate[]
     */
    public function createReductions(Order $order, array $parsedData);

    /**
     * This method applies the refund in the shop for the parsed data.
     * This method must return an array with data that will be used in the afterApplyAction method
     *
     * @param Order $order
     * @param array $parsedData
     * @return array $appliedData
     */
    public function applyRefund(Order $order, array $parsedData);

    /**
     * This method will be called after the refund is applied in the shop and the db transaction is commited.
     * This method will only be called once. And any exception will be ignored.
     *
     * @param Order $order
     * @param array $parsedData
     * @param array $applyData
     */
    public function afterApplyRefundActions(Order $order, array $parsedData, array $applyData);

    /**
     * This function process the voucher delete request
     *
     * @param Order $order
     * @param array $postData
     */
    public function processVoucherDeleteRequest(Order $order, array $data);

    /**
     * This function process the voucher delete request
     *
     * @param Order $order
     * @param array $postData
     */
    public function processVoucherAddRequest(Order $order, array $data);
}
