<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle transaction state transitions.
 */
class PostFinanceCheckout_Webhook_Transaction extends PostFinanceCheckout_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see PostFinanceCheckout_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    protected function loadEntity(PostFinanceCheckout_Webhook_Request $request)
    {
        $transactionService = new \PostFinanceCheckout\Sdk\Service\TransactionService(PostFinanceCheckout_Helper::getApiClient());
        return $transactionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($transaction)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Transaction $transaction */
        return $transaction->getMerchantReference();
    }

    protected function getTransactionId($transaction)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Order $order, $transaction)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Transaction $transaction */
        $transactionInfo = PostFinanceCheckout_Model_TransactionInfo::loadByOrderId($order->id);
        if ($transaction->getState() != $transactionInfo->getState()) {
            switch ($transaction->getState()) {
                case \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED:
                    $this->authorize($transaction, $order);
                    break;
                case \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE:
                    $this->decline($transaction, $order);
                    break;
                case \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED:
                    $this->failed($transaction, $order);
                    break;
                case \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL:
                    $this->authorize($transaction, $order);
                    $this->fulfill($transaction, $order);
                    break;
                case \PostFinanceCheckout\Sdk\Model\TransactionState::VOIDED:
                    $this->voided($transaction, $order);
                    break;
                case \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED:
                    $this->waiting($transaction, $order);
                    break;
                default:
                    // Nothing to do.
                    break;
            }
        }
    }


    protected function authorize(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (PostFinanceCheckout_Helper::getOrderMeta($sourceOrder, 'authorized')) {
            return;
        }
        //Do not send emails for this status update
        PostFinanceCheckout::startRecordingMailMessages();
        PostFinanceCheckout_Helper::updateOrderMeta($sourceOrder, 'authorized', true);
        $authorizedStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_AUTHORIZED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($authorizedStatusId);
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        if (Configuration::get(PostFinanceCheckout::CK_MAIL, null, null, $sourceOrder->id_shop)) {
           //Send stored messages
            $messages = PostFinanceCheckout_Helper::getOrderEmails($sourceOrder);
            if (count($messages) > 0) {
                if (method_exists('Mail', 'sendMailMessageWithoutHook')) {
                    foreach ($messages as $message) {
                        Mail::sendMailMessageWithoutHook($message, false);
                    }
                }
            }
        }
        PostFinanceCheckout_Helper::deleteOrderEmails($order);
        //Cleanup carts
        $originalCartId = PostFinanceCheckout_Helper::getOrderMeta($order, 'originalCart');
        if (!empty($originalCartId)) {
            $cart = new Cart($originalCartId);
            $cart->delete();
        }
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function waiting(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        PostFinanceCheckout::startRecordingMailMessages();
        $waitingStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_COMPLETED);
        if (! PostFinanceCheckout_Helper::getOrderMeta($sourceOrder, 'manual_check')) {
            $orders = $sourceOrder->getBrother();
            $orders[] = $sourceOrder;
            foreach ($orders as $order) {
                $order->setCurrentState($waitingStatusId);
                $order->save();
            }
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function decline(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(PostFinanceCheckout::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            PostFinanceCheckout::startRecordingMailMessages();
        }
        
        $canceledStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_DECLINED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function failed(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        //Do not send email
        PostFinanceCheckout::startRecordingMailMessages();
        $errorStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_FAILED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($errorStatusId);
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        PostFinanceCheckout_Helper::deleteOrderEmails($sourceOrder);
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function fulfill(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(PostFinanceCheckout::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            PostFinanceCheckout::startRecordingMailMessages();
        }
        $payedStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_FULFILL);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($payedStatusId);
            if (empty($order->invoice_date) || $order->invoice_date == '0000-00-00 00:00:00') {
                //Make sure invoice date is set, otherwise prestashop ignores the order in the statistics
                $order->invoice_date = date('Y-m-d H:i:s');
            }
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function voided(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (!Configuration::get(PostFinanceCheckout::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            //Do not send email
            PostFinanceCheckout::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_VOIDED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
        PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }
}
