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
            $this->createRefundObject($refundJob);
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
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions);

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
    protected function fixReductions($refundTotal, $spaceId, $transactionId, array $reductions)
    {
        $baseLineItems = $this->getBaseLineItems($spaceId, $transactionId);
        $reductionAmount = PostFinanceCheckoutHelper::getReductionAmount($baseLineItems, $reductions);

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
