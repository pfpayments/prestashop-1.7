<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with PostFinance Checkout transaction completions.
 */
class PostFinanceCheckout_Service_TransactionCompletion extends PostFinanceCheckout_Service_Abstract
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
            PostFinanceCheckout_Helper::startDBTransaction();
            $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Could not load corresponding transaction.'));
            }
           
            PostFinanceCheckout_Helper::lockByTransactionId($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            //Reload after locking
            $transactionInfo = PostFinanceCheckout_Model_TransactionInfo::loadByTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();
            
            if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(PostFinanceCheckout_Helper::getModuleInstance()->l('The transaction is not in a state to be completed.'));
            }
            
            if (PostFinanceCheckout_Model_CompletionJob::isCompletionRunningForTransaction(
                $spaceId, $transactionId)){
                    throw new Exception( PostFinanceCheckout_Helper::getModuleInstance()->l('Please wait until the existing completion is processed.'));
            }
            
            if (PostFinanceCheckout_Model_VoidJob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('There is a void in process. The order can not be completed.'));
            }

            $completionJob = new PostFinanceCheckout_Model_CompletionJob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(PostFinanceCheckout_Model_CompletionJob::STATE_CREATED);
            $completionJob->setOrderId(PostFinanceCheckout_Helper::getOrderMeta($order, 'postFinanceCheckoutMainOrderId'));
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            PostFinanceCheckout_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            throw $e;
        }
        
        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        }
        catch (Exception $e) {
            throw $e;            
        }
    }
    
    
    protected function updateLineItems($completionJobId){
        
        $completionJob = new PostFinanceCheckout_Model_CompletionJob($completionJobId);
        PostFinanceCheckout_Helper::startDBTransaction();
        PostFinanceCheckout_Helper::lockByTransactionId($completionJob->getSpaceId(), $completionJob->getTransactionId());
        // Reload completion job;
        $completionJob = new PostFinanceCheckout_Model_CompletionJob($completionJobId);
        
        if ($completionJob->getState() != PostFinanceCheckout_Model_CompletionJob::STATE_CREATED) {
            //Already updated in the meantime
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;
            
            $lineItems = PostFinanceCheckout_Service_LineItem::instance()->getItemsFromOrders($collected);
            PostFinanceCheckout_Service_Transaction::instance()->updateLineItems($completionJob->getSpaceId(), $completionJob->getTransactionId(), $lineItems);
            $completionJob->setState(PostFinanceCheckout_Model_CompletionJob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $completionJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        PostFinanceCheckout_Helper::getModuleInstance()->l('Could not update the line items. Error: %s'),
                        PostFinanceCheckout_Helper::cleanExceptionMessage($e->getMessage()))
                ));
            
            $completionJob->setState(PostFinanceCheckout_Model_CompletionJob::STATE_FAILURE);
            $completionJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {        
        $completionJob = new PostFinanceCheckout_Model_CompletionJob($completionJobId);
        PostFinanceCheckout_Helper::startDBTransaction();
        PostFinanceCheckout_Helper::lockByTransactionId($completionJob->getSpaceId(), $completionJob->getTransactionId());
        // Reload completion job;
        $completionJob = new PostFinanceCheckout_Model_CompletionJob($completionJobId);
        
        if ($completionJob->getState() != PostFinanceCheckout_Model_CompletionJob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            PostFinanceCheckout_Helper::rollbackDBTransaction();
            return;
        }
        try {                        
            $completion = $this->getCompletionService()->completeOnline($completionJob->getSpaceId(), $completionJob->getTransactionId());
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(PostFinanceCheckout_Model_CompletionJob::STATE_SENT);
            $completionJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
        }
        catch (Exception $e) {
            $completionJob->setFailureReason(
                array(
                    'en-US' => sprintf(
                        PostFinanceCheckout_Helper::getModuleInstance()->l('Could not send the completion to %s. Error: %s'), 'PostFinance Checkout',
                        PostFinanceCheckout_Helper::cleanExceptionMessage($e->getMessage()))
                ));
            $completionJob->setState(PostFinanceCheckout_Model_CompletionJob::STATE_FAILURE);
            $completionJob->save();
            PostFinanceCheckout_Helper::commitDBTransaction();
            throw $e;
        }
    }
    

    public function updateForOrder($order)
    {
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = PostFinanceCheckout_Model_CompletionJob::loadRunningCompletionForTransaction($spaceId, $transactionId);
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }
        
    public function updateCompletions($endTime = null)
    {
        $toProcess = PostFinanceCheckout_Model_CompletionJob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if($endTime!== null && time()+15 > $endTime){
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            }
            catch (Exception $e) {
                $message = sprintf(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('Error updating completion job with id %d: %s'), $id,
                    $e->getMessage());
                PrestaShopLogger::addLog($message, 3, null, 'PostFinanceCheckout_Model_CompletionJob');
                
            }
        }
    }
    
    public function hasPendingCompletions(){
        $toProcess = PostFinanceCheckout_Model_CompletionJob::loadNotSentJobIds();
        return !empty($toProcess);
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
                PostFinanceCheckout_Helper::getApiClient());
        }
        return $this->completionService;
    }
}
