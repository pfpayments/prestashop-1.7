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
 * Webhook processor to handle transaction void state transitions.
 */
class PostFinanceCheckoutWebhookTransactionvoid extends PostFinanceCheckoutWebhookOrderrelatedabstract
{

    /**
     *
     * @see PostFinanceCheckoutWebhookOrderrelatedabstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\TransactionVoid
     */
    protected function loadEntity(PostFinanceCheckoutWebhookRequest $request)
    {
        $voidService = new \PostFinanceCheckout\Sdk\Service\TransactionVoidService(
            PostFinanceCheckoutHelper::getApiClient()
        );
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
        $voidJob = PostFinanceCheckoutModelVoidjob::loadByVoidId($void->getLinkedSpaceId(), $void->getId());
        if (! $voidJob->getId()) {
            // We have no void job with this id -> the server could not store the id of the void after sending the
            // request. (e.g. connection issue or crash)
            // We only have on running void which was not yet processed successfully and use it as it should be the one
            // the webhook is for.
            $voidJob = PostFinanceCheckoutModelVoidjob::loadRunningVoidForTransaction(
                $void->getLinkedSpaceId(),
                $void->getLinkedTransaction()
            );
            if (! $voidJob->getId()) {
                // void not initated in shop backend ignore
                return;
            }
            $voidJob->setVoidId($void->getId());
        }
        if ($success) {
            $voidJob->setState(PostFinanceCheckoutModelVoidjob::STATE_SUCCESS);
        } else {
            if ($voidJob->getFailureReason() != null) {
                $voidJob->setFailureReason($void->getFailureReason()
                    ->getDescription());
            }
            $voidJob->setState(PostFinanceCheckoutModelVoidjob::STATE_FAILURE);
        }
        $voidJob->save();
    }
}
