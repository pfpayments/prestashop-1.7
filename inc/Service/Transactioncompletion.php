<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with PostFinance Checkout transaction completions.
 */
class PostFinanceCheckoutServiceTransactioncompletion extends PostFinanceCheckoutServiceAbstract
{

    /**
     * The transaction completion API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionCompletionService
     */
    private $completionService;

    public function executeCompletion($order)
    {
        $currentCompletionJob = null;
        try {
            PostFinanceCheckoutHelper::startDBTransaction();
            $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactioncompletion'
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

            if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be completed.',
                        'transactioncompletion'
                    )
                );
            }

            if (PostFinanceCheckoutModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Please wait until the existing completion is processed.',
                        'transactioncompletion'
                    )
                );
            }

            if (PostFinanceCheckoutModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'There is a void in process. The order can not be completed.',
                        'transactioncompletion'
                    )
                );
            }

            $completionJob = new PostFinanceCheckoutModelCompletionjob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(PostFinanceCheckoutModelCompletionjob::STATE_CREATED);
            $completionJob->setOrderId(
                PostFinanceCheckoutHelper::getOrderMeta($order, 'postFinanceCheckoutMainOrderId')
            );
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw $e;
        }

        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function updateLineItems($completionJobId)
    {
        $completionJob = new PostFinanceCheckoutModelCompletionjob($completionJobId);
        PostFinanceCheckoutHelper::startDBTransaction();
        PostFinanceCheckoutHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new PostFinanceCheckoutModelCompletionjob($completionJobId);

        if ($completionJob->getState() != PostFinanceCheckoutModelCompletionjob::STATE_CREATED) {
            // Already updated in the meantime
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;

            $lineItems = PostFinanceCheckoutServiceLineitem::instance()->getItemsFromOrders($collected);
            PostFinanceCheckoutServiceTransaction::instance()->updateLineItems(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId(),
                $lineItems
            );
            $completionJob->setState(PostFinanceCheckoutModelCompletionjob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'Could not update the line items. Error: %s',
                                'transactioncompletion'
                            ),
                            PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(PostFinanceCheckoutModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error updating line items for completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
            $message = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'Error updating line items for completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelCompletionjob');
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {
        $completionJob = new PostFinanceCheckoutModelCompletionjob($completionJobId);
        PostFinanceCheckoutHelper::startDBTransaction();
        PostFinanceCheckoutHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new PostFinanceCheckoutModelCompletionjob($completionJobId);

        if ($completionJob->getState() != PostFinanceCheckoutModelCompletionjob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            return;
        }
        try {
            $completion = $this->getCompletionService()->completeOnline(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId()
            );
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(PostFinanceCheckoutModelCompletionjob::STATE_SENT);
            $completionJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'Could not send the completion to %s. Error: %s',
                                'transactioncompletion'
                            ),
                            'PostFinance Checkout',
                            PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(PostFinanceCheckoutModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error sending completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
            $message = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'Error sending completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelCompletionjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = PostFinanceCheckoutModelCompletionjob::loadRunningCompletionForTransaction(
            $spaceId,
            $transactionId
        );
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }

    public function updateCompletions($endTime = null)
    {
        $toProcess = PostFinanceCheckoutModelCompletionjob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            } catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error updating completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelCompletionjob');
            }
        }
    }

    public function hasPendingCompletions()
    {
        $toProcess = PostFinanceCheckoutModelCompletionjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction completion API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TransactionCompletionService
     */
    protected function getCompletionService()
    {
        if ($this->completionService == null) {
            $this->completionService = new \PostFinanceCheckout\Sdk\Service\TransactionCompletionService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }
        return $this->completionService;
    }
}
