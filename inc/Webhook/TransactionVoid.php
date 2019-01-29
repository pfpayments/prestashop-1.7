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
 * Webhook processor to handle transaction void state transitions.
 */
class PostFinanceCheckout_Webhook_TransactionVoid extends PostFinanceCheckout_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see PostFinanceCheckout_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\TransactionVoid
     */
    protected function loadEntity(PostFinanceCheckout_Webhook_Request $request)
    {
        $voidService = new \PostFinanceCheckout\Sdk\Service\TransactionVoidService(PostFinanceCheckout_Helper::getApiClient());
        return $voidService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($void)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\TransactionVoid $void */
        return $void->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($void)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\TransactionVoid $void */
        return $void->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $void)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\TransactionVoid $void */
        switch ($void->getState()) {
            case \PostFinanceCheckout\Sdk\Model\TransactionVoidState::FAILED:
                $this->update($void, $order, false);
                break;
            case \PostFinanceCheckout\Sdk\Model\TransactionVoidState::SUCCESSFUL:
                $this->update($void, $order, true);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function update(\PostFinanceCheckout\Sdk\Model\TransactionVoid $void, Order $order, $success)
    {
        $voidJob = PostFinanceCheckout_Model_VoidJob::loadByVoidId($void->getLinkedSpaceId(), $void->getId());
        if (!$voidJob->getId()) {
            //We have no void job with this id -> the server could not store the id of the void after sending the request. (e.g. connection issue or crash)
            //We only have on running void which was not yet processed successfully and use it as it should be the one the webhook is for.
            $voidJob = PostFinanceCheckout_Model_VoidJob::loadRunningVoidForTransaction($void->getLinkedSpaceId(), $void->getLinkedTransaction());
            if (!$voidJob->getId()) {
                //void not initated in shop backend ignore
                return;
            }
            $voidJob->setVoidId($void->getId());
        }
        if ($success) {
            $voidJob->setState(PostFinanceCheckout_Model_VoidJob::STATE_SUCCESS);
        } else {
            if ($voidJob->getFailureReason() != null) {
                $voidJob->setFailureReason($void->getFailureReason()->getDescription());
            }
            $voidJob->setState(PostFinanceCheckout_Model_VoidJob::STATE_FAILURE);
        }
        $voidJob->save();
    }
}
