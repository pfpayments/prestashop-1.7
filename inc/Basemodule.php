<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2021 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

/**
 * Base implementation for common features for PS1.6 and 1.7
 * Because of the PrestaShop Module Validator we can not use inheritance
 */
class PostFinanceCheckoutBasemodule
{
    const CK_BASE_URL = 'PFC_BASE_GATEWAY_URL';

    const CK_USER_ID = 'PFC_USER_ID';

    const CK_APP_KEY = 'PFC_APP_KEY';

    const CK_SPACE_ID = 'PFC_SPACE_ID';

    const CK_SPACE_VIEW_ID = 'PFC_SPACE_VIEW_ID';

    const CK_MAIL = 'PFC_SHOP_EMAIL';

    const CK_INVOICE = 'PFC_INVOICE_DOWNLOAD';

    const CK_PACKING_SLIP = 'PFC_PACKING_SLIP_DOWNLOAD';

    const CK_LINE_ITEM_CONSISTENCY = 'PFC_LINE_ITEM_CONSISTENCY';

    const CK_FEE_ITEM = 'PFC_FEE_ITEM';

    const CK_SURCHARGE_ITEM = 'PFC_SURCHARGE_ITEM';

    const CK_SURCHARGE_TAX = 'PFC_SURCHARGE_TAX';

    const CK_SURCHARGE_AMOUNT = 'PFC_SURCHARGE_AMOUNT';

    const CK_SURCHARGE_TOTAL = 'PFC_SURCHARGE_TOTAL';

    const CK_SURCHARGE_BASE = 'PFC_SURCHARGE_BASE';

    const CK_STATUS_FAILED = 'PFC_STATUS_FAILED';

    const CK_STATUS_AUTHORIZED = 'PFC_STATUS_AUTHORIZED';

    const CK_STATUS_VOIDED = 'PFC_STATUS_VOIDED';

    const CK_STATUS_COMPLETED = 'PFC_STATUS_COMPLETED';

    const CK_STATUS_MANUAL = 'PFC_STATUS_MANUAL';

    const CK_STATUS_DECLINED = 'PFC_STATUS_DECLINED';

    const CK_STATUS_FULFILL = 'PFC_STATUS_FULFILL';

    const MYSQL_DUPLICATE_CONSTRAINT_ERROR_CODE = 1062;

    const TOTAL_MODE_BOTH_INC = 0;

    const TOTAL_MODE_BOTH_EXC = 1;

    const TOTAL_MODE_PRODUCTS_INC = 2;

    const TOTAL_MODE_PRODUCTS_EXC = 3;

    const TOTAL_MODE_WITHOUT_SHIPPING_INC = 4;

    const TOTAL_MODE_WITHOUT_SHIPPING_EXC = 5;
        
    private static $recordMailMessages = false;

    private static $recordedMailMessages = array();

    public static function install(PostFinanceCheckout $module)
    {
        if (! $module->installHooks()) {
            $module->addError(Tools::displayError('Unable to install hooks.'));
            return false;
        }
        if (! self::installControllers($module)) {
            $module->addError(Tools::displayError('Unable to install controllers.'));
            return false;
        }
        if (! PostFinanceCheckoutMigration::installDb()) {
            $module->addError(Tools::displayError('Unable to install database tables.'));
            return false;
        }
        PostFinanceCheckoutOrderstatus::registerOrderStatus();
        if (! $module->installConfigurationValues()) {
            $module->addError(Tools::displayError('Unable to install configuration.'));
        }

        return true;
    }

    public static function uninstall(PostFinanceCheckout $module)
    {
        return self::uninstallControllers($module) && $module->uninstallConfigurationValues();
    }

    public function checkRequirements(PostFinanceCheckout $module)
    {
        try {
            \PostFinanceCheckout\Sdk\Http\HttpClientFactory::getClient();
        } catch (Exception $e) {
            $module->addError(
                Tools::displayError(
                    'Install the PHP cUrl extension or ensure the \'stream_socket_client\' function is available.'
                )
            );
            return false;
        }
        return true;
    }

    public static function installHooks(PostFinanceCheckout $module)
    {
        return $module->registerHook('actionAdminControllerSetMedia') &&
            $module->registerHook('actionAdminOrdersControllerBefore') && $module->registerHook('actionMailSend') &&
            $module->registerHook('actionOrderEdited') && $module->registerHook('displayAdminAfterHeader') &&
            $module->registerHook('displayAdminOrder') && $module->registerHook('displayAdminOrderContentOrder') &&
            $module->registerHook('displayAdminOrderLeft') && $module->registerHook('displayAdminOrderTabOrder') &&
            $module->registerHook('displayAdminOrderTabContent') &&
            $module->registerHook('displayAdminOrderMain') && $module->registerHook('displayAdminOrderTabLink') &&
            $module->registerHook('displayBackOfficeHeader') && $module->registerHook('displayOrderDetail') &&
            $module->registerHook('actionProductCancel') && $module->registerHook('postFinanceCheckoutSettingsChanged');
    }

    public static function installConfigurationValues()
    {
        return Configuration::updateGlobalValue(self::CK_MAIL, true) &&
            Configuration::updateGlobalValue(self::CK_INVOICE, true) &&
			Configuration::updateGlobalValue(self::CK_PACKING_SLIP, true) &&
			Configuration::updateGlobalValue(self::CK_LINE_ITEM_CONSISTENCY, true);
    }

    public static function uninstallConfigurationValues()
    {
        return
			Configuration::deleteByName(self::CK_USER_ID) &&
			Configuration::deleteByName(self::CK_APP_KEY) &&
            Configuration::deleteByName(self::CK_SPACE_ID) &&
			Configuration::deleteByName(self::CK_SPACE_VIEW_ID) &&
            Configuration::deleteByName(self::CK_MAIL) &&
			Configuration::deleteByName(self::CK_INVOICE) &&
            Configuration::deleteByName(self::CK_PACKING_SLIP) &&
			Configuration::deleteByName(self::CK_LINE_ITEM_CONSISTENCY) &&
			Configuration::deleteByName(self::CK_FEE_ITEM) &&
            Configuration::deleteByName(self::CK_SURCHARGE_ITEM) &&
			Configuration::deleteByName(self::CK_SURCHARGE_TAX) &&
            Configuration::deleteByName(self::CK_SURCHARGE_AMOUNT) &&
            Configuration::deleteByName(self::CK_SURCHARGE_TOTAL) &&
			Configuration::deleteByName(self::CK_SURCHARGE_BASE) &&
            Configuration::deleteByName(PostFinanceCheckoutServiceManualtask::CONFIG_KEY) &&
            Configuration::deleteByName(self::CK_STATUS_FAILED) &&
            Configuration::deleteByName(self::CK_STATUS_AUTHORIZED) &&
            Configuration::deleteByName(self::CK_STATUS_VOIDED) &&
			Configuration::deleteByName(self::CK_STATUS_COMPLETED) &&
			Configuration::deleteByName(self::CK_STATUS_MANUAL) &&
            Configuration::deleteByName(self::CK_STATUS_DECLINED) &&
			Configuration::deleteByName(self::CK_STATUS_FULFILL);
    }


    private function installControllers(PostFinanceCheckout $module)
    {
        foreach ($module->getBackendControllers() as $className => $data) {
            if (Tab::getIdFromClassName($className)) {
                continue;
            }
            if (! self::addTab($module, $className, $data['name'], $data['parentId'])) {
                return false;
            }
        }
        return true;
    }

    public static function addTab(PostFinanceCheckout $module, $className, $name, $parentId)
    {
        $tab = new Tab();
        $tab->id_parent = $parentId;
        $tab->module = $module->name;
        $tab->class_name = $className;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $module->l($name, 'basemodule');
        }
        return $tab->save();
    }

    private static function uninstallControllers(PostFinanceCheckout $module)
    {
        $result = true;
        foreach (array_keys($module->getBackendControllers()) as $className) {
            $id = Tab::getIdFromClassName($className);
            if (! $id) {
                continue;
            }
            $tab = new Tab($id);
            if (! Validate::isLoadedObject($tab) || ! $tab->delete()) {
                $result = false;
            }
        }
        return $result;
    }

    public static function displayHelpButtons(PostFinanceCheckout $module)
    {
        return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/admin_help_buttons.tpl');
    }

    public static function getMailHookActiveWarning(PostFinanceCheckout $module)
    {
        $output = "";
        if (! Module::isInstalled('mailhook') || ! Module::isEnabled('mailhook')) {
            $error = "<b>" . $module->l('The module "Mail Hook" is not active.', 'basemodule') . "</b>";
            $error .= "<br/>";
            $error .= $module->l(
                'This module is recommend for handling the shop emails. Otherwise the mail sending behavior may be inappropriate.',
                'basemodule'
            );
            $error .= "<br/>";
            $error .= sprintf(
                $module->l('You can download the module %shere%s.', 'basemodule'),
                '<a href="https://github.com/wallee-payment/prestashop-mailhook/releases" target="_blank">',
                '</a>'
            );
            $output .= $module->displayError($error);
        }
        return $output;
    }

    public static function handleSaveAll(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_all')) {
            $refresh = true;
            if ($module->getContext()->shop->isFeatureActive()) {
                if ($module->getContext()->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(self::CK_USER_ID, Tools::getValue(self::CK_USER_ID));
                    Configuration::updateGlobalValue(self::CK_APP_KEY, Tools::getValue(self::CK_APP_KEY));
                    $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
                } elseif ($module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                    foreach ($module->getConfigurationKeys() as $key) {
                        Configuration::updateValue($key, Tools::getValue($key));
                    }
                    $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
                } else {
                    $refresh = false;
                    $output .= $module->displayError(
                        $module->l('You can not store the configuration for Shop Group.', 'basemodule')
                    );
                }
            } else {
                Configuration::updateGlobalValue(self::CK_USER_ID, Tools::getValue(self::CK_USER_ID));
                Configuration::updateGlobalValue(self::CK_APP_KEY, Tools::getValue(self::CK_APP_KEY));
                foreach ($module->getConfigurationKeys() as $key) {
                    Configuration::updateValue($key, Tools::getValue($key));
                }
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            }
            if ($refresh) {
                $error = Hook::exec('postFinanceCheckoutSettingsChanged');
                if (! empty($error)) {
                    $output .= $module->displayError($error);
                }
            }
        }
        return $output;
    }

    public static function getConfigurationKeys()
    {
        return array(
            self::CK_SPACE_ID,
            self::CK_SPACE_VIEW_ID,
            self::CK_MAIL,
            self::CK_INVOICE,
            self::CK_PACKING_SLIP,
            self::CK_LINE_ITEM_CONSISTENCY,
            self::CK_FEE_ITEM,
            self::CK_SURCHARGE_ITEM,
            self::CK_SURCHARGE_TAX,
            self::CK_SURCHARGE_AMOUNT,
            self::CK_SURCHARGE_TOTAL,
            self::CK_SURCHARGE_BASE,
            self::CK_STATUS_FAILED,
            self::CK_STATUS_AUTHORIZED,
            self::CK_STATUS_VOIDED,
            self::CK_STATUS_COMPLETED,
            self::CK_STATUS_MANUAL,
            self::CK_STATUS_DECLINED,
            self::CK_STATUS_FULFILL
        );
    }

    public static function handleSaveApplication(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_application')) {
            $refresh = true;
            if ($module->getContext()->shop->isFeatureActive()) {
                if ($module->getContext()->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(self::CK_USER_ID, Tools::getValue(self::CK_USER_ID));
                    Configuration::updateGlobalValue(self::CK_APP_KEY, Tools::getValue(self::CK_APP_KEY));
                    $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
                } elseif ($module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_SPACE_ID, Tools::getValue(self::CK_SPACE_ID));
                    Configuration::updateValue(self::CK_SPACE_VIEW_ID, Tools::getValue(self::CK_SPACE_VIEW_ID));
                    $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
                } else {
                    $refresh = false;
                    $output .= $module->displayError(
                        $module->l('You can not store the configuration for Shop Group.', 'basemodule')
                    );
                }
            } else {
                Configuration::updateGlobalValue(self::CK_USER_ID, Tools::getValue(self::CK_USER_ID));
                Configuration::updateGlobalValue(self::CK_APP_KEY, Tools::getValue(self::CK_APP_KEY));
                Configuration::updateValue(self::CK_SPACE_ID, Tools::getValue(self::CK_SPACE_ID));
                Configuration::updateValue(self::CK_SPACE_VIEW_ID, Tools::getValue(self::CK_SPACE_VIEW_ID));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            }
            if ($refresh) {
                $error = Hook::exec('postFinanceCheckoutSettingsChanged');
                if (! empty($error)) {
                    $output .= $module->displayError($error);
                }
            }
        }
        return $output;
    }

    public static function handleSaveEmail(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_email')) {
            if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_MAIL, Tools::getValue(self::CK_MAIL));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            } else {
                $output .= $module->displayError(
                    $module->l('You can not store the configuration for all Shops or a Shop Group.', 'basemodule')
                );
            }
        }
        return $output;
    }

    public static function handleSaveFeeItem(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_fee_item')) {
            if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_LINE_ITEM_CONSISTENCY, Tools::getValue(self::CK_LINE_ITEM_CONSISTENCY));
				Configuration::updateValue(self::CK_FEE_ITEM, Tools::getValue(self::CK_FEE_ITEM));
                Configuration::updateValue(self::CK_SURCHARGE_ITEM, Tools::getValue(self::CK_SURCHARGE_ITEM));
                Configuration::updateValue(self::CK_SURCHARGE_TAX, Tools::getValue(self::CK_SURCHARGE_TAX));
                Configuration::updateValue(self::CK_SURCHARGE_AMOUNT, Tools::getValue(self::CK_SURCHARGE_AMOUNT));
                Configuration::updateValue(self::CK_SURCHARGE_TOTAL, Tools::getValue(self::CK_SURCHARGE_TOTAL));
                Configuration::updateValue(self::CK_SURCHARGE_BASE, Tools::getValue(self::CK_SURCHARGE_BASE));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            } else {
                $output .= $module->displayError(
                    $module->l('You can not store the configuration for all Shops or a Shop Group.', 'basemodule')
                );
            }
        }
        return $output;
    }

    public static function handleSaveDownload(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_download')) {
            if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_INVOICE, Tools::getValue(self::CK_INVOICE));
                Configuration::updateValue(self::CK_PACKING_SLIP, Tools::getValue(self::CK_PACKING_SLIP));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            } else {
                $output .= $module->displayError(
                    $module->l('You can not store the configuration for all Shops or a Shop Group.', 'basemodule')
                );
            }
        }
        return $output;
    }

    public static function handleSaveSpaceViewId(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_space_view_id')) {
            if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_SPACE_VIEW_ID, Tools::getValue(self::CK_SPACE_VIEW_ID));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            } else {
                $output .= $module->displayError(
                    $module->l('You can not store the configuration for all Shops or a Shop Group.', 'basemodule')
                );
            }
        }
        return $output;
    }

    public static function handleSaveOrderStatus(PostFinanceCheckout $module)
    {
        $output = "";
        if (Tools::isSubmit('submit' . $module->name . '_order_status')) {
            if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_STATUS_FAILED, Tools::getValue(self::CK_STATUS_FAILED));
                Configuration::updateValue(self::CK_STATUS_AUTHORIZED, Tools::getValue(self::CK_STATUS_AUTHORIZED));
                Configuration::updateValue(self::CK_STATUS_VOIDED, Tools::getValue(self::CK_STATUS_VOIDED));
                Configuration::updateValue(self::CK_STATUS_COMPLETED, Tools::getValue(self::CK_STATUS_COMPLETED));
                Configuration::updateValue(self::CK_STATUS_MANUAL, Tools::getValue(self::CK_STATUS_MANUAL));
                Configuration::updateValue(self::CK_STATUS_DECLINED, Tools::getValue(self::CK_STATUS_DECLINED));
                Configuration::updateValue(self::CK_STATUS_FULFILL, Tools::getValue(self::CK_STATUS_FULFILL));
                $output .= $module->displayConfirmation($module->l('Settings updated', 'basemodule'));
            } else {
                $output .= $module->displayError(
                    $module->l('You can not store the configuration for all Shops or a Shop Group.', 'basemodule')
                );
            }
        }
        return $output;
    }

    private static function getFormHelper(PostFinanceCheckout $module)
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $module->getTable();
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_FORM_LANG'
        ) : 0;

        $helper->identifier = $module->getIdentifier();

        $helper->title = $module->displayName;

        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $module->name . '&tab_module=' .
            $module->tab . '&module_name=' . $module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'languages' => $module->getContext()->controller->getLanguages(),
            'id_language' => $module->getContext()->language->id
        );
        return $helper;
    }

    public static function displayForm(PostFinanceCheckout $module)
    {
        $userIdConfig = array(
            'type' => 'text',
            'label' => $module->l('User Id', 'basemodule'),
            'name' => self::CK_USER_ID,
            'required' => true,
            'col' => 3,
            'lang' => false
        );
        $userPwConfig = array(
            'type' => 'postfinancecheckout_password',
            'label' => $module->l('Authentication Key', 'basemodule'),
            'name' => self::CK_APP_KEY,
            'required' => true,
            'col' => 3,
            'lang' => false
        );

        $userIdInfo = array(
            'type' => 'html',
            'name' => 'IGNORE',
            'col' => 3,
            'html_content' => '<b>' . $module->l('The User Id needs to be configured globally.', 'basemodule') . '</b>'
        );

        $userPwInfo = array(
            'type' => 'html',
            'name' => 'IGNORE',
            'col' => 3,
            'html_content' => '<b>' .
            $module->l('The Authentication Key needs to be configured globally.', 'basemodule') . '</b>'
        );

        $spaceIdConfig = array(
            'type' => 'text',
            'label' => $module->l('Space Id', 'basemodule'),
            'name' => self::CK_SPACE_ID,
            'required' => true,
            'col' => 3,
            'lang' => false
        );

        $spaceIdInfo = array(
            'type' => 'html',
            'name' => 'IGNORE',
            'col' => 3,
            'html_content' => '<b>' . $module->l('The Space Id needs to be configured per shop.', 'basemodule') .
            '</b>'
        );

        $generalInputs = array(
            $spaceIdConfig,
            $userIdConfig,
            $userPwConfig
        );
        $buttons = array(
            array(
                'title' => $module->l('Save', 'basemodule'),
                'class' => 'pull-right',
                'type' => 'input',
                'icon' => 'process-icon-save',
                'name' => 'submit' . $module->name . '_application'
            )
        );

        if ($module->getContext()->shop->isFeatureActive()) {
            if ($module->getContext()->shop->getContext() == Shop::CONTEXT_ALL) {
                $generalInputs = array(
                    $spaceIdInfo,
                    $userIdConfig,
                    $userPwConfig
                );
            } elseif ($module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                $generalInputs = array(
                    $spaceIdConfig,
                    $userIdInfo,
                    $userPwInfo
                );
                array_unshift(
                    $buttons,
                    array(
                        'title' => $module->l('Save All', 'basemodule'),
                        'class' => 'pull-right',
                        'type' => 'input',
                        'icon' => 'process-icon-save',
                        'name' => 'submit' . $module->name . '_all'
                    )
                );
            } else {
                $generalInputs = array_merge($spaceIdInfo, $userIdInfo, $userPwInfo);
                $buttons = array();
            }
        } else {
            array_unshift(
                $buttons,
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                )
            );
        }
        $fieldsForm = array();
        // General Settings
        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => 'PostFinance Checkout ' . $module->l('General Settings', 'basemodule')
            ),
            'input' => $generalInputs,
            'buttons' => $buttons
        );

        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
            $forms = $module->getConfigurationForms();
            foreach ($forms as $form) {
                $fieldsForm[]['form'] = $form;
            }
        }

        $helper = self::getFormHelper($module);
        $helper->tpl_vars['fields_value'] = $module->getConfigurationValues();

        return $helper->generateForm($fieldsForm);
    }


    public static function getApplicationConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if ($module->getContext()->shop->isFeatureActive()) {
            if ($module->getContext()->shop->getContext() == Shop::CONTEXT_ALL) {
                $values[self::CK_USER_ID] = Configuration::getGlobalValue(self::CK_USER_ID);
                $values[self::CK_APP_KEY] = Configuration::getGlobalValue(self::CK_APP_KEY);
            } elseif ($module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_SPACE_ID] = Configuration::get(self::CK_SPACE_ID);
                $values[self::CK_SPACE_VIEW_ID] = Configuration::get(self::CK_SPACE_VIEW_ID);
            }
        } else {
            $values[self::CK_USER_ID] = Configuration::getGlobalValue(self::CK_USER_ID);
            $values[self::CK_APP_KEY] = Configuration::getGlobalValue(self::CK_APP_KEY);
            $values[self::CK_SPACE_ID] = Configuration::get(self::CK_SPACE_ID);
            $values[self::CK_SPACE_VIEW_ID] = Configuration::get(self::CK_SPACE_VIEW_ID);
        }
        return $values;
    }

    public static function getEmailForm(PostFinanceCheckout $module)
    {
        $emailConfig = array(
            array(
                'type' => 'switch',
                'label' => $module->l('Send Order Emails', 'basemodule'),
                'name' => self::CK_MAIL,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $module->l('Send', 'basemodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $module->l('Disabled', 'basemodule')
                    )
                ),
                'desc' => $module->l('Send the prestashop order emails.', 'basemodule'),
                'lang' => false
            )
        );

        return array(
            'legend' => array(
                'title' => $module->l('Order Email Settings', 'basemodule')
            ),
            'input' => $emailConfig,
            'buttons' => array(
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                ),
                array(
                    'title' => $module->l('Save', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_email'
                )
            )
        );
    }

    public static function getEmailConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
            $values[self::CK_MAIL] = (bool) Configuration::get(self::CK_MAIL);
        }
        return $values;
    }

    public static function getFeeForm(PostFinanceCheckout $module)
    {
        $feeProducts = Product::getSimpleProducts($module->getContext()->language->id);
        array_unshift(
            $feeProducts,
            array(
                'id_product' => '-1',
                'name' => $module->l('None (disables payment fees)', 'basemodule')
            )
        );

        $surchargeProducts = Product::getSimpleProducts($module->getContext()->language->id);
        array_unshift(
            $surchargeProducts,
            array(
                'id_product' => '-1',
                'name' => $module->l('None (disables surcharges)', 'basemodule')
            )
        );

        $defaultCurrency = Currency::getCurrency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $feeItemConfig = array(
            array(
                'type' => 'select',
                'label' => $module->l('Payment Fee Product', 'basemodule'),
                'desc' => $module->l(
                    'Select the product that should be inserted into the cart as a payment fee.',
                    'basemodule'
                ),
                'name' => self::CK_FEE_ITEM,
                'options' => array(
                    'query' => $feeProducts,
                    'id' => 'id_product',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Minimum Sales Surcharge Product', 'basemodule'),
                'desc' => $module->l(
                    'Select the product that should be inserted into the cart as a minimal sales surcharge.',
                    'basemodule'
                ),
                'name' => self::CK_SURCHARGE_ITEM,
                'options' => array(
                    'query' => $surchargeProducts,
                    'id' => 'id_product',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Add tax', 'basemodule'),
                'name' => self::CK_SURCHARGE_TAX,
                'desc' => $module->l(
                    'Should the tax amount be added after the computation or should the tax be included in the computed surcharge.',
                    'basemodule'
                ),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $module->l('Add', 'basemodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $module->l('Inlcuded', 'basemodule')
                    )
                ),
                'lang' => false
            ),
			array(
				'type' => 'switch',
				'label' => $module->l('Line item consistency', 'basemodule'),
				'name' => self::CK_LINE_ITEM_CONSISTENCY,
				'desc' => $module->l(
					'If this option is enabled line item totals will always match the order total.',
					'basemodule'
				),
				'is_bool' => true,
				'values' => array(
					array(
						'id' => 'active_on',
						'value' => 1,
						'label' => $module->l('Allow', 'basemodule')
					),
					array(
						'id' => 'active_off',
						'value' => 0,
						'label' => $module->l('Disallow', 'basemodule')
					)
				),
				'lang' => false
			),
            array(
                'type' => 'text',
                'label' => $module->l('Surcharge Amount', 'basemodule'),
                'desc' => sprintf(
                    $module->l(
                        'The amount has to be entered in the shops default currency. Current default currency: %s',
                        'basemodule'
                    ),
                    $defaultCurrency['iso_code']
                ),
                'name' => self::CK_SURCHARGE_AMOUNT,
                'col' => 3
            ),
            array(
                'type' => 'text',
                'label' => $module->l('Minimum Sales Order Total', 'basemodule'),
                'desc' => sprintf(
                    $module->l(
                        'The surcharge is added, if the order total is below this amount. The total has to be entered in the shops default currency. Current default currency: %s',
                        'basemodule'
                    ),
                    $defaultCurrency['iso_code']
                ),
                'name' => self::CK_SURCHARGE_TOTAL,
                'col' => 3
            ),
            array(
                'type' => 'select',
                'label' => $module->l('The order total is the following:', 'basemodule'),
                'name' => self::CK_SURCHARGE_BASE,
                'options' => array(

                    'query' => array(
                        array(
                            'name' => $module->l('Total (inc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_BOTH_INC
                        ),
                        array(
                            'name' => $module->l('Total (exc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_BOTH_EXC
                        ),
                        array(
                            'name' => $module->l('Total without shipping (inc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_INC
                        ),
                        array(
                            'name' => $module->l('Total without shipping (exc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_EXC
                        ),
                        array(
                            'name' => $module->l('Products only (inc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_PRODUCTS_INC
                        ),
                        array(
                            'name' => $module->l('Products only (exc Tax)', 'basemodule'),
                            'type' => PostFinanceCheckoutBasemodule::TOTAL_MODE_PRODUCTS_EXC
                        )
                    ),
                    'id' => 'type',
                    'name' => 'name'
                )
            )
        );

        return array(
            'legend' => array(
                'title' => $module->l('Fee Item Settings', 'basemodule')
            ),
            'input' => $feeItemConfig,
            'buttons' => array(
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                ),
                array(
                    'title' => $module->l('Save', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_fee_item'
                )
            )
        );
    }

    public static function getFeeItemConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
			$values[self::CK_FEE_ITEM] = (int) Configuration::get(self::CK_FEE_ITEM);
            $values[self::CK_LINE_ITEM_CONSISTENCY] = (int) Configuration::get(self::CK_LINE_ITEM_CONSISTENCY);
            $values[self::CK_SURCHARGE_ITEM] = (int) Configuration::get(self::CK_SURCHARGE_ITEM);
            $values[self::CK_SURCHARGE_TAX] = (int) Configuration::get(self::CK_SURCHARGE_TAX);
            $values[self::CK_SURCHARGE_AMOUNT] = (float) Configuration::get(self::CK_SURCHARGE_AMOUNT);
            $values[self::CK_SURCHARGE_TOTAL] = (float) Configuration::get(self::CK_SURCHARGE_TOTAL);
            $values[self::CK_SURCHARGE_BASE] = (int) Configuration::get(self::CK_SURCHARGE_BASE);
        }
        return $values;
    }

    public static function getDocumentForm(PostFinanceCheckout $module)
    {
        $documentConfig = array(
            array(
                'type' => 'switch',
                'label' => $module->l('Invoice Download', 'basemodule'),
                'name' => self::CK_INVOICE,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $module->l('Allow', 'basemodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $module->l('Disallow', 'basemodule')
                    )
                ),
                'desc' => sprintf(
                    $module->l('Allow the customers to download the %s invoice.', 'basemodule'),
                    'PostFinance Checkout'
                ),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Packing Slip Download', 'basemodule'),
                'name' => self::CK_PACKING_SLIP,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $module->l('Allow', 'basemodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $module->l('Disallow', 'basemodule')
                    )
                ),
                'desc' => sprintf(
                    $module->l('Allow the customers to download the %s packing slip.', 'basemodule'),
                    'PostFinance Checkout'
                ),
                'lang' => false
            )
        );

        return array(
            'legend' => array(
                'title' => $module->l('Document Settings', 'basemodule')
            ),
            'input' => $documentConfig,
            'buttons' => array(
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                ),
                array(
                    'title' => $module->l('Save', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_download'
                )
            )
        );
    }

    public static function getDownloadConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
            $values[self::CK_INVOICE] = (bool) Configuration::get(self::CK_INVOICE);
            $values[self::CK_PACKING_SLIP] = (bool) Configuration::get(self::CK_PACKING_SLIP);
        }

        return $values;
    }

    public static function getSpaceViewIdForm(PostFinanceCheckout $module)
    {
        $spaceViewIdConfig = array(
            array(
                'type' => 'text',
                'label' => $module->l('Space View Id', 'basemodule'),
                'name' => self::CK_SPACE_VIEW_ID,
                'col' => 3,
                'lang' => false
            )
        );

        return array(
            'legend' => array(
                'title' => $module->l('Space View Id Settings', 'basemodule')
            ),
            'input' => $spaceViewIdConfig,
            'buttons' => array(
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                ),
                array(
                    'title' => $module->l('Save', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_space_view_id'
                )
            )
        );
    }

    public static function getSpaceViewIdConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
            $values[self::CK_SPACE_VIEW_ID] = Configuration::get(self::CK_SPACE_VIEW_ID);
        }

        return $values;
    }

    public static function getOrderStatusForm(PostFinanceCheckout $module)
    {
        $orderStates = OrderState::getOrderStates($module->getContext()->language->id);

        $orderStatusConfig = array(
            array(
                'type' => 'select',
                'label' => $module->l('Failed Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the failed status.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_FAILED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Authorized Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the authorized status.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_AUTHORIZED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Voided Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the voided status.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_VOIDED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Waiting Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the completed status and the delivery indication is in a pending state.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_COMPLETED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Manual  Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the completed status and the delivery indication requires a manual decision.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_MANUAL,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Decline  Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the declined status.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_DECLINED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $module->l('Fulfill  Status', 'basemodule'),
                'desc' => $module->l(
                    'Status the order enters when the transaction is in the fulfill status.',
                    'basemodule'
                ),
                'name' => self::CK_STATUS_FULFILL,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            )
        );

        return array(
            'legend' => array(
                'title' => $module->l('Order Status Settings', 'basemodule')
            ),
            'input' => $orderStatusConfig,
            'buttons' => array(
                array(
                    'title' => $module->l('Save All', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_all'
                ),
                array(
                    'title' => $module->l('Save', 'basemodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $module->name . '_order_status'
                )
            )
        );
    }

    public static function getOrderStatusConfigValues(PostFinanceCheckout $module)
    {
        $values = array();
        if (! $module->getContext()->shop->isFeatureActive() || $module->getContext()->shop->getContext() == Shop::CONTEXT_SHOP) {
            $values[self::CK_STATUS_FAILED] = (int) Configuration::get(self::CK_STATUS_FAILED);
            $values[self::CK_STATUS_AUTHORIZED] = (int) Configuration::get(self::CK_STATUS_AUTHORIZED);
            $values[self::CK_STATUS_VOIDED] = (int) Configuration::get(self::CK_STATUS_VOIDED);
            $values[self::CK_STATUS_COMPLETED] = (int) Configuration::get(self::CK_STATUS_COMPLETED);
            $values[self::CK_STATUS_MANUAL] = (int) Configuration::get(self::CK_STATUS_MANUAL);
            $values[self::CK_STATUS_DECLINED] = (int) Configuration::get(self::CK_STATUS_DECLINED);
            $values[self::CK_STATUS_FULFILL] = (int) Configuration::get(self::CK_STATUS_FULFILL);
        }
        return $values;
    }

    public static function hookPostFinanceCheckoutSettingsChanged(PostFinanceCheckout $module, $params)
    {
        try {
            PostFinanceCheckoutHelper::resetApiClient();
            PostFinanceCheckoutHelper::getApiClient();
        } catch (PostFinanceCheckoutExceptionIncompleteconfig $e) {
            // We stop here as the configuration is not complete
            return "";
        }
        $errors = array();
        try {
            PostFinanceCheckoutServiceMethodconfiguration::instance()->synchronize();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $module->l('Synchronization of the payment method configurations failed.', 'basemodule');
        }
        try {
            PostFinanceCheckoutServiceWebhook::instance()->install();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $module->l(
                'Installation of the webhooks failed, please check if the feature is active in your space.',
                'basemodule'
            );
        }
        try {
            PostFinanceCheckoutServiceManualtask::instance()->update();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $module->l('Update of Manual Tasks failed.', 'basemodule');
        }
        self::deleteCachedEntries();
        if (! empty($errors)) {
            return $module->l(
                'Please check your credentials and grant the application user the necessary rights (Account Admin) for your space.',
                'basemodule'
            ) . ' ' . implode(" ", $errors);
        }
        return "";
    }

    private static function deleteCachedEntries()
    {
        $toDelete = array(
            'postfinancecheckout_currencies',
            'postfinancecheckout_label_description',
            'postfinancecheckout_label_description_group',
            'postfinancecheckout_languages',
            'postfinancecheckout_connectors',
            'postfinancecheckout_methods'
        );
        foreach ($toDelete as $delete) {
            Cache::clean($delete);
        }
    }

    public static function getParametersFromMethodConfiguration(
        PostFinanceCheckout $module,
        PostFinanceCheckoutModelMethodconfiguration $methodConfiguration,
        Cart $cart,
        $shopId,
        $language
    ) {
        $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, $shopId);
        $spaceViewId = Configuration::get(self::CK_SPACE_VIEW_ID, null, null, $shopId);
        $parameters = array();
        $parameters['methodId'] = $methodConfiguration->getId();
        $parameters['configurationId'] = $methodConfiguration->getConfigurationId();
        $parameters['link'] = $module->getContext()->link->getModuleLink(
            'postfinancecheckout',
            'payment',
            array(
                'methodId' => $methodConfiguration->getId()
            ),
            true
        );
        $name = $methodConfiguration->getConfigurationName();
        $translatedName = PostFinanceCheckoutHelper::translate($methodConfiguration->getTitle(), $language);
        if (! empty($translatedName)) {
            $name = $translatedName;
        }
        $parameters['name'] = $name;
        $parameters['image'] = '';
        $img = $methodConfiguration->getImage();
        if (! empty($img) && $methodConfiguration->isShowImage()) {
            $parameters['image'] = PostFinanceCheckoutHelper::getResourceUrl(
                $methodConfiguration->getImageBase(),
                $methodConfiguration->getImage(),
                PostFinanceCheckoutHelper::convertLanguageIdToIETF($cart->id_lang),
                $spaceId,
                $spaceViewId
            );
        }
        $parameters['description'] = '';
        $description = PostFinanceCheckoutHelper::translate($methodConfiguration->getDescription(), $language);
        if (! empty($description) && $methodConfiguration->isShowDescription()) {
            $description = preg_replace('/((<a (?!.*target="_blank").*?)>)/', '$2 target="_blank">', $description);
            $parameters['description'] = $description;
        }
        $surchargeValues = PostFinanceCheckoutFeehelper::getSurchargeValues($cart);
        if ($surchargeValues['surcharge_total'] > 0) {
            $parameters['surchargeValues'] = $surchargeValues;
        } else {
            $parameters['surchargeValues'] = array();
        }
        $feeValues = PostFinanceCheckoutFeehelper::getFeeValues($cart, $methodConfiguration);
        if ($feeValues['fee_total'] > 0) {
            $parameters['feeValues'] = $feeValues;
        } else {
            $parameters['feeValues'] = array();
        }
        return $parameters;
    }

    public static function hookActionMailSend($module, $data)
    {
        if (! isset($data['event'])) {
            throw new Exception("No item 'event' provided in the mail action function.");
        }
        $event = $data['event'];
        if (! ($event instanceof MailMessageEvent)) {
            throw new Exception("Invalid type provided by the mail send action.");
        }

        if (self::isRecordingMailMessages()) {
            foreach ($event->getMessages() as $message) {
                self::$recordedMailMessages[] = $message;
            }
            $event->setMessages(array());
        }
    }

    public static function isRecordingMailMessages()
    {
        return self::$recordMailMessages;
    }

    public static function startRecordingMailMessages()
    {
        self::$recordMailMessages = true;
        self::$recordedMailMessages = array();
    }

    /**
     *
     * @return MailMessage[]
     */
    public static function stopRecordingMailMessages()
    {
        self::$recordMailMessages = false;
        return self::$recordedMailMessages;
    }

    public static function validateOrder(
        PostFinanceCheckout $module,
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
        if ($module->active) {
            PostFinanceCheckoutHelper::startDBTransaction();
            $methodConfiguration = null;
            try {
                $originalCart = new Cart($id_cart);

                // If transaction is no longer pending we stop here and the customer has to go through the checkout
                // again
                PostFinanceCheckoutServiceTransaction::instance()->checkTransactionPending($originalCart);
                $rs = $originalCart->duplicate();
                if (! isset($rs['success']) || ! isset($rs['cart'])) {
                    $error = 'The cart duplication failed. May be some module prevents it.';
                    PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $module->id);
                    throw new Exception("There was a technical issue, please try again.");
                }
                $cart = $rs['cart'];
                if (! ($cart instanceof Cart)) {
                    $error = 'The duplicated cart is not of type "Cart".';
                    PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $module->id);
                    throw new Exception("There was a technical issue, please try again.");
                }
                foreach ($originalCart->getCartRules() as $rule) {
                    $ruleObject = $rule['obj'];
                    // Because free gift cart rules adds a product to the order, the product is already in the
                    // duplicated order,
                    // before we can add the cart rule to the new cart we have to remove the existing gift.
                    if ((int) $ruleObject->gift_product) { // We use the same check as the shop, to get the gift product
                        $cart->updateQty(
                            1,
                            $ruleObject->gift_product,
                            $ruleObject->gift_product_attribute,
                            false,
                            'down',
                            0,
                            null,
                            false
                        );
                    }
                    $cart->addCartRule($ruleObject->id);
                }
                // Update customizations
                $customizationCollection = new PrestaShopCollection('Customization');
                $customizationCollection->where('id_cart', '=', (int) $cart->id);
                foreach ($customizationCollection->getResults() as $customization) {
                    $customization->id_address_delivery = $cart->id_address_delivery;
                    $customization->save();
                }

                // Updated all specific Prices to the duplicated cart
                $specificPriceCollection = new PrestaShopCollection('SpecificPrice');
                $specificPriceCollection->where('id_cart', '=', (int) $id_cart);
                foreach ($specificPriceCollection->getResults() as $specificPrice) {
                    $specificPrice->id_cart = $cart->id;
                    $specificPrice->save();
                }

                // Copy messages to new cart
                $messageCollection = new PrestaShopCollection('Message');
                $messageCollection->where('id_cart', '=', (int) $id_cart);
                foreach ($messageCollection->getResults() as $orderMessage) {
                    $duplicateMessage = $orderMessage->duplicateObject();
                    $duplicateMessage->id_cart = $cart->id;
                    $duplicateMessage->save();
                }

                
                if (strpos($payment_method, "postfinancecheckout_") === 0) {
                    $id = Tools::substr($payment_method, strpos($payment_method, "_") + 1);
                    $methodConfiguration = new PostFinanceCheckoutModelMethodconfiguration($id);
                }

                if ($methodConfiguration == null || $methodConfiguration->getId() == null ||
                    $methodConfiguration->getState() != PostFinanceCheckoutModelMethodconfiguration::STATE_ACTIVE || $methodConfiguration->getSpaceId() !=
                    Configuration::get(self::CK_SPACE_ID, null, null, $cart->id_shop)) {
                    $error = 'PostFinanceCheckout method configuration called with wrong payment method configuration. Method: ' .
                        $payment_method;
                    PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $module->id);
                    throw new Exception("There was a technical issue, please try again.");
                }

                $title = $methodConfiguration->getConfigurationName();
                $translatedTitel = PostFinanceCheckoutHelper::translate(
                    $methodConfiguration->getTitle(),
                    $cart->id_lang
                );
                if ($translatedTitel !== null) {
                    $title = $translatedTitel;
                }

                PostFinanceCheckoutBasemodule::startRecordingMailMessages();
                $module->validateOrderParent(
                    (int) $cart->id,
                    $id_order_state,
                    (float) $amount_paid,
                    $title,
                    $message,
                    $extra_vars,
                    $currency_special,
                    $dont_touch_amount,
                    $secure_key,
                    $shop
                );

                $lastOrderId = $module->currentOrder;
                $dataOrder = new Order($lastOrderId);
                $orders = $dataOrder->getBrother()->getResults();
                $orders[] = $dataOrder;
                foreach ($orders as $order) {
                    PostFinanceCheckoutHelper::updateOrderMeta(
                        $order,
                        'postFinanceCheckoutMethodId',
                        $methodConfiguration->getId()
                    );
                    PostFinanceCheckoutHelper::updateOrderMeta(
                        $order,
                        'postFinanceCheckoutMainOrderId',
                        $dataOrder->id
                    );
                    $order->save();
                }
                $emailMessages = PostFinanceCheckoutBasemodule::stopRecordingMailMessages();

                // Update cart <-> PostFinance Checkout mapping <-> order mapping
                $ids = PostFinanceCheckoutHelper::getCartMeta($originalCart, 'mappingIds');
                PostFinanceCheckoutHelper::updateOrderMeta($dataOrder, 'mappingIds', $ids);
                if (Configuration::get(self::CK_MAIL, null, null, $cart->id_shop)) {
                    PostFinanceCheckoutHelper::storeOrderEmails($dataOrder, $emailMessages);
                }
                PostFinanceCheckoutHelper::updateOrderMeta($dataOrder, 'originalCart', $originalCart->id);
                PostFinanceCheckoutHelper::commitDBTransaction();
            } catch (Exception $e) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                throw $e;
            }

            try {
                $transaction = PostFinanceCheckoutServiceTransaction::instance()->confirmTransaction(
                    $dataOrder,
                    $orders,
                    $methodConfiguration->getConfigurationId()
                );
                PostFinanceCheckoutServiceTransaction::instance()->updateTransactionInfo($transaction, $dataOrder);
                $GLOBALS['postfinancecheckoutTransactionIds'] = array(
                    'spaceId' => $transaction->getLinkedSpaceId(),
                    'transactionId' => $transaction->getId()
                );
            } catch (Exception $e) {
                PrestaShopLogger::addLog($e->getMessage(), 3, null, null, false);
                PostFinanceCheckoutHelper::deleteOrderEmails($dataOrder);
                PostFinanceCheckoutBasemodule::startRecordingMailMessages();
                $canceledStatusId = Configuration::get(self::CK_STATUS_FAILED);
                foreach ($orders as $order) {
                    $order->setCurrentState($canceledStatusId);
                    $order->save();
                }
                PostFinanceCheckoutBasemodule::stopRecordingMailMessages();
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'There was a technical issue, please try again.',
                        'basemodule'
                    )
                );
            }
        } else {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'There was a technical issue, please try again.',
                    'basemodule'
                )
            );
        }
    }

    public static function hookActionProductCancel(PostFinanceCheckout $module, $params)
    {
        // check version too here to only run on > 1.7.7 for now 
        // as there is some overlap in functionality with some previous versions 1.7+
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND && version_compare(_PS_VERSION_, '1.7.7', '>=')) {

            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
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

    public static function hookDisplayOrderDetail(PostFinanceCheckout $module, $params)
    {
        $order = $params['order'];

        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $documentVars = array();
        if (in_array(
            $transactionInfo->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
                \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE
            )
        ) && (bool) Configuration::get(self::CK_INVOICE)) {
            $documentVars['postFinanceCheckoutInvoice'] = $module->getContext()->link->getModuleLink(
                'postfinancecheckout',
                'documents',
                array(
                    'type' => 'invoice',
                    'id_order' => $order->id
                ),
                true
            );
        }
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL &&
            (bool) Configuration::get(self::CK_PACKING_SLIP)) {
            $documentVars['postFinanceCheckoutPackingSlip'] = $module->getContext()->link->getModuleLink(
                'postfinancecheckout',
                'documents',
                array(
                    'type' => 'packingSlip',
                    'id_order' => $order->id
                ),
                true
            );
        }
        $module->getContext()->smarty->assign($documentVars);
        return $module->display(dirname(dirname(__FILE__)), 'hook/order_detail.tpl');
    }

    public static function hookActionAdminControllerSetMedia(PostFinanceCheckout $module, $arr)
    {
        if (Tools::strtolower(Tools::getValue('controller')) == 'adminorders') {
            $module->getContext()->controller->addJS(
                __PS_BASE_URI__ . 'modules/' . $module->name . '/views/js/admin/jAlert.min.js'
            );
            $module->getContext()->controller->addJS(__PS_BASE_URI__ . 'modules/' . $module->name . '/views/js/admin/order.js');
            $module->getContext()->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $module->name . '/views/css/admin/order.css'
            );
            $module->getContext()->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $module->name . '/views/css/admin/jAlert.css'
            );
        }
        $module->getContext()->controller->addJS(__PS_BASE_URI__ . 'modules/' . $module->name . '/views/js/admin/general.js');
    }

    public static function hookDisplayBackOfficeHeader(PostFinanceCheckout $module, $params)
    {
        if (Module::isEnabled($module->name)) {
            try {
                PostFinanceCheckoutMigration::migrateDb();
            } catch (Exception $e) {
                $module->displayError(
                    $module->l(
                        sprintf(
                            'Error migrating the database for %s. Please check the log to resolve the issue.',
                            'basemodule'
                        ),
                        'PostFinance Checkout'
                    )
                );
                PrestaShopLogger::addLog($e->getMessage(), 3, null, 'PostFinanceCheckout');
            }
        }
        if (array_key_exists('submitChangeCurrency', $_POST)) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            $backendController = Context::getContext()->controller;
            if (Validate::isLoadedObject($order) && $order->module == $module->name) {
                $backendController->errors[] = Tools::displayError(
                    'You cannot change the currency for this order.',
                    'basemodule'
                );
                unset($_POST['submitChangeCurrency']);
                return;
            }
        }
        self::handleVoucherAddRequest($module);
        self::handleVoucherDeleteRequest($module);
        self::handleRefundRequest($module);
        self::handleCancelProductRequest($module);
    }


    private static function handleVoucherAddRequest(PostFinanceCheckout $module)
    {
        if (array_key_exists('submitNewVoucher', $_POST)) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
            $postData = $_POST;
            unset($_POST['submitNewVoucher']);
            $backendController = Context::getContext()->controller;
            if ($module->hasBackendControllerEditAccess($backendController)) {
                $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
                try {
                    $strategy->processVoucherAddRequest($order, $postData);
                    Tools::redirectAdmin(
                        AdminController::$currentIndex . '&id_order=' . $order->id . '&vieworder&conf=4&token=' .
                        $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to edit this.');
            }
        }
    }

    private static function handleVoucherDeleteRequest(PostFinanceCheckout $module)
    {
        if (Tools::isSubmit('submitDeleteVoucher')) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
            $data = $_GET;
            unset($_GET['submitDeleteVoucher']);
            $backendController = Context::getContext()->controller;
            if ($module->hasBackendControllerEditAccess($backendController)) {
                $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
                try {
                    $strategy->processVoucherDeleteRequest($order, $data);
                    Tools::redirectAdmin(
                        AdminController::$currentIndex . '&id_order=' . $order->id . '&vieworder&conf=4&token=' .
                        $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }

    private static function handleRefundRequest(PostFinanceCheckout $module)
    {
        // We need to do some special handling for refunds requests
        if (array_key_exists('partialRefund', $_POST)) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
            $refundParameters = $_POST;
            $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $refundParameters)) {
                return;
            }
            unset($_POST['partialRefund']);

            $backendController = Context::getContext()->controller;
            if ($module->hasBackendControllerEditAccess($backendController)) {
                try {
                    $parsedData = $strategy->validateAndParseData($order, $refundParameters);
                    PostFinanceCheckoutServiceRefund::instance()->executeRefund($order, $parsedData);
                    Tools::redirectAdmin(
                        AdminController::$currentIndex . '&id_order=' . $order->id . '&vieworder&conf=30&token=' .
                        $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }

    private static function handleCancelProductRequest(PostFinanceCheckout $module)
    {
        if (array_key_exists('cancelProduct', $_POST)) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
            $cancelParameters = $_POST;

            $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $cancelParameters)) {
                return;
            }
            unset($_POST['cancelProduct']);
            $backendController = Context::getContext()->controller;
            if ($module->hasBackendControllerDeleteAccess($backendController)) {
                $strategy = PostFinanceCheckoutBackendStrategyprovider::getStrategy();
                if ($strategy->isCancelRequest($order, $cancelParameters)) {
                    try {
                        $strategy->processCancel($order, $cancelParameters);
                    } catch (Exception $e) {
                        $backendController->errors[] = $e->getMessage();
                    }
                } else {
                    try {
                        $parsedData = $strategy->validateAndParseData($order, $cancelParameters);
                        PostFinanceCheckoutServiceRefund::instance()->executeRefund($order, $parsedData);
                        Tools::redirectAdmin(
                            AdminController::$currentIndex . '&id_order=' . $order->id . '&vieworder&conf=31&token=' .
                            $backendController->token
                        );
                    } catch (Exception $e) {
                        $backendController->errors[] = PostFinanceCheckoutHelper::cleanExceptionMessage(
                            $e->getMessage()
                        );
                    }
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }

    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public static function hookDisplayAdminAfterHeader(PostFinanceCheckout $module)
    {
        $manualTasks = PostFinanceCheckoutServiceManualtask::instance()->getNumberOfManualTasks();
        $url = PostFinanceCheckoutHelper::getBaseGatewayUrl();
        if (count($manualTasks) == 1) {
            $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, key($manualTasks));
            $url .= '/s/' . $spaceId . '/manual-task/list';
        }
        $templateVars = array(
            'manualTotal' => array_sum($manualTasks),
            'manualUrl' => $url
        );
        $module->getContext()->smarty->assign($templateVars);
        $result = $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_after_header.tpl');
        return $result;
    }

    /**
     * Show transaction information
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderMain(PostFinanceCheckout $module, $params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        if ($order->module != $module->name) {
            return;
        }
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $methodId = PostFinanceCheckoutHelper::getOrderMeta($order, 'postFinanceCheckoutMethodId');
        $method = new PostFinanceCheckoutModelMethodconfiguration($methodId);
        $tplVars = array(
            'currency' => new Currency($order->id_currency),
            'configurationName' => $method->getConfigurationName(),
            'methodImage' => PostFinanceCheckoutHelper::getResourceUrl(
                $transactionInfo->getImageBase(),
                $transactionInfo->getImage(),
                PostFinanceCheckoutHelper::convertLanguageIdToIETF($order->id_lang),
                $spaceId,
                $transactionInfo->getSpaceViewId()
            ),
            'transactionState' => PostFinanceCheckoutHelper::getTransactionState($transactionInfo),
            'failureReason' => PostFinanceCheckoutHelper::translate($transactionInfo->getFailureReason()),
            'authorizationAmount' => $transactionInfo->getAuthorizationAmount(),
            'transactionUrl' => PostFinanceCheckoutHelper::getTransactionUrl($transactionInfo),
            'labelsByGroup' => PostFinanceCheckoutHelper::getGroupedChargeAttemptLabels($transactionInfo),
            'voids' => PostFinanceCheckoutModelVoidjob::loadByTransactionId($spaceId, $transactionId),
            'completions' => PostFinanceCheckoutModelCompletionjob::loadByTransactionId($spaceId, $transactionId),
            'refunds' => PostFinanceCheckoutModelRefundjob::loadByTransactionId($spaceId, $transactionId)
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_translate',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'translate'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundUrl'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_amount',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundAmount'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_type',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundType'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_completion_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getCompletionUrl'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_void_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getVoidUrl'
            )
        );

        $module->getContext()->smarty->assign($tplVars);
        return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order_left.tpl');
    }

    /**
     * Show transaction information
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderLeft(PostFinanceCheckout $module, $params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        if ($order->module != $module->name) {
            return;
        }
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $methodId = PostFinanceCheckoutHelper::getOrderMeta($order, 'postFinanceCheckoutMethodId');
        $method = new PostFinanceCheckoutModelMethodconfiguration($methodId);
        $tplVars = array(
            'currency' => new Currency($order->id_currency),
            'configurationName' => $method->getConfigurationName(),
            'methodImage' => PostFinanceCheckoutHelper::getResourceUrl(
                $transactionInfo->getImageBase(),
                $transactionInfo->getImage(),
                PostFinanceCheckoutHelper::convertLanguageIdToIETF($order->id_lang),
                $spaceId,
                $transactionInfo->getSpaceViewId()
            ),
            'transactionState' => PostFinanceCheckoutHelper::getTransactionState($transactionInfo),
            'failureReason' => PostFinanceCheckoutHelper::translate($transactionInfo->getFailureReason()),
            'authorizationAmount' => $transactionInfo->getAuthorizationAmount(),
            'transactionUrl' => PostFinanceCheckoutHelper::getTransactionUrl($transactionInfo),
            'labelsByGroup' => PostFinanceCheckoutHelper::getGroupedChargeAttemptLabels($transactionInfo),
            'voids' => PostFinanceCheckoutModelVoidjob::loadByTransactionId($spaceId, $transactionId),
            'completions' => PostFinanceCheckoutModelCompletionjob::loadByTransactionId($spaceId, $transactionId),
            'refunds' => PostFinanceCheckoutModelRefundjob::loadByTransactionId($spaceId, $transactionId)
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_translate',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'translate'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundUrl'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_amount',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundAmount'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_type',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getRefundType'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_completion_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getCompletionUrl'
            )
        );
        $module->getContext()->smarty->registerPlugin(
            'function',
            'postfinancecheckout_void_url',
            array(
                'PostFinanceCheckoutSmartyfunctions',
                'getVoidUrl'
            )
        );

        $module->getContext()->smarty->assign($tplVars);
        return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order_left.tpl');
    }

    /**
     * Show PostFinance Checkout documents tab
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderTabLink(PostFinanceCheckout $module, $params)
    {
        return self::hookDisplayAdminOrderTabOrder($module, $params);
    }

    /**
     * Show PostFinance Checkout documents tab
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderTabOrder(PostFinanceCheckout $module, $params)
    {
        $order = $params['order'];

        // compatibility for versions >= 1.7.7
        if (is_null($order)) {
            $orderId = $params['id_order'];
            $order = new Order($orderId);
        }

        if ($order->module != $module->name) {
            return;
        }
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['postFinanceCheckoutDocumentsCount'] = 0;
        if (in_array(
            $transactionInfo->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
                \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE
            )
        )) {
            $templateVars['postFinanceCheckoutDocumentsCount'] ++;
        }
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL) {
            $templateVars['postFinanceCheckoutDocumentsCount'] ++;
        }
        $module->getContext()->smarty->assign($templateVars);
        return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order_tab_order.tpl');
    }

    /**
     * Show PostFinance Checkout documents table.
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderTabContent(PostFinanceCheckout $module, $params)
    {
        return self::hookDisplayAdminOrderContentOrder($module, $params);
    }

    /**
     * Show PostFinance Checkout documents table.
     *
     * @param array $params
     * @return string
     */
    public static function hookDisplayAdminOrderContentOrder(PostFinanceCheckout $module, $params)
    {
        $order = $params['order'];

        // compatibility for versions >= 1.7.7
        if (is_null($order)) {
            $orderId = $params['id_order'];
            $order = new Order($orderId);
        }

        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['postFinanceCheckoutDocumentsCount'] = 0;
        $templateVars['postFinanceCheckoutDocuments'] = array();
        if (in_array(
            $transactionInfo->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
                \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE
            )
        )) {
            $templateVars['postFinanceCheckoutDocuments'][] = array(
                'icon' => 'file-text-o',
                'name' => $module->l('Invoice', 'basemodule'),
                'url' => $module->getContext()->link->getAdminLink('AdminPostFinanceCheckoutDocuments') .
                '&action=postFinanceCheckoutInvoice&id_order=' . $order->id
            );
            $templateVars['postFinanceCheckoutDocumentsCount'] ++;
        }
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL) {
            $templateVars['postFinanceCheckoutDocuments'][] = array(
                'icon' => 'truck',
                'name' => $module->l('Packing Slip', 'basemodule'),
                'url' => $module->getContext()->link->getAdminLink('AdminPostFinanceCheckoutDocuments') .
                '&action=postFinanceCheckoutPackingSlip&id_order=' . $order->id
            );
            $templateVars['postFinanceCheckoutDocumentsCount'] ++;
        }
        $module->getContext()->smarty->assign($templateVars);
        return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order_content_order.tpl');
    }

    public static function hookDisplayAdminOrder(PostFinanceCheckout $module, $params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['isPostFinanceCheckoutTransaction'] = true;
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
            if (! PostFinanceCheckoutModelCompletionjob::isCompletionRunningForTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            ) && ! PostFinanceCheckoutModelVoidjob::isVoidRunningForTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            )) {
                $affectedOrders = $order->getBrother()->getResults();
                $affectedIds = array();
                foreach ($affectedOrders as $other) {
                    $affectedIds[] = $other->id;
                }
                sort($affectedIds);
                $templateVars['showAuthorizedActions'] = true;
                $templateVars['affectedOrders'] = $affectedIds;
                $templateVars['voidUrl'] = $module->getContext()->link->getAdminLink('AdminPostFinanceCheckoutOrder', true) .
                    '&action=voidOrder&ajax=1&id_order=' . $orderId;
                $templateVars['completionUrl'] = $module->getContext()->link->getAdminLink(
                    'AdminPostFinanceCheckoutOrder',
                    true
                ) . '&action=completeOrder&ajax=1&id_order=' . $orderId;
            }
        }
        if (in_array(
            $transactionInfo->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE,
                \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL
            )
        )) {
            $templateVars['editButtons'] = true;
            $templateVars['refundChanges'] = true;
        }
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::VOIDED) {
            $templateVars['editButtons'] = true;
            $templateVars['cancelButtons'] = true;
        }

        if (PostFinanceCheckoutModelCompletionjob::isCompletionRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['completionPending'] = true;
        }
        if (PostFinanceCheckoutModelVoidjob::isVoidRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['voidPending'] = true;
        }
        if (PostFinanceCheckoutModelRefundjob::isRefundRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['refundPending'] = true;
        }
        $module->getContext()->smarty->assign($templateVars);
        if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order177.tpl');
        } else {
            return $module->display(dirname(dirname(__FILE__)), 'views/templates/admin/hook/admin_order.tpl');
        }
    }

    public static function hookActionAdminOrdersControllerBefore(PostFinanceCheckout $module, $params)
    {
        // We need to start a db transaction here to revert changes to the order, if the update to PostFinance Checkout fails.
        // But we can not use the ActionAdminOrdersControllerAfter, because these are ajax requests and all of
        // exit the process before the ActionAdminOrdersControllerAfter Hook is called.
        $action = Tools::getValue('action');
        if (in_array($action, array(
            'editProductOnOrder',
            'deleteProductLine',
            'addProductOnOrder'
        ))) {
            $order = new Order((int) Tools::getValue('id_order'));
            if ($order->module != $module->name) {
                return;
            }
            PostFinanceCheckoutHelper::startDBTransaction();
        }
    }

    public static function hookActionObjectOrderPaymentAddBefore(PostFinanceCheckout $module, $params)
    {
        $orderPayment = $params['object'];
        if ($orderPayment instanceof OrderPayment) {
            if ($orderPayment->payment_method == $module->displayName) {
                $order = Order::getByReference($orderPayment->order_reference)->getFirst();
                $orderPayment->payment_method = $order->payment;
            }
        }
    }

    public static function hookActionOrderEdited(PostFinanceCheckout $module, $params)
    {
        // We send the changed line items to PostFinance Checkout after the order has been edited
        $action = Tools::getValue('action');
        if (in_array($action, array(
            'editProductOnOrder',
            'deleteProductLine',
            'addProductOnOrder'
        ))) {
            $modifiedOrder = $params['order'];
            if ($modifiedOrder->module != $module->name) {
                return;
            }

            $orders = $modifiedOrder->getBrother()->getResults();
            $orders[] = $modifiedOrder;

            $lineItems = PostFinanceCheckoutServiceLineitem::instance()->getItemsFromOrders($orders);
            $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($modifiedOrder);
            if (! $transactionInfo) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    $module->l(
                                        'Could not load the corresponding transaction for order with id %d.',
                                        'basemodule'
                                    ),
                                    $modifiedOrder->id
                                )
                            )
                        )
                    )
                );
            }
            if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                $module->l('The line items for this order can not be changed.', 'basemodule')
                            )
                        )
                    )
                );
            }

            try {
                PostFinanceCheckoutServiceTransaction::instance()->updateLineItems(
                    $transactionInfo->getSpaceId(),
                    $transactionInfo->getTransactionId(),
                    $lineItems
                );
            } catch (Exception $e) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    $module->l('Could not update the line items at %s. Reason: %s', 'basemodule'),
                                    'PostFinance Checkout',
                                    PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                                )
                            )
                        )
                    )
                );
            }
            PostFinanceCheckoutHelper::commitDBTransaction();
        }
    }
    
    
    public static function hookDisplayTop(PostFinanceCheckout $module, $params)
    {
        return self::getCronJobItem($module);
    }
    
    public static function getCronJobItem(PostFinanceCheckout $module)
    {
        PostFinanceCheckoutCron::cleanUpHangingCrons();
        PostFinanceCheckoutCron::insertNewPendingCron();
        
        $currentToken = PostFinanceCheckoutCron::getCurrentSecurityTokenForPendingCron();
        if ($currentToken) {
            $url = $module->getContext()->link->getModuleLink(
                'postfinancecheckout',
                'cron',
                array(
                    'security_token' => $currentToken
                ),
                true
            );
            return '<img src="' . $url . '" style="display:none" />';
        }
    }
    
    
    public static function hookPostFinanceCheckoutCron($params)
    {
        $tasks = array();
        $tasks[] = 'PostFinanceCheckoutCron::cleanUpCronDB';
        $voidService = PostFinanceCheckoutServiceTransactionvoid::instance();
        if ($voidService->hasPendingVoids()) {
            $tasks[] = array(
                $voidService,
                "updateVoids"
            );
        }
        $completionService = PostFinanceCheckoutServiceTransactioncompletion::instance();
        if ($completionService->hasPendingCompletions()) {
            $tasks[] = array(
                $completionService,
                "updateCompletions"
            );
        }
        $refundService = PostFinanceCheckoutServiceRefund::instance();
        if ($refundService->hasPendingRefunds()) {
            $tasks[] = array(
                $refundService,
                "updateRefunds"
            );
        }
        return $tasks;
    }
}
