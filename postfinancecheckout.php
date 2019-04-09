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

if (! defined('_PS_VERSION_')) {
    exit();
}

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'postfinancecheckout_autoloader.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'postfinancecheckout-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');

class PostFinanceCheckout extends PostFinanceCheckout_AbstractModule
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'postfinancecheckout';
        $this->tab = 'payments_gateways';
        $this->author = 'Customweb GmbH';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->version = '1.1.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        parent::__construct();
    }
    
    protected function installHooks()
    {
        return parent::installHooks() && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionCronJob') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    protected function getBackendControllers()
    {
        return array(
            'AdminPostFinanceCheckoutMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'PostFinance Checkout ' . $this->l('Payment Methods')
            ),
            'AdminPostFinanceCheckoutDocuments' => array(
                'parentId' => - 1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Documents')
            ),
            'AdminPostFinanceCheckoutOrder' => array(
                'parentId' => - 1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Order Management')
            )
        );
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallControllers() &&
            $this->uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = $this->getMailHookActiveWarning();
        $output .= $this->getCronModuleActiveWarning();
        $output .= $this->handleSaveAll();
        $output .= $this->handleSaveApplication();
        $output .= $this->handleSaveEmail();
        $output .= $this->handleSaveFeeItem();
        $output .= $this->handleSaveDownload();
        $output .= $this->handleSaveSpaceViewId();
        $output .= $this->handleSaveOrderStatus();
        $output .= $this->displayHelpButtons();
        return $output . $this->displayForm();
    }

    protected function getCronModuleActiveWarning()
    {
        $output = "";
        if (! Module::isInstalled('cronjobs') || ! Module::isEnabled('cronjobs')) {
            $error = "<b>" . $this->l('The module "Cron tasks manager" is not active.') . "</b>";
            $error .= "<br/>";
            $error .= $this->l('This module is required for updating pending transactions, completions, voids and refunds.');
            $error .= "<br/>";
            $output .= $this->displayError($error);
        }
        return $output;
    }

    protected function getConfigurationForms()
    {
        return array(
            $this->getEmailForm(),
            $this->getFeeForm(),
            $this->getDocumentForm(),
            $this->getSpaceViewIdForm(),
            $this->getOrderStatusForm()
        );
    }

    protected function getConfigurationValues()
    {
        return array_merge(
            $this->getApplicationConfigValues(),
            $this->getEmailConfigValues(),
            $this->getFeeItemConfigValues(),
            $this->getDownloadConfigValues(),
            $this->getSpaceViewIdConfigValues(),
            $this->getOrderStatusConfigValues()
        );
    }

    public function hookPaymentOptions($params)
    {
        if (! $this->active) {
            return;
        }
        if (! isset($params['cart']) || ! ($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = PostFinanceCheckout_Service_Transaction::instance()->getPossiblePaymentMethods(
                $cart
            );
        } catch(PostFinanceCheckout_Exception_InvalidTransactionAmount $e){
            PrestaShopLogger::addLog(
                $e->getMessage()." CartId: ".$cart->id,
                2,
                null,
                'PostFinanceCheckout'
                );
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($this->l('There is an issue with your cart, some payment methods are not available.'));
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/amount_error.tpl'
                    )
                );
            $paymentOption->setForm( $this->context->smarty->fetch(
                'module:postfinancecheckout/views/templates/front/hook/amount_error_form.tpl'
                ));
            $paymentOption->setModuleName($this->name."-error");
            return array($paymentOption);
        } catch (Exception $e) {
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = PostFinanceCheckout_Model_MethodConfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (! $methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();
        foreach (PostFinanceCheckout_Helper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = $this->getParametersFromMethodConfiguration(
                $methodConfiguration,
                $cart,
                $shopId,
                $language
            );
            $parameters['priceDisplayTax'] = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $parameters['orderUrl'] = $this->context->link->getModuleLink(
                'postfinancecheckout',
                'order',
                array(),
                true
            );
            $this->context->smarty->assign($parameters);
            
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($parameters['name']);
            $paymentOption->setLogo($parameters['image']);
            $paymentOption->setAction($parameters['link']);
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/payment_additional.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/payment_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name);
            $result[] = $paymentOption;
        }
        return $result;
    }

    public function hookActionFrontControllerSetMedia($arr)
    {
        if ($this->context->controller->php_self == 'order' ||
            $this->context->controller->php_self == 'cart') {
            $uniqueId = $this->context->cookie->pfc_device_id;
            if ($uniqueId == false) {
                $uniqueId = PostFinanceCheckout_Helper::generateUUID();
                $this->context->cookie->pfc_device_id = $uniqueId;
            }
            $scriptUrl = PostFinanceCheckout_Helper::getBaseGatewayUrl() . '/s/' .
                Configuration::get(self::CK_SPACE_ID) . '/payment/device.js?sessionIdentifier=' .
                $uniqueId;
            $this->context->controller->registerJavascript(
                'postfinancecheckout-device-identifier',
                $scriptUrl,
                array(
                    'server' => 'remote',
                    'attributes' => 'async="async"'
                )
            );
        }
        if ($this->context->controller->php_self == 'order') {
            $this->context->controller->registerStylesheet(
                'postfinancecheckout-checkut-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );
            $this->context->controller->registerJavascript(
                'postfinancecheckout-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );
            Media::addJsDef(
                array(
                    'postFinanceCheckoutCheckoutUrl' => $this->context->link->getModuleLink(
                        'postfinancecheckout',
                        'checkout',
                        array(),
                        true
                    ),
                    'postfinancecheckoutMsgJsonError' => $this->l('The server experienced an unexpected error, you may try again or try to use a different payment method.')
                )
            );
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                try {
                    $jsUrl = PostFinanceCheckout_Service_Transaction::instance()->getJavascriptUrl(
                        $this->context->cart
                    );
                    $this->context->controller->registerJavascript(
                        'postfinancecheckout-iframe-handler',
                        $jsUrl,
                        array(
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="postfinancecheckout-iframe-handler"'
                        )
                    );
                } catch (Exception $e) {
                }
            }
        }
        if ($this->context->controller->php_self == 'order-detail') {
            $this->context->controller->registerJavascript(
                'postfinancecheckout-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        parent::hookActionAdminControllerSetMedia($arr);
        $this->context->controller->addCSS(
            __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css'
        );
    }

    protected function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    protected function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    public function hookActionCronJob($param)
    {
        $voidService = PostFinanceCheckout_Service_TransactionVoid::instance();
        if ($voidService->hasPendingVoids()) {
            $voidService->updateVoids();
        }
        $completionService = PostFinanceCheckout_Service_TransactionCompletion::instance();
        if ($completionService->hasPendingCompletions()) {
            $completionService->updateCompletions();
        }
        $refundService = PostFinanceCheckout_Service_Refund::instance();
        if ($refundService->hasPendingRefunds()) {
            $refundService->updateRefunds();
        }
    }

    public function getCronFrequency()
    {
        return array(
            'hour' => - 1,
            'day' => - 1,
            'month' => - 1,
            'day_of_week' => - 1
        );
    }
}
