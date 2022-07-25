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

if (!defined('_PS_VERSION_')) {
    exit();
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'postfinancecheckout_autoloader.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'postfinancecheckout-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');
class PostFinanceCheckout extends PaymentModule
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
        $this->version = '1.2.28';
        $this->displayName = 'PostFinance Checkout';
        $this->description = $this->l('This PrestaShop module enables to process payments with %s.');
        $this->description = sprintf($this->description, 'PostFinance Checkout');
        $this->module_key = '';
        $this->ps_versions_compliancy = array(
            'min' => '1.7',
            'max' => _PS_VERSION_
        );
        parent::__construct();
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'PostFinance Checkout'
        );

        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            PostFinanceCheckoutFeehelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (!empty($this->context->cookie->pfc_error)) {
            $errors = $this->context->cookie->pfc_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            } elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->pfc_error = null;
        }
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function install()
    {
        if (!PostFinanceCheckoutBasemodule::checkRequirements($this)) {
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        return PostFinanceCheckoutBasemodule::install($this);
    }

    public function uninstall()
    {
        return parent::uninstall() && PostFinanceCheckoutBasemodule::uninstall($this);
    }

    public function installHooks()
    {
        return PostFinanceCheckoutBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    public function getBackendControllers()
    {
        return array(
            'AdminPostFinanceCheckoutMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'PostFinance Checkout ' . $this->l('Payment Methods')
            ),
            'AdminPostFinanceCheckoutDocuments' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Documents')
            ),
            'AdminPostFinanceCheckoutOrder' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'PostFinance Checkout ' . $this->l('Order Management')
            ),
            'AdminPostFinanceCheckoutCronJobs' => array(
                'parentId' => Tab::getIdFromClassName('AdminTools'),
                'name' => 'PostFinance Checkout ' . $this->l('CronJobs')
            )
        );
    }

    public function installConfigurationValues()
    {
        return PostFinanceCheckoutBasemodule::installConfigurationValues();
    }

    public function uninstallConfigurationValues()
    {
        return PostFinanceCheckoutBasemodule::uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = PostFinanceCheckoutBasemodule::getMailHookActiveWarning($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveAll($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveApplication($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveEmail($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveCartRecreation($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveFeeItem($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveDownload($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveSpaceViewId($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveOrderStatus($this);
        $output .= PostFinanceCheckoutBasemodule::handleSaveCronSettings($this);
        $output .= PostFinanceCheckoutBasemodule::displayHelpButtons($this);
        return $output . PostFinanceCheckoutBasemodule::displayForm($this);
    }

    public function getConfigurationForms()
    {
        return array(
            PostFinanceCheckoutBasemodule::getEmailForm($this),
            PostFinanceCheckoutBasemodule::getCartRecreationForm($this),
            PostFinanceCheckoutBasemodule::getFeeForm($this),
            PostFinanceCheckoutBasemodule::getDocumentForm($this),
            PostFinanceCheckoutBasemodule::getSpaceViewIdForm($this),
            PostFinanceCheckoutBasemodule::getOrderStatusForm($this),
            PostFinanceCheckoutBasemodule::getCronSettingsForm($this),
        );
    }

    public function getConfigurationValues()
    {
        return array_merge(
            PostFinanceCheckoutBasemodule::getApplicationConfigValues($this),
            PostFinanceCheckoutBasemodule::getEmailConfigValues($this),
            PostFinanceCheckoutBasemodule::getCartRecreationConfigValues($this),
            PostFinanceCheckoutBasemodule::getFeeItemConfigValues($this),
            PostFinanceCheckoutBasemodule::getDownloadConfigValues($this),
            PostFinanceCheckoutBasemodule::getSpaceViewIdConfigValues($this),
            PostFinanceCheckoutBasemodule::getOrderStatusConfigValues($this),
            PostFinanceCheckoutBasemodule::getCronSettingsConfigValues($this)
        );
    }

    public function getConfigurationKeys()
    {
        return PostFinanceCheckoutBasemodule::getConfigurationKeys();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!isset($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = PostFinanceCheckoutServiceTransaction::instance()->getPossiblePaymentMethods(
                $cart
            );
        } catch (PostFinanceCheckoutExceptionInvalidtransactionamount $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 2, null, 'PostFinanceCheckout');
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText(
                $this->l('There is an issue with your cart, some payment methods are not available.')
            );
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/amount_error.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:postfinancecheckout/views/templates/front/hook/amount_error_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name . "-error");
            return array(
                $paymentOption
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 1, null, 'PostFinanceCheckout');
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = PostFinanceCheckoutModelMethodconfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (!$methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();

        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_clean_html',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'cleanHtml'
            )
        );

        foreach (PostFinanceCheckoutHelper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = PostFinanceCheckoutBasemodule::getParametersFromMethodConfiguration($this, $methodConfiguration, $cart, $shopId, $language);
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
        if ($this->context->controller->php_self == 'order' || $this->context->controller->php_self == 'cart') {
            $uniqueId = $this->context->cookie->pfc_device_id;
            if ($uniqueId == false) {
                $uniqueId = PostFinanceCheckoutHelper::generateUUID();
                $this->context->cookie->pfc_device_id = $uniqueId;
            }
            $scriptUrl = PostFinanceCheckoutHelper::getBaseGatewayUrl() . '/s/' . Configuration::get(
                PostFinanceCheckoutBasemodule::CK_SPACE_ID
            ) . '/payment/device.js?sessionIdentifier=' . $uniqueId;
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
                    'postfinancecheckoutMsgJsonError' => $this->l(
                        'The server experienced an unexpected error, you may try again or try to use a different payment method.'
                    )
                )
            );
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                try {
                    $jsUrl = PostFinanceCheckoutServiceTransaction::instance()->getJavascriptUrl($this->context->cart);
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

    public function hookDisplayTop($params)
    {
        return  PostFinanceCheckoutBasemodule::hookDisplayTop($this, $params);
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        PostFinanceCheckoutBasemodule::hookActionAdminControllerSetMedia($this, $arr);
        $this->context->controller->addCSS(__PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css');
    }

    public function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    public function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    public function hookPostFinanceCheckoutCron($params)
    {
        return PostFinanceCheckoutBasemodule::hookPostFinanceCheckoutCron($params);
    }
    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = PostFinanceCheckoutBasemodule::hookDisplayAdminAfterHeader($this);
        $result .= PostFinanceCheckoutBasemodule::getCronJobItem($this);
        return $result;
    }

    public function hookPostFinanceCheckoutSettingsChanged($params)
    {
        return PostFinanceCheckoutBasemodule::hookPostFinanceCheckoutSettingsChanged($this, $params);
    }

    public function hookActionMailSend($data)
    {
        return PostFinanceCheckoutBasemodule::hookActionMailSend($this, $data);
    }

    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null
    ) {
        PostFinanceCheckoutBasemodule::validateOrder($this, $id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop);
    }

    public function validateOrderParent(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null
    ) {
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop);
    }

    public function hookDisplayOrderDetail($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayOrderDetail($this, $params);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        PostFinanceCheckoutBasemodule::hookDisplayBackOfficeHeader($this, $params);
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderLeft($this, $params);
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabOrder($this, $params);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderMain($this, $params);
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabLink($this, $params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderContentOrder($this, $params);
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrderTabContent($this, $params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return PostFinanceCheckoutBasemodule::hookDisplayAdminOrder($this, $params);
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        return PostFinanceCheckoutBasemodule::hookActionAdminOrdersControllerBefore($this, $params);
    }

    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        PostFinanceCheckoutBasemodule::hookActionObjectOrderPaymentAddBefore($this, $params);
    }

    public function hookActionOrderEdited($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderEdited($this, $params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderGridDefinitionModifier($this, $params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        PostFinanceCheckoutBasemodule::hookActionOrderGridQueryBuilderModifier($this, $params);
    }

    public function hookActionProductCancel($params)
    {
        // check version too here to only run on > 1.7.7 for now
        // as there is some overlap in functionality with some previous versions 1.7+
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND && version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }

            $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $refundParameters)) {
                return;
            }

            // need to manually set this here as it's expected downstream
            $refundParameters['partialRefund'] = true;

            $backendController = Context::getContext()->controller;
            $editAccess = 0;

            $access = Profile::getProfileAccess(
                Context::getContext()->employee->id_profile,
                (int) Tab::getIdFromClassName('AdminOrders')
            );
            $editAccess = isset($access['edit']) && $access['edit'] == 1;

            if ($editAccess) {
                try {
                    $parsedData = $strategy->simplifiedRefund($refundParameters);
                    PostFinanceCheckoutServiceRefund::instance()->executeRefund($order, $parsedData);
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }
}
