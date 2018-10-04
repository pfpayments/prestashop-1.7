<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle delivery indication state transitions.
 */
class PostFinanceCheckout_Webhook_DeliveryIndication extends PostFinanceCheckout_Webhook_OrderRelatedAbstract
{

    /**
     *
     * @see PostFinanceCheckout_Webhook_OrderRelatedAbstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(PostFinanceCheckout_Webhook_Request $request)
    {
        $deliveryIndicationService = new \PostFinanceCheckout\Sdk\Service\DeliveryIndicationService(
            PostFinanceCheckout_Helper::getApiClient()
        );
        return $deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($deliveryIndication)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\DeliveryIndication $delivery_indication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $deliveryIndication)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \PostFinanceCheckout\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                break;
        }
    }

    protected function review(Order $sourceOrder)
    {
        PostFinanceCheckout::startRecordingMailMessages();
        $manualStatusId = Configuration::get(PostFinanceCheckout::CK_STATUS_MANUAL);
        PostFinanceCheckout_Helper::updateOrderMeta($sourceOrder, 'manual_check', true);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($manualStatusId);
            $order->save();
        }
        PostFinanceCheckout::stopRecordingMailMessages();
    }
}
