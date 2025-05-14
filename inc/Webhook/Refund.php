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
 * Webhook processor to handle refund state transitions.
 */
class PostFinanceCheckoutWebhookRefund extends PostFinanceCheckoutWebhookOrderrelatedabstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param PostFinanceCheckoutWebhookRequest $request
     */
    public function process(PostFinanceCheckoutWebhookRequest $request)
    {
        parent::process($request);
        $refund = $this->loadEntity($request);
        $refundJob = PostFinanceCheckoutModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getState() == PostFinanceCheckoutModelRefundjob::STATE_APPLY) {
            PostFinanceCheckoutServiceRefund::instance()->applyRefundToShop($refundJob->getId());
        }
    }

    /**
     *
     * @see PostFinanceCheckoutWebhookOrderrelatedabstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    protected function loadEntity(PostFinanceCheckoutWebhookRequest $request)
    {
        $refundService = new \PostFinanceCheckout\Sdk\Service\RefundService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($refund)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($refund)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Order $order, $refund)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \PostFinanceCheckout\Sdk\Model\RefundState::FAILED:
                $this->failed($refund, $order);
                break;
            case \PostFinanceCheckout\Sdk\Model\RefundState::SUCCESSFUL:
                $this->refunded($refund, $order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\PostFinanceCheckout\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = PostFinanceCheckoutModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_FAILURE);
            $refundJob->setRefundId($refund->getId());
            if ($refund->getFailureReason() != null) {
                $refundJob->setFailureReason($refund->getFailureReason()
                    ->getDescription());
            }
            $refundJob->save();
        }
    }

    protected function refunded(\PostFinanceCheckout\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = PostFinanceCheckoutModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_APPLY);
            $refundJob->setRefundId($refund->getId());
            $refundJob->save();
        }
    }
}
