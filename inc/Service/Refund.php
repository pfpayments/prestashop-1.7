<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with PostFinance Checkout refunds.
 */
class PostFinanceCheckoutServiceRefund extends PostFinanceCheckoutServiceAbstract
{
    private static $refundableStates = array(
        \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
        \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE,
        \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL
    );

    /**
     * The refund API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\RefundService
     */
    private $refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            throw new Exception('The refund could not be found.');
        }
    }

    /**
     * Executes the refund, saving it in the database (via database transaction)
     * and then sending the refund information to the portal.
     *
     * @param Order $order
     * @param array $parsedParameters
     * @return void
     *
     * @see hookActionProductCancel
     */
    public function executeRefund(Order $order, array $parsedParameters)
    {
        $currentRefundJob = null;
        try {
            PostFinanceCheckoutHelper::startDBTransaction();
            $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction',
                        'refund'
                    )
                );
            }

            PostFinanceCheckoutHelper::lockByTransactionId(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            // Reload after locking
            $transactionInfo = PostFinanceCheckoutModelTransactioninfo::loadByTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();

            if (! in_array($transactionInfo->getState(), self::$refundableStates)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be refunded.',
                        'refund'
                    )
                );
            }

            if (PostFinanceCheckoutModelRefundjob::isRefundRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Please wait until the existing refund is processed.',
                        'refund'
                    )
                );
            }

            $refundJob = new PostFinanceCheckoutModelRefundjob();
            $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_CREATED);
            $refundJob->setOrderId($order->id);
            $refundJob->setSpaceId($transactionInfo->getSpaceId());
            $refundJob->setTransactionId($transactionInfo->getTransactionId());
            $refundJob->setExternalId(uniqid($order->id . '-'));
            $refundJob->setRefundParameters($parsedParameters);
            $refundJob->save();
            // validate Refund Job
            // $this->createRefundObject($refundJob);
            $currentRefundJob = $refundJob->getId();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendRefund($currentRefundJob);
    }

    protected function sendRefund($refundJobId)
    {
        $refundJob = new PostFinanceCheckoutModelRefundjob($refundJobId);
        PostFinanceCheckoutHelper::startDBTransaction();
        PostFinanceCheckoutHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new PostFinanceCheckoutModelRefundjob($refundJobId);
        if ($refundJob->getState() != PostFinanceCheckoutModelRefundjob::STATE_CREATED) {
            // Already sent in the meantime
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            return;
        }
        try {
            $executedRefund = $this->refund($refundJob->getSpaceId(), $this->createRefundObject($refundJob));
            $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_SENT);
            $refundJob->setRefundId($executedRefund->getId());

            if ($executedRefund->getState() == \PostFinanceCheckout\Sdk\Model\RefundState::PENDING) {
                $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_PENDING);
            }
            $refundJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                $refundJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'Could not send the refund to %s. Error: %s',
                                'refund'
                            ),
                            'PostFinance Checkout',
                            PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_FAILURE);
                $refundJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
            } else {
                $refundJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error sending refund job with id %d: %s',
                        'refund'
                    ),
                    $refundJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelRefundjob');
                throw $e;
            }
        } catch (Exception $e) {
            $refundJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
            $message = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l('Error sending refund job with id %d: %s', 'refund'),
                $refundJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelRefundjob');
            throw $e;
        }
    }

    /**
     * This functionality is called from a webhook, triggered by the portal.
     * Updates the status of the order in the shop.
     *
     * @param [type] $refundJobId
     * @return void
     *
     * @see PostFinanceCheckoutWebhookRefund::process
     */
    public function applyRefundToShop($refundJobId)
    {
        $refundJob = new PostFinanceCheckoutModelRefundjob($refundJobId);
        PostFinanceCheckoutHelper::startDBTransaction();
        PostFinanceCheckoutHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new PostFinanceCheckoutModelRefundjob($refundJobId);
        if ($refundJob->getState() != PostFinanceCheckoutModelRefundjob::STATE_APPLY) {
            // Already processed in the meantime
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            return;
        }
        try {
            $order = new Order($refundJob->getOrderId());
            $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
            $appliedData = $strategy->applyRefund($order, $refundJob->getRefundParameters());
            $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_SUCCESS);
            $refundJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
            try {
                $strategy->afterApplyRefundActions($order, $refundJob->getRefundParameters(), $appliedData);
            } catch (Exception $e) {
                // We ignore errors in the after apply actions
            }
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            PostFinanceCheckoutHelper::startDBTransaction();
            PostFinanceCheckoutHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
            $refundJob = new PostFinanceCheckoutModelRefundjob($refundJobId);
            $refundJob->increaseApplyTries();
            if ($refundJob->getApplyTries() > 3) {
                $refundJob->setState(PostFinanceCheckoutModelRefundjob::STATE_FAILURE);
                $refundJob->setFailureReason(array(
                    'en-US' => sprintf($e->getMessage())
                ));
            }
            $refundJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $refundJob = PostFinanceCheckoutModelRefundjob::loadRunningRefundForTransaction($spaceId, $transactionId);
        if ($refundJob->getState() == PostFinanceCheckoutModelRefundjob::STATE_CREATED) {
            $this->sendRefund($refundJob->getId());
        } elseif ($refundJob->getState() == PostFinanceCheckoutModelRefundjob::STATE_APPLY) {
            $this->applyRefundToShop($refundJob->getId());
        }
    }

    public function updateRefunds($endTime = null)
    {
        $toSend = PostFinanceCheckoutModelRefundjob::loadNotSentJobIds();
        foreach ($toSend as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendRefund($id);
            } catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error updating refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelRefundjob');
            }
        }
        $toApply = PostFinanceCheckoutModelRefundjob::loadNotAppliedJobIds();
        foreach ($toApply as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->applyRefundToShop($id);
            } catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error applying refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelRefundjob');
            }
        }
    }

    public function hasPendingRefunds()
    {
        $toSend = PostFinanceCheckoutModelRefundjob::loadNotSentJobIds();
        $toApply = PostFinanceCheckoutModelRefundjob::loadNotAppliedJobIds();
        return ! empty($toSend) || ! empty($toApply);
    }

    /**
     * Creates a refund request model for the given parameters.
     *
     * @param Order $order
     * @param array $refund
     *            Refund data to be determined
     * @return \PostFinanceCheckout\Sdk\Model\RefundCreate
     */
    protected function createRefundObject(PostFinanceCheckoutModelRefundjob $refundJob)
    {
        $order = new Order($refundJob->getOrderId());

        $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();

        $spaceId = $refundJob->getSpaceId();
        $transactionId = $refundJob->getTransactionId();
        $externalRefundId = $refundJob->getExternalId();
        $parsedData = $refundJob->getRefundParameters();
        $amount = $strategy->getRefundTotal($parsedData);
        $type = $strategy->getPostFinanceCheckoutRefundType($parsedData);

        $reductions = $strategy->createReductions($order, $parsedData);
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions, $order);

        $remoteRefund = new \PostFinanceCheckout\Sdk\Model\RefundCreate();
        $remoteRefund->setExternalId($externalRefundId);
        $remoteRefund->setReductions($reductions);
        $remoteRefund->setTransaction($transactionId);
        $remoteRefund->setType($type);

        return $remoteRefund;
    }

    /**
     * Returns the fixed line item reductions for the refund.
     *
     * If the amount of the given reductions does not match the refund's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param float $refundTotal
     * @param int $spaceId
     * @param int $transactionId
     * @param \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate[] $reductions
     * @return \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate[]
     */
    protected function fixReductions($refundTotal, $spaceId, $transactionId, array $reductions, Order $order)
    {
        $baseLineItems = $this->getBaseLineItems($spaceId, $transactionId);
        $reductionAmount = PostFinanceCheckoutHelper::getReductionAmount($baseLineItems, $reductions);
        // We try to get the information about which Line Items need to be reduced.
        // We have this information in the REQUEST object.
        $line_items = $order->getProducts();
        $line_items_ids = [];
        foreach ($line_items as $line_item) {
            $line_items_ids[$line_item['product_reference']] = [
                'unit_price' => $line_item['unit_price_tax_incl'],
                'id' => $line_item['id_order_detail'],
            ];
        }

        $fixedReductions = [];
        $refunded_value = 0;
        foreach ($baseLineItems as $lineItem) {
            // The way of identify the lineItem is based on the position in the array.
            $sku = $lineItem->getSku();
            if (!(empty($line_items_ids[$sku]['id']))) {
                $line_item_id = $line_items_ids[$sku]['id'];
                $unit_price = round((float) $line_items_ids[$sku]['unit_price'], 2);
                if (!(empty($_REQUEST['cancel_product']['amount_' . $line_item_id]))) {
                    $refund_value = (float) $_REQUEST['cancel_product']['amount_' . $line_item_id];
                    if ($refund_value > 0 && $refund_value <= $refundTotal) {
                        $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
                        $reduction->setLineItemUniqueId($lineItem->getUniqueId());

                        // Prestashop forces to, at least, refund a quantity of 1.
                        $quantity = 1;
                        if (!empty($_REQUEST['cancel_product']['quantity_' . $line_item_id])) {
                            $quantity = (int) $_REQUEST['cancel_product']['quantity_' . $line_item_id];
                        }

                        // Prestashop allows to refund whole quantites (the whole unit price), or a reduced value between the unit price.
                        // For example, if unit price was 30, and the customer got 10 of them, Prestashop expects the admin to refund a
                        // quantity of items and then allow reduce the value to be refunded. So, the admin can:
                        // - Refund 2 items => 2*30 = 60
                        // - Refund 2 items and reduce the refund value => A value less than 60
                        // - But not to refund 2 items and increase the refund value => A value bigger than 60. If the user does this in the form,
                        //   the system does not complain but the total amount refunded is still 60.
                        //
                        // We need to accomodate here also what is expected by the SDK
                        // In the SDK, we can set how many quantites can be refunded. But we cannot decrease the value to refund after setting the
                        // quantity. So, following the example:
                        // - Refund just 2 items => setQuantityReduction(2), setUnitPriceReduction(0)
                        // - Refund 2 items and reduce the refund value => depending on the value to reduce, if it's bigger than unit price.
                        // - - If for example the reduce value is 20 (less than 30): setQuantityReduction(0), setUnitPriceReduction(20)
                        // - - If the reduce value is 40 (more than 30): setQuantityReduction(1), setUnitPriceReduction(10)
                        // - Refund 2 items and increase the refund value => Replicate how Prestashop works, this is, just refund as many items and
                        //   ignore the rest: setQuantityReduction(2), setUnitPriceReduction(0)
                        //
                        // On top of that, we need to send the data to the SDK using its format. The SDK can contain a unit_price lower than the
                        // one in the shop. This happens if there has been previous refundings. The same happens with the number of items that
                        // potentially can yet be refunded. So we need to use the SDK values for the calculations.
                        // Finally, the setUnitPriceReduction method will reduce the given value equally through all the remaining items in the line.
                        // This is why we divide, right before setting, the amount to be refunded by all the remaining items in the line. The SDK,
                        // back in the portal, will undo this operation and refund the money we are expecting to refund.

                        $unit_price_sdk = $lineItem->getUnitPriceIncludingTax();
                        $line_quantity_sdk = $lineItem->getQuantity();

                        $max_to_refund = $unit_price * $quantity;
                        if ($max_to_refund <= $refund_value) {
                            $reduction->setQuantityReduction($quantity);
                            $reduction->setUnitPriceReduction(($unit_price - $unit_price_sdk)/($line_quantity_sdk - $quantity));
                        } else {
                            $items_to_refund = (int) ($refund_value / $unit_price_sdk);
                            // Multiply by 100 for converting the cents (2 digits) into integer, as % only works with integers.
                            $rest_from_whole_item = ((int)(100 * $refund_value) % (int)(100 * $unit_price_sdk)) / 100;

                            $reduction->setQuantityReduction($items_to_refund);
                            $reduction->setUnitPriceReduction($rest_from_whole_item / ($line_quantity_sdk - $items_to_refund));
                        }

                        $fixedReductions[] = $reduction;
                        $refunded_value += $refund_value;
                    }
                }
            }
        }

        if ($refunded_value <= $refundTotal && !empty($fixedReductions)) {
            return $fixedReductions;
        }

        $fixedReductions = [];
        $configuration = PostFinanceCheckoutVersionadapter::getConfigurationInterface();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        if (Tools::ps_round($refundTotal, $computePrecision) != Tools::ps_round($reductionAmount, $computePrecision)) {
            $fixedReductions = array();
            $baseAmount = PostFinanceCheckoutHelper::getTotalAmountIncludingTax($baseLineItems);
            $rate = $refundTotal / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    round($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), 8)
                );
                $fixedReductions[] = $reduction;
            }

            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\RefundCreate $refund
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    public function refund($spaceId, \PostFinanceCheckout\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the completed transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \PostFinanceCheckout\Sdk\Model\Refund $refund
     * @return \PostFinanceCheckout\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, $transactionId, \PostFinanceCheckout\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws Exception
     * @return \PostFinanceCheckout\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();

        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter(
                    'state',
                    \PostFinanceCheckout\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \PostFinanceCheckout\Sdk\Model\CriteriaOperator::NOT_EQUALS
                ),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $invoiceService = new \PostFinanceCheckout\Sdk\Service\TransactionInvoiceService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \PostFinanceCheckout\Sdk\Model\Refund $refund
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund(
        $spaceId,
        $transactionId,
        \PostFinanceCheckout\Sdk\Model\Refund $refund = null
    ) {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();

        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transactionId)
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter(
                'id',
                $refund->getId(),
                \PostFinanceCheckout\Sdk\Model\CriteriaOperator::NOT_EQUALS
            );
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn', \PostFinanceCheckout\Sdk\Model\EntityQueryOrderByType::DESC)
            )
        );
        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->refundService == null) {
            $this->refundService = new \PostFinanceCheckout\Sdk\Service\RefundService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }

        return $this->refundService;
    }
}
