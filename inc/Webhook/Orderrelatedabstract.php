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
 * Abstract webhook processor for order related entities.
 */
abstract class PostFinanceCheckoutWebhookOrderrelatedabstract extends PostFinanceCheckoutWebhookAbstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param PostFinanceCheckoutWebhookRequest $request
     */
    public function process(PostFinanceCheckoutWebhookRequest $request)
    {
        PostFinanceCheckoutHelper::startDBTransaction();
        $entity = $this->loadEntity($request);
        try {
            $order = new Order($this->getOrderId($entity));
            if (Validate::isLoadedObject($order)) {
                $ids = PostFinanceCheckoutHelper::getOrderMeta($order, 'mappingIds');
                if ($ids['transactionId'] != $this->getTransactionId($entity)) {
                    return;
                }
                // We never have an employee on webhooks, but the stock magement sometimes needs one
                if (Context::getContext()->employee == null) {
                    $employees = Employee::getEmployeesByProfile(_PS_ADMIN_PROFILE_, true);
                    $employeeArray = reset($employees);
                    Context::getContext()->employee = new Employee($employeeArray['id_employee']);
                }
                PostFinanceCheckoutHelper::lockByTransactionId(
                    $request->getSpaceId(),
                    $this->getTransactionId($entity)
                );
                $order = new Order($this->getOrderId($entity));
                $this->processOrderRelatedInner($order, $entity);
            }
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw $e;
        }
    }

    /**
     * Loads and returns the entity for the webhook request.
     *
     * @param PostFinanceCheckoutWebhookRequest $request
     * @return object
     */
    abstract protected function loadEntity(PostFinanceCheckoutWebhookRequest $request);

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
