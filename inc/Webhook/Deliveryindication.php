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
 * Webhook processor to handle delivery indication state transitions.
 */
class PostFinanceCheckoutWebhookDeliveryindication extends PostFinanceCheckoutWebhookOrderrelatedabstract
{

    /**
     *
     * @see PostFinanceCheckoutWebhookOrderrelatedabstract::loadEntity()
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(PostFinanceCheckoutWebhookRequest $request)
    {
        $deliveryIndicationService = new \PostFinanceCheckout\Sdk\Service\DeliveryIndicationService(
            PostFinanceCheckoutHelper::getApiClient()
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
        PostFinanceCheckoutBasemodule::startRecordingMailMessages();
        $manualStatusId = Configuration::get(PostFinanceCheckoutBasemodule::CK_STATUS_MANUAL);
        PostFinanceCheckoutHelper::updateOrderMeta($sourceOrder, 'manual_check', true);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($manualStatusId);
            $order->save();
        }
        PostFinanceCheckoutBasemodule::stopRecordingMailMessages();
    }
}
