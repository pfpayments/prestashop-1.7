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
 * This class provides function to download documents from PostFinance Checkout
 */
class PostFinanceCheckoutDownloadhelper
{

    /**
     * Downloads the transaction's invoice PDF document.
     */
    public static function downloadInvoice($order)
    {
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo != null && in_array(
            $transactionInfo->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
                \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE
            )
        )) {
            $service = new \PostFinanceCheckout\Sdk\Service\TransactionService(
                PostFinanceCheckoutHelper::getApiClient()
            );
            $document = $service->getInvoiceDocument(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            self::download($document);
        }
    }

    /**
     * Downloads the transaction's packing slip PDF document.
     */
    public static function downloadPackingSlip($order)
    {
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo != null &&
            $transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL) {
            $service = new \PostFinanceCheckout\Sdk\Service\TransactionService(
                PostFinanceCheckoutHelper::getApiClient()
            );
            $document = $service->getPackingSlip($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            self::download($document);
        }
    }

    /**
     * Sends the data received by calling the given path to the browser and ends the execution of the script
     *
     * @param string $path
     */
    protected static function download(\PostFinanceCheckout\Sdk\Model\RenderedDocument $document)
    {
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document->getTitle() . '.pdf"');
        header('Content-Description: ' . $document->getTitle());
        echo PostFinanceCheckoutTools::base64Decode($document->getData());
        exit();
    }
}
