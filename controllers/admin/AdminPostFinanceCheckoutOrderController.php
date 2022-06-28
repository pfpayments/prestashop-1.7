<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
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
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l(
                        'You do not have permission to edit the order.',
                        'adminpostfinancecheckoutordercontroller'
                    )
                )
            );
            die();
        }
    }

    public function ajaxProcessUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckoutServiceTransactioncompletion::instance()->updateForOrder($order);
                PostFinanceCheckoutServiceTransactioncompletion::instance()->updateForOrder($order);
                echo Tools::jsonEncode(array(
                    'success' => 'true'
                ));
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(array(
                    'success' => 'false',
                    'message' => $e->getMessage()
                ));
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminpostfinancecheckoutordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckoutServiceTransactionvoid::instance()->executeVoid($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the void is processed.',
                            'adminpostfinancecheckoutordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminpostfinancecheckoutordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                PostFinanceCheckoutServiceTransactioncompletion::instance()->executeCompletion($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the completion is processed.',
                            'adminpostfinancecheckoutordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminpostfinancecheckoutordercontroller')
                )
            );
            die();
        }
    }
}
