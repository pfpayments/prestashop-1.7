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

class AdminPostFinanceCheckoutOrderController extends ModuleAdminController
{

    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess($this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders'));
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('You do not have permission to edit the order.')
                ));
            die();
        }
    }

    public function ajaxProcessUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckout_Service_TransactionVoid::instance()->updateForOrder($order);
                PostFinanceCheckout_Service_TransactionCompletion::instance()->updateForOrder($order);
                echo Tools::jsonEncode(array(
                    'success' => 'true'
                ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => $e->getMessage()
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.')
                ));
            die();
        }
    }

    public function ajaxProcessVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckout_Service_TransactionVoid::instance()->executeVoid($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l('The order is updated automatically once the void is processed.')
                    ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => PostFinanceCheckout_Helper::cleanExceptionMessage($e->getMessage())
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.')
                ));
            die();
        }
    }
    
    public function ajaxProcessCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckout_Service_TransactionCompletion::instance()->executeCompletion($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l('The order is updated automatically once the completion is processed.')
                    ));
                die();
            }
            catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => PostFinanceCheckout_Helper::cleanExceptionMessage($e->getMessage())
                    ));
                die();
            }
        }
        else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.')
                ));
            die();
        }
    }
}
    
