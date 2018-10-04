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
 * This service provides functions to deal with PostFinance Checkout transaction voids.
 */
class PostFinanceCheckout_Service_TransactionVoid extends PostFinanceCheckout_Service_Abstract
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
            PostFinanceCheckout_Helper::startDBTransaction();
            $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Could not load corresponding transaction.', 'transactionvoid')
                );
            }
           
            PostFinanceCheckout_Helper::lockByTransactionId($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            //Reload after locking
            $transactionInfo = PostFinanceCheckout_Model_TransactionInfo::loadByTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();
            
            if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(PostFinanceCheckout_Helper::getModuleInstance()->l('The transaction is not in a state to be voided.', 'transactionvoid'));
            }
            if (PostFinanceCheckout_Model_VoidJob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Please wait until the existing void is processed.', 'transactionvoid')
                );
            }
            if (PostFinanceCheckout_Model_CompletionJob::isCompletionRunningForTransaction(
                $spaceId,
                $transactionId
            )) {
                    throw new Exception(PostFinanceCheckout_Helper::getModuleInstance()->l('There is a completion in process. The order can not be voided.', 'transactionvoid'));
            }
            
            $voidJob = new PostFinanceCheckout_Model_VoidJob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(PostFinanceCheckout_Model_VoidJob::STATE_CREATED);
            $voidJob->setOrderId(PostFinanceCheckout_Helper::getOrderMeta($order, 'postFinanceCheckoutMainOrderId'));
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            PostFinanceCheckout_Helper::commitDBTransaction();
        } catch (Exception $e) {
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
    }

    protected function sendVoid($voidJobId)
    {
        $voidJob = new PostFinanceCheckout_Model_VoidJob($voidJobId);
        PostFinanceCheckout_Helper::startDBTransaction();
        PostFinanceCheckout_Helper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new PostFinanceCheckout_Model_VoidJob($voidJobId);
        if ($voidJob->getState() != PostFinanceCheckout_Model_VoidJob::STATE_CREATED) {
            // Already sent in the meantime
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            return;
        }
        try {
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(PostFinanceCheckout_Model_VoidJob::STATE_SENT);
            $voidJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
        } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \PostFinanceCheckout\Sdk\Model\ClientError) {
                $voidJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            PostFinanceCheckout_Helper::getModuleInstance()->l('Could not send the void to %s. Error: %s', 'transactionvoid'),
                            'PostFinance Checkout',
                            PostFinanceCheckout_Helper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $voidJob->setState(PostFinanceCheckout_Model_VoidJob::STATE_FAILURE);
                $voidJob->save();
                PostFinanceCheckout_Helper::commitDBTransaction();
            } else {
                $voidJob->save();
                PostFinanceCheckout_Helper::commitDBTransaction();
                $message = sprintf(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Error sending void job with id %d: %s', 'transactionvoid'),
                    $voidJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckout_Model_VoidJob');
                throw $e;
            }
        } catch (Exception $e) {
            $voidJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
            $message = sprintf(
                PostFinanceCheckout_Helper::getModuleInstance()->l('Error sending void job with id %d: %s', 'transactionvoid'),
                $voidJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckout_Model_VoidJob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = PostFinanceCheckout_Model_VoidJob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == PostFinanceCheckout_Model_VoidJob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = PostFinanceCheckout_Model_VoidJob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if ($endTime!== null && time()+15 > $endTime) {
                return;
            }
            try {
                $this->sendVoid($id);
            } catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Error updating void job with id %d: %s', 'transactionvoid'),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckout_Model_VoidJob');
            }
        }
    }
    
    public function hasPendingVoids()
    {
        $toProcess = PostFinanceCheckout_Model_VoidJob::loadNotSentJobIds();
        return !empty($toProcess);
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
                PostFinanceCheckout_Helper::getApiClient()
            );
        }
        
        return $this->voidService;
    }
}
