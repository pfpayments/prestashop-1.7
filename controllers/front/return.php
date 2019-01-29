<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckoutReturnModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    /**
     *
     * @see FrontController::initContent()
     */
    public function postProcess()
    {
        $orderId = Tools::getValue('order_id', null);
        $orderKey = Tools::getValue('secret', null);
        $action = Tools::getValue('action', null);
        
        if ($orderId != null) {
            $order = new Order($orderId);
            if ($orderKey == null || $orderKey != PostFinanceCheckout_Helper::computeOrderSecret($order)) {
                $error = Tools::displayError('Invalid Secret.');
                die($error);
            }
            switch ($action) {
                case 'success':
                    $this->processSuccess($order);
                    
                    return;
                case 'failure':
                    self::processFailure($order);
                    
                    return;
                default:
            }
        }
        $error = Tools::displayError('Invalid Request.');
        die($error);
    }

    private function processSuccess(Order $order)
    {
        $transactionService = PostFinanceCheckout_Service_Transaction::instance();
        $transactionService->waitForTransactionState(
            $order,
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::CONFIRMED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::PENDING,
                \PostFinanceCheckout\Sdk\Model\TransactionState::PROCESSING
            ),
            5
        );
        $cartId = $order->id_cart;
        $customer = new Customer($order->id_customer);
        
        $this->redirect_after = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            array(
                'id_cart' => $cartId,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $customer->secure_key
            )
        );
    }

    private function processFailure(Order $order)
    {
        $transactionService = PostFinanceCheckout_Service_Transaction::instance();
        $transactionService->waitForTransactionState(
            $order,
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED
            ),
            5
        );
        $transaction = PostFinanceCheckout_Model_TransactionInfo::loadByOrderId($order->id);
        
        $userFailureMessage = $transaction->getUserFailureMessage();
        
        if (empty($userFailureMessage)) {
           	$failureReason = $transaction->getFailureReason();
        
        	if ($failureReason !== null) {
           	    $userFailureMessage = PostFinanceCheckout_Helper::translate($failureReason);
           	}
        }
        
        if (!empty($userFailureMessage)) {
            $this->context->cookie->pfc_error = $userFailureMessage;
        }
        
        $this->redirect_after = $this->context->link->getPageLink('order', true, null, "step=3");
    }

    public function setMedia()
    {
        // We do not need styling here
    }
    
    protected function displayMaintenancePage()
    {
        // We never display the maintenance page.
    }
    
    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }
    
    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}
