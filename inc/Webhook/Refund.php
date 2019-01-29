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
 * Webhook processor to handle refund state transitions.
 */
class PostFinanceCheckout_Webhook_Refund extends PostFinanceCheckout_Webhook_OrderRelatedAbstract
{
       
    /**
    * Processes the received order related webhook request.
    *
    * @param PostFinanceCheckout_Webhook_Request $request
    */
    public function process(PostFinanceCheckout_Webhook_Request $request)
    {
        parent::process($request);
        $refund = $this->loadEntity($request);
        $refundJob = PostFinanceCheckout_Model_RefundJob::loadByExternalId($refund->getLinkedSpaceId(), $refund->getExternalId());
        if ($refundJob->getState() == PostFinanceCheckout_Model_RefundJob::STATE_APPLY) {
            PostFinanceCheckout_Service_Refund::instance()->applyRefundToShop($refundJob->getId());
        }
    }

    /**
     *
     * @see PostFinanceCheckout_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    protected function loadEntity(PostFinanceCheckout_Webhook_Request $request)
    {
        $refundService = new \PostFinanceCheckout\Sdk\Service\RefundService(PostFinanceCheckout_Helper::getApiClient());
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
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\PostFinanceCheckout\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = PostFinanceCheckout_Model_RefundJob::loadByExternalId($refund->getLinkedSpaceId(), $refund->getExternalId());
        if ($refundJob->getId()) {
            $refundJob->setState(PostFinanceCheckout_Model_RefundJob::STATE_FAILURE);
            $refundJob->setRefundId($refund->getId());
            if ($refund->getFailureReason() != null) {
                $refundJob->setFailureReason($refund->getFailureReason()->getDescription());
            }
            $refundJob->save();
        }
    }

    protected function refunded(\PostFinanceCheckout\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = PostFinanceCheckout_Model_RefundJob::loadByExternalId($refund->getLinkedSpaceId(), $refund->getExternalId());
        if ($refundJob->getId()) {
            $refundJob->setState(PostFinanceCheckout_Model_RefundJob::STATE_APPLY);
            $refundJob->setRefundId($refund->getId());
            $refundJob->save();
        }
    }
}
