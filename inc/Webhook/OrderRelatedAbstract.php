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
 * Abstract webhook processor for order related entities.
 */
abstract class PostFinanceCheckout_Webhook_OrderRelatedAbstract extends PostFinanceCheckout_Webhook_Abstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param PostFinanceCheckout_Webhook_Request $request
     */
    public function process(PostFinanceCheckout_Webhook_Request $request)
    {
        
        PostFinanceCheckout_Helper::startDBTransaction();
        $entity = $this->loadEntity($request);
        try {
            $order = new Order($this->getOrderId($entity));
            if (Validate::isLoadedObject($order)) {
                $ids = PostFinanceCheckout_Helper::getOrderMeta($order, 'mappingIds');

                if ($ids['transactionId'] != $this->getTransactionId($entity)) {
                    return;
                }
                PostFinanceCheckout_Helper::lockByTransactionId($request->getSpaceId(), $this->getTransactionId($entity));
                $order = new Order($this->getOrderId($entity));
                $this->processOrderRelatedInner($order, $entity);
            }
            PostFinanceCheckout_Helper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            throw $e;
        }
    }

    /**
     * Loads and returns the entity for the webhook request.
     *
     * @param PostFinanceCheckout_Webhook_Request $request
     * @return object
     */
    abstract protected function loadEntity(PostFinanceCheckout_Webhook_Request $request);

    /**
     * Returns the order's increment id linked to the entity.
     *
     * @param object $entity
     * @return string
     */
    abstract protected function getOrderId($entity);

    /**
     * Returns the transaction's id linked to the entity.
     *
     * @param object $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);

    /**
     * Actually processes the order related webhook request.
     *
     * This must be implemented
     *
     * @param Order $order
     * @param Object $entity
     */
    abstract protected function processOrderRelatedInner(Order $order, $entity);
}
