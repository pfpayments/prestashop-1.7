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
 * This service provides functions to deal with PostFinance Checkout transaction voids.
 */
class PostFinanceCheckoutServiceTransactionvoid extends PostFinanceCheckoutServiceAbstract
{

    /**
     * The transaction void API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionVoidService
     */
    private $voidService;

    public function executeVoid($order)
    {
        $currentVoidId = null;
        try {
            PostFinanceCheckoutHelper::startDBTransaction();
            $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactionvoid'
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
                        'The transaction is not in a state to be voided.',
                        'transactionvoid'
                    )
                );
            }
            if (PostFinanceCheckoutModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Please wait until the existing void is processed.',
                        'transactionvoid'
                    )
                );
            }
            if (PostFinanceCheckoutModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'There is a completion in process. The order can not be voided.',
                        'transactionvoid'
                    )
                );
            }

            $voidJob = new PostFinanceCheckoutModelVoidjob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(PostFinanceCheckoutModelVoidjob::STATE_CREATED);
            $voidJob->setOrderId(
                PostFinanceCheckoutHelper::getOrderMeta($order, 'postFinanceCheckoutMainOrderId')
            );
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
    }

    protected function sendVoid($voidJobId)
    {
        $voidJob = new PostFinanceCheckoutModelVoidjob($voidJobId);
        PostFinanceCheckoutHelper::startDBTransaction();
        PostFinanceCheckoutHelper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new PostFinanceCheckoutModelVoidjob($voidJobId);
        if ($voidJob->getState() != PostFinanceCheckoutModelVoidjob::STATE_CREATED) {
            // Already sent in the meantime
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            return;
        }
        try {
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(PostFinanceCheckoutModelVoidjob::STATE_SENT);
            $voidJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
        } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                $voidJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'Could not send the void to %s. Error: %s',
                                'transactionvoid'
                            ),
                            'PostFinance Checkout',
                            PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $voidJob->setState(PostFinanceCheckoutModelVoidjob::STATE_FAILURE);
                $voidJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
            } else {
                $voidJob->save();
                PostFinanceCheckoutHelper::commitDBTransaction();
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error sending void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $voidJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelVoidjob');
                throw $e;
            }
        } catch (Exception $e) {
            $voidJob->save();
            PostFinanceCheckoutHelper::commitDBTransaction();
            $message = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'Error sending void job with id %d: %s',
                    'transactionvoid'
                ),
                $voidJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelVoidjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = PostFinanceCheckoutModelVoidjob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == PostFinanceCheckoutModelVoidjob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = PostFinanceCheckoutModelVoidjob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendVoid($id);
            } catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Error updating void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckoutModelVoidjob');
            }
        }
    }

    public function hasPendingVoids()
    {
        $toProcess = PostFinanceCheckoutModelVoidjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TransactionVoidService
     */
    protected function getVoidService()
    {
        if ($this->voidService == null) {
            $this->voidService = new \PostFinanceCheckout\Sdk\Service\TransactionVoidService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }

        return $this->voidService;
    }
}
