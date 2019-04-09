<?php
use PostFinanceCheckout\Sdk\Model\PaymentMethod;

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Base implementation for common features for PS1.6 and 1.7
 *
 * @author Nico Eigenmann
 *
 */
abstract class PostFinanceCheckout_AbstractModule extends PaymentModule
{

    const CK_BASE_URL = 'PFC_BASE_GATEWAY_URL';

    const CK_USER_ID = 'PFC_USER_ID';

    const CK_APP_KEY = 'PFC_APP_KEY';

    const CK_SPACE_ID = 'PFC_SPACE_ID';

    const CK_SPACE_VIEW_ID = 'PFC_SPACE_VIEW_ID';

    const CK_MAIL = 'PFC_SHOP_EMAIL';

    const CK_INVOICE = 'PFC_INVOICE_DOWNLOAD';

    const CK_PACKING_SLIP = 'PFC_PACKING_SLIP_DOWNLOAD';

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

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->displayName = 'PostFinance Checkout';
        $this->description = sprintf(
            $this->l('This PrestaShop module enables to process payments with %s.', 'abstractmodule'),
            'PostFinance Checkout'
        );
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'PostFinance Checkout'
        );
        
        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            PostFinanceCheckout_FeeHelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (! empty($this->context->cookie->pfc_error)) {
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

    public function install()
    {
        if (! $this->checkRequirements()) {
            return false;
        }
        if (! parent::install()) {
            return false;
        }
        if (! $this->installHooks()) {
            $this->_errors[] = Tools::displayError('Unable to install hooks.');
            return false;
        }
        if (! $this->installControllers()) {
            $this->_errors[] = Tools::displayError('Unable to install controllers.');
            return false;
        }
        if (! PostFinanceCheckout_Migration::installDb()) {
            $this->_errors[] = Tools::displayError('Unable to install database tables.');
            return false;
        }
        $this->registerOrderStates();
        if (! $this->installConfigurationValues()) {
            $this->_errors[] = Tools::displayError('Unable to install configuration.');
        }
       
        return true;
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallControllers() &&
             $this->uninstallConfigurationValues();
    }

    private function checkRequirements()
    {
        try {
            \PostFinanceCheckout\Sdk\Http\HttpClientFactory::getClient();
        } catch (Exception $e) {
            $this->_errors[] = Tools::displayError(
                'Install the PHP cUrl extension or ensure the \'stream_socket_client\' function is available.'
            );
            return false;
        }
        return true;
    }

    protected function installHooks()
    {
        return $this->registerHook('actionAdminControllerSetMedia') &&
             $this->registerHook('actionAdminOrdersControllerBefore') &&
             $this->registerHook('actionMailSend') && $this->registerHook('actionOrderEdited') &&
             $this->registerHook('displayAdminAfterHeader') &&
             $this->registerHook('displayAdminOrder') &&
             $this->registerHook('displayAdminOrderContentOrder') &&
             $this->registerHook('displayAdminOrderLeft') &&
             $this->registerHook('displayAdminOrderTabOrder') &&
             $this->registerHook('displayBackOfficeHeader') &&
             $this->registerHook('displayOrderDetail') &&
             $this->registerHook('postFinanceCheckoutSettingsChanged');
    }

    protected function installConfigurationValues()
    {
        return Configuration::updateGlobalValue(self::CK_MAIL, true) &&
        Configuration::updateGlobalValue(self::CK_INVOICE, true) &&
        Configuration::updateGlobalValue(self::CK_PACKING_SLIP, true);
    }

    protected function uninstallConfigurationValues()
    {
        return Configuration::deleteByName(self::CK_USER_ID) &&
        Configuration::deleteByName(self::CK_APP_KEY) &&
        Configuration::deleteByName(self::CK_SPACE_ID) &&
        Configuration::deleteByName(self::CK_SPACE_VIEW_ID) &&
        Configuration::deleteByName(self::CK_MAIL) &&
        Configuration::deleteByName(self::CK_INVOICE) &&
        Configuration::deleteByName(self::CK_PACKING_SLIP) &&
        Configuration::deleteByName(self::CK_FEE_ITEM) &&
        Configuration::deleteByName(self::CK_SURCHARGE_ITEM) &&
        Configuration::deleteByName(self::CK_SURCHARGE_TAXM) &&
        Configuration::deleteByName(self::CK_SURCHARGE_AMOUNT) &&
        Configuration::deleteByName(self::CK_SURCHARGE_TOTAL) &&
        Configuration::deleteByName(self::CK_SURCHARGE_BASE) &&
        Configuration::deleteByName(PostFinanceCheckout_Service_ManualTask::CONFIG_KEY) &&
        Configuration::deleteByName(self::CK_STATUS_FAILED) &&
        Configuration::deleteByName(self::CK_STATUS_AUTHORIZED) &&
        Configuration::deleteByName(self::CK_STATUS_VOIDED) &&
        Configuration::deleteByName(self::CK_STATUS_COMPLETED) &&
        Configuration::deleteByName(self::CK_STATUS_MANUAL) &&
        Configuration::deleteByName(self::CK_STATUS_DECLINED) &&
        Configuration::deleteByName(self::CK_STATUS_FULFILL);
    }

    abstract protected function getBackendControllers();

    protected function installControllers()
    {
        foreach ($this->getBackendControllers() as $className => $data) {
            if (Tab::getIdFromClassName($className)) {
                continue;
            }
            if (! $this->addTab($className, $data['name'], $data['parentId'])) {
                return false;
            }
        }
        return true;
    }

    protected function addTab($className, $name, $parentId)
    {
        $tab = new Tab();
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->class_name = $className;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $this->l($name, 'abstractmodule');
        }
        return $tab->save();
    }

    protected function uninstallControllers()
    {
        $result = true;
        foreach ($this->getBackendControllers() as $className => $data) {
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

    protected function registerOrderStates()
    {
        PostFinanceCheckout_OrderStatus::registerOrderStatus();
    }

    abstract public function getContent();
    
    
    protected function displayHelpButtons()
    {
        return $this->display(dirname(__DIR__), 'views/templates/admin/admin_help_buttons.tpl');
    }

    protected function getMailHookActiveWarning()
    {
        $output = "";
        if (! Module::isInstalled('mailhook') || ! Module::isEnabled('mailhook')) {
            $error = "<b>" . $this->l('The module "Mail Hook" is not active.', 'abstractmodule') . "</b>";
            $error .= "<br/>";
            $error .= $this->l('This module is recommend for handling the shop emails. Otherwise the mail sending behavior may be inappropriate.', 'abstractmodule');
            $error .= "<br/>";
            $error .= sprintf(
                $this->l('You can download the module %shere%s.', 'abstractmodule'),
                '<a href="https://github.com/wallee-payment/prestashop-mailhook/releases" target="_blank">',
                '</a>'
            );
            $output .= $this->displayError($error);
        }
        return $output;
    }

    protected function handleSaveAll()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_all')) {
            $refresh = true;
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(
                        self::CK_USER_ID,
                        Tools::getValue(self::CK_USER_ID)
                    );
                    Configuration::updateGlobalValue(
                        self::CK_APP_KEY,
                        Tools::getValue(self::CK_APP_KEY)
                    );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
                } elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    foreach ($this->getConfigurationKeys() as $key) {
                        Configuration::updateValue($key, Tools::getValue($key));
                    }
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
                } else {
                    $refresh = false;
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for Shop Group.', 'abstractmodule')
                    );
                }
            } else {
                Configuration::updateGlobalValue(
                    self::CK_USER_ID,
                    Tools::getValue(self::CK_USER_ID)
                );
                Configuration::updateGlobalValue(
                    self::CK_APP_KEY,
                    Tools::getValue(self::CK_APP_KEY)
                );
                foreach ($this->getConfigurationKeys() as $key) {
                    Configuration::updateValue($key, Tools::getValue($key));
                }
                $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            }
            if ($refresh) {
                $error = Hook::exec('postFinanceCheckoutSettingsChanged');
                if (! empty($error)) {
                    $output .= $this->displayError($error);
                }
            }
        }
        return $output;
    }

    protected function getConfigurationKeys()
    {
        return array(
            self::CK_SPACE_ID,
            self::CK_SPACE_VIEW_ID,
            self::CK_MAIL,
            self::CK_INVOICE,
            self::CK_PACKING_SLIP,
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

    protected function handleSaveApplication()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_application')) {
            $refresh = true;
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(
                        self::CK_USER_ID,
                        Tools::getValue(self::CK_USER_ID)
                    );
                    Configuration::updateGlobalValue(
                        self::CK_APP_KEY,
                        Tools::getValue(self::CK_APP_KEY)
                    );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
                } elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(
                        self::CK_SPACE_ID,
                        Tools::getValue(self::CK_SPACE_ID)
                    );
                    Configuration::updateValue(
                        self::CK_SPACE_VIEW_ID,
                        Tools::getValue(self::CK_SPACE_VIEW_ID)
                    );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
                } else {
                    $refresh = false;
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for Shop Group.', 'abstractmodule')
                    );
                }
            } else {
                Configuration::updateGlobalValue(
                    self::CK_USER_ID,
                    Tools::getValue(self::CK_USER_ID)
                );
                Configuration::updateGlobalValue(
                    self::CK_APP_KEY,
                    Tools::getValue(self::CK_APP_KEY)
                );
                Configuration::updateValue(self::CK_SPACE_ID, Tools::getValue(self::CK_SPACE_ID));
                Configuration::updateValue(
                    self::CK_SPACE_VIEW_ID,
                    Tools::getValue(self::CK_SPACE_VIEW_ID)
                );
                $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            }
            if ($refresh) {
                $error = Hook::exec('postFinanceCheckoutSettingsChanged');
                if (! empty($error)) {
                    $output .= $this->displayError($error);
                }
            }
        }
        return $output;
    }

    protected function handleSaveEmail()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_email')) {
            if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_MAIL, Tools::getValue(self::CK_MAIL));
                $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            } else {
                $output .= $this->displayError(
                    $this->l('You can not store the configuration for all Shops or a Shop Group.', 'abstractmodule')
                );
            }
        }
        return $output;
    }

    protected function handleSaveFeeItem()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_fee_item')) {
            if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(
                        self::CK_FEE_ITEM,
                        Tools::getValue(self::CK_FEE_ITEM)
                    );
                    Configuration::updateValue(
                        self::CK_SURCHARGE_ITEM,
                        Tools::getValue(self::CK_SURCHARGE_ITEM)
                        );
                    Configuration::updateValue(
                        self::CK_SURCHARGE_TAX,
                        Tools::getValue(self::CK_SURCHARGE_TAX)
                        );
                    Configuration::updateValue(
                        self::CK_SURCHARGE_AMOUNT,
                        Tools::getValue(self::CK_SURCHARGE_AMOUNT)
                        );
                    Configuration::updateValue(
                        self::CK_SURCHARGE_TOTAL,
                        Tools::getValue(self::CK_SURCHARGE_TOTAL)
                        );
                    Configuration::updateValue(
                        self::CK_SURCHARGE_BASE,
                        Tools::getValue(self::CK_SURCHARGE_BASE)
                        );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            } else {
                $output .= $this->displayError(
                    $this->l('You can not store the configuration for all Shops or a Shop Group.', 'abstractmodule')
                );
            }
        }
        return $output;
    }

    protected function handleSaveDownload()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_download')) {
            if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_INVOICE, Tools::getValue(self::CK_INVOICE));
                    Configuration::updateValue(
                        self::CK_PACKING_SLIP,
                        Tools::getValue(self::CK_PACKING_SLIP)
                    );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            } else {
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for all Shops or a Shop Group.', 'abstractmodule')
                    );
            }
        }
        return $output;
    }
    
    protected function handleSaveSpaceViewId()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_space_view_id')) {
            if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(
                        self::CK_SPACE_VIEW_ID,
                        Tools::getValue(self::CK_SPACE_VIEW_ID)
                    );
                    $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            } else {
                $output .= $this->displayError(
                    $this->l('You can not store the configuration for all Shops or a Shop Group.', 'abstractmodule')
                );
            }
        }
        return $output;
    }
    
    protected function handleSaveOrderStatus()
    {
        $output = "";
        if (Tools::isSubmit('submit' . $this->name . '_order_status')) {
            if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                Configuration::updateValue(self::CK_STATUS_FAILED, Tools::getValue(self::CK_STATUS_FAILED));
                Configuration::updateValue(self::CK_STATUS_AUTHORIZED, Tools::getValue(self::CK_STATUS_AUTHORIZED));
                Configuration::updateValue(self::CK_STATUS_VOIDED, Tools::getValue(self::CK_STATUS_VOIDED));
                Configuration::updateValue(self::CK_STATUS_COMPLETED, Tools::getValue(self::CK_STATUS_COMPLETED));
                Configuration::updateValue(self::CK_STATUS_MANUAL, Tools::getValue(self::CK_STATUS_MANUAL));
                Configuration::updateValue(self::CK_STATUS_DECLINED, Tools::getValue(self::CK_STATUS_DECLINED));
                Configuration::updateValue(self::CK_STATUS_FULFILL, Tools::getValue(self::CK_STATUS_FULFILL));
                $output .= $this->displayConfirmation($this->l('Settings updated', 'abstractmodule'));
            } else {
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for all Shops or a Shop Group.', 'abstractmodule')
                    );
            }
        }
        return $output;
    }
    
    protected function getFormHelper()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_FORM_LANG'
        ) : 0;
        
        $helper->identifier = $this->identifier;
        
        $helper->title = $this->displayName;
        
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name .
             '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper;
    }

    protected function displayForm()
    {
        $userIdConfig = array(
                'type' => 'text',
            'label' => $this->l('User Id', 'abstractmodule'),
                'name' => self::CK_USER_ID,
                'required' => true,
                'col' => 3,
                'lang' => false
        );
        $userPwConfig = array(
                'type' => 'postfinancecheckout_password',
            'label' => $this->l('Authentication Key', 'abstractmodule'),
                'name' => self::CK_APP_KEY,
                'required' => true,
                'col' => 3,
                'lang' => false
        );
        
        $userIdInfo =  array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
            'html_content' => '<b>' . $this->l('The User Id needs to be configured globally.', 'abstractmodule') .
                     '</b>'
        );
            
        $userPwInfo = array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
                'html_content' => '<b>' .
            $this->l('The Authentication Key needs to be configured globally.', 'abstractmodule') . '</b>'
        );
        
        $spaceIdConfig = array(
                'type' => 'text',
            'label' => $this->l('Space Id', 'abstractmodule'),
                'name' => self::CK_SPACE_ID,
                'required' => true,
                'col' => 3,
                'lang' => false
        );
       
        $spaceIdInfo = array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
            'html_content' => '<b>' . $this->l('The Space Id needs to be configured per shop.', 'abstractmodule') .
                     '</b>'
        );
        
        $generalInputs = array($spaceIdConfig, $userIdConfig, $userPwConfig);
        $buttons = array(
            array(
                'title' => $this->l('Save', 'abstractmodule'),
                'class' => 'pull-right',
                'type' => 'input',
                'icon' => 'process-icon-save',
                'name' => 'submit' . $this->name . '_application'
            )
        );
        
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                $generalInputs = array($spaceIdInfo, $userIdConfig, $userPwConfig);
            } elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $generalInputs = array($spaceIdConfig, $userIdInfo, $userPwInfo);
                array_unshift(
                    $buttons,
                    array(
                        'title' => $this->l('Save All', 'abstractmodule'),
                        'class' => 'pull-right',
                        'type' => 'input',
                        'icon' => 'process-icon-save',
                        'name' => 'submit' . $this->name . '_all'
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
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                )
            );
        }
        $fieldsForm = array();
        // General Settings
        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => 'PostFinance Checkout ' . $this->l('General Settings', 'abstractmodule')
            ),
            'input' => $generalInputs,
            'buttons' => $buttons
        );
        
        if (! $this->context->shop->isFeatureActive() ||
             $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
            $forms = $this->getConfigurationForms();
            foreach ($forms as $form) {
                $fieldsForm[]['form'] = $form;
            }
        }
        
        $helper = $this->getFormHelper();
        $helper->tpl_vars['fields_value'] = $this->getConfigurationValues();
        
        return $helper->generateForm($fieldsForm);
    }

    abstract protected function getConfigurationForms();

    abstract protected function getConfigurationValues();

    protected function getApplicationConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                $values[self::CK_USER_ID] = Configuration::getGlobalValue(self::CK_USER_ID);
                $values[self::CK_APP_KEY] = Configuration::getGlobalValue(self::CK_APP_KEY);
            } elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
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

    protected function getEmailForm()
    {
        $emailConfig = array(
            array(
                'type' => 'switch',
                'label' => $this->l('Send Order Emails', 'abstractmodule'),
                'name' => self::CK_MAIL,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Send', 'abstractmodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disabled', 'abstractmodule')
                    )
                ),
                'desc' => $this->l('Send the prestashop order emails.', 'abstractmodule'),
                'lang' => false
            )
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Order Email Settings', 'abstractmodule')
            ),
            'input' => $emailConfig,
            'buttons' => array(
                array(
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' => $this->l('Save', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_email'
                )
            )
        );
    }

    protected function getEmailConfigValues()
    {
        $values = array();
        if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_MAIL] = (bool) Configuration::get(self::CK_MAIL);
        }
        return $values;
    }

    protected function getFeeForm()
    {
        $feeProducts = Product::getSimpleProducts($this->context->language->id);
        array_unshift(
            $feeProducts,
            array(
                'id_product' => '-1',
                'name' => $this->l('None (disables payment fees)', 'abstractmodule')
            )
        );
        
        $surchargeProducts = Product::getSimpleProducts($this->context->language->id);
        array_unshift(
            $surchargeProducts,
            array(
                'id_product' => '-1',
                'name' => $this->l('None (disables surcharges)', 'abstractmodule')
            )
        );
        
        $defaultCurrency = Currency::getCurrency(Configuration::get('PS_CURRENCY_DEFAULT'));
        $feeItemConfig = array(
            array(
                'type' => 'select',
                'label' => $this->l('Payment Fee Product', 'abstractmodule'),
                'desc' => $this->l('Select the product that should be inserted into the cart as a payment fee.', 'abstractmodule'),
                'name' => self::CK_FEE_ITEM,
                'options' => array(
                    'query' => $feeProducts,
                    'id' => 'id_product',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Minimum Sales Surcharge Product', 'abstractmodule'),
                'desc' => $this->l('Select the product that should be inserted into the cart as a minimal sales surcharge.', 'abstractmodule'),
                'name' => self::CK_SURCHARGE_ITEM,
                'options' => array(
                    'query' => $surchargeProducts,
                    'id' => 'id_product',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Add tax', 'abstractmodule'),
                'name' => self::CK_SURCHARGE_TAX,
                'desc' => $this->l('Should the tax amount be added after the computation or should the tax be included in the computed surcharge.', 'abstractmodule'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Add', 'abstractmodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Inlcuded', 'abstractmodule')
                    )
                ),
                'lang' => false
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Surcharge Amount', 'abstractmodule'),
                'desc' => sprintf(
                    $this->l('The amount has to be entered in the shops default currency. Current default currency: %s', 'abstractmodule'),
                    $defaultCurrency['iso_code']
                    ),
                'name' => self::CK_SURCHARGE_AMOUNT,
                'col' => 3
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Minimum Sales Order Total', 'abstractmodule'),
                'desc' => sprintf(
                    $this->l('The surcharge is added, if the order total is below this amount. The total has to be entered in the shops default currency. Current default currency: %s', 'abstractmodule'),
                    $defaultCurrency['iso_code']
                    ),
                'name' => self::CK_SURCHARGE_TOTAL,
                'col' => 3
            ),
            array(
                'type' => 'select',
                'label' => $this->l('The order total is the following:', 'abstractmodule'),
                'name' => self::CK_SURCHARGE_BASE,
                'options' => array(
                    
                    'query' => array(
                        array(
                            'name' => $this->l('Total (inc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_BOTH_INC
                        ),
                        array(
                            'name' => $this->l('Total (exc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_BOTH_EXC
                        ),
                        array(
                            'name' => $this->l('Total without shipping (inc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_WITHOUT_SHIPPING_INC
                        ),
                        array(
                            'name' => $this->l('Total without shipping (exc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_WITHOUT_SHIPPING_EXC
                        ),
                        array(
                            'name' => $this->l('Products only (inc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_PRODUCTS_INC
                        ),
                        array(
                            'name' => $this->l('Products only (exc Tax)', 'abstractmodule'),
                            'type' => PostFinanceCheckout::TOTAL_MODE_PRODUCTS_EXC
                        )
                    ),
                    'id' => 'type',
                    'name' => 'name'
                )
            )
        );        
                
        return array(
            'legend' => array(
                'title' => $this->l('Fee Item Settings', 'abstractmodule')
            ),
            'input' => $feeItemConfig,
            'buttons' => array(
                array(
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' => $this->l('Save', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_fee_item'
                )
            )
        );
    }

    protected function getFeeItemConfigValues()
    {
        $values = array();
        if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_FEE_ITEM] = (int) Configuration::get(self::CK_FEE_ITEM);
                $values[self::CK_SURCHARGE_ITEM] = (int) Configuration::get(self::CK_SURCHARGE_ITEM);
                $values[self::CK_SURCHARGE_TAX] = (int) Configuration::get(self::CK_SURCHARGE_TAX);
                $values[self::CK_SURCHARGE_AMOUNT] = (float) Configuration::get(self::CK_SURCHARGE_AMOUNT);
                $values[self::CK_SURCHARGE_TOTAL] = (float) Configuration::get(self::CK_SURCHARGE_TOTAL);
                $values[self::CK_SURCHARGE_BASE] = (int) Configuration::get(self::CK_SURCHARGE_BASE);
        }
        return $values;
    }

    protected function getDocumentForm()
    {
        $documentConfig = array(
            array(
                'type' => 'switch',
                'label' => $this->l('Invoice Download', 'abstractmodule'),
                'name' => self::CK_INVOICE,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Allow', 'abstractmodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disallow', 'abstractmodule')
                    )
                ),
                'desc' => sprintf(
                    $this->l('Allow the customers to download the %s invoice.', 'abstractmodule'),
                    'PostFinance Checkout'
                ),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Packing Slip Download', 'abstractmodule'),
                'name' => self::CK_PACKING_SLIP,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Allow', 'abstractmodule')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disallow', 'abstractmodule')
                    )
                ),
                'desc' => sprintf(
                    $this->l('Allow the customers to download the %s packing slip.', 'abstractmodule'),
                    'PostFinance Checkout'
                ),
                'lang' => false
            )
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Document Settings', 'abstractmodule')
            ),
            'input' => $documentConfig,
            'buttons' => array(
                array(
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' => $this->l('Save', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_download'
                )
            )
        );
    }

    protected function getDownloadConfigValues()
    {
        $values = array();
        if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_INVOICE] = (bool) Configuration::get(self::CK_INVOICE);
                $values[self::CK_PACKING_SLIP] = (bool) Configuration::get(self::CK_PACKING_SLIP);
        }
        
        return $values;
    }
    
    protected function getSpaceViewIdForm()
    {
            
            
        $spaceViewIdConfig =  array(
            array(
            'type' => 'text',
                'label' => $this->l('Space View Id', 'abstractmodule'),
            'name' => self::CK_SPACE_VIEW_ID,
            'col' => 3,
            'lang' => false
        ));
       
        return array(
            'legend' => array(
                'title' => $this->l('Space View Id Settings', 'abstractmodule')
            ),
            'input' => $spaceViewIdConfig,
            'buttons' => array(
                array(
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' => $this->l('Save', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_space_view_id'
                )
            )
        );
    }
    
    protected function getSpaceViewIdConfigValues()
    {
        $values = array();
        if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
            $values[self::CK_SPACE_VIEW_ID] = Configuration::get(self::CK_SPACE_VIEW_ID);
        }
        
        return $values;
    }
    
    protected function getOrderStatusForm()
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        
        
        $orderStatusConfig = array(
            array(
                'type' => 'select',
                'label' => $this->l('Failed Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the failed status.', 'abstractmodule'),
                'name' => self::CK_STATUS_FAILED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Authorized Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the authorized status.', 'abstractmodule'),
                'name' => self::CK_STATUS_AUTHORIZED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Voided Status', 'abstractmodule'),
                'desc' =>  $this->l('Status the order enters when the transaction is in the voided status.', 'abstractmodule'),
                'name' => self::CK_STATUS_VOIDED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Waiting Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the completed status and the delivery indication is in a pending state.', 'abstractmodule'),
                'name' => self::CK_STATUS_COMPLETED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Manual  Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the completed status and the delivery indication requires a manual decision.', 'abstractmodule'),
                'name' => self::CK_STATUS_MANUAL,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Decline  Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the declined status.', 'abstractmodule'),
                'name' => self::CK_STATUS_DECLINED,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Fulfill  Status', 'abstractmodule'),
                'desc' => $this->l('Status the order enters when the transaction is in the fulfill status.', 'abstractmodule'),
                'name' => self::CK_STATUS_FULFILL,
                'options' => array(
                    'query' => $orderStates,
                    'id' => 'id_order_state',
                    'name' => 'name'
                )
            ),
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Order Status Settings', 'abstractmodule')
            ),
            'input' => $orderStatusConfig,
            'buttons' => array(
                array(
                    'title' => $this->l('Save All', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' => $this->l('Save', 'abstractmodule'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_order_status'
                )
            )
        );
    }
    
    protected function getOrderStatusConfigValues()
    {
        $values = array();
        if (!$this->context->shop->isFeatureActive() || $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
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

    public function hookPostFinanceCheckoutSettingsChanged($params)
    {
        try {
            PostFinanceCheckout_Helper::resetApiClient();
            PostFinanceCheckout_Helper::getApiClient();
        } catch (PostFinanceCheckout_Exception_IncompleteConfig $e) {
            // We stop here as the configuration is not complete
            return "";
        }
        $errors = array();
        try {
            PostFinanceCheckout_Service_MethodConfiguration::instance()->synchronize();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $this->l('Synchronization of the payment method configurations failed.', 'abstractmodule');
        }
        try {
            PostFinanceCheckout_Service_Webhook::instance()->install();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $this->l('Installation of the webhooks failed, please check if the feature is active in your space.', 'abstractmodule');
        }
        try {
            PostFinanceCheckout_Service_ManualTask::instance()->update();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, null, false);
            $errors[] = $this->l('Update of Manual Tasks failed.', 'abstractmodule');
        }
        $this->deleteCachedEntries();
        if (empty(!$errors)) {
            return $this->l('Please check your credentials and grant the application user the necessary rights (Account Admin) for your space.', 'abstractmodule').' '.implode(" ", $errors);
        }
        return "";
    }

    protected function deleteCachedEntries()
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

    protected function getParametersFromMethodConfiguration(
        PostFinanceCheckout_Model_MethodConfiguration $methodConfiguration,
        Cart $cart,
        $shopId,
        $language
    ) {
        $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, $shopId);
        $spaceViewId = Configuration::get(self::CK_SPACE_VIEW_ID, null, null, $shopId);
        $parameters = array();
        $parameters['methodId'] = $methodConfiguration->getId();
        $parameters['configurationId'] = $methodConfiguration->getConfigurationId();
        $parameters['link'] = $this->context->link->getModuleLink(
            'postfinancecheckout',
            'payment',
            array(
                'methodId' => $methodConfiguration->getId()
            ),
            true
        );
        $name = $methodConfiguration->getConfigurationName();
        $translatedName = PostFinanceCheckout_Helper::translate(
            $methodConfiguration->getTitle(),
            $language
        );
        if (! empty($translatedName)) {
            $name = $translatedName;
        }
        $parameters['name'] = $name;
        $parameters['image'] = '';
        if (! empty($methodConfiguration->getImage()) && $methodConfiguration->isShowImage()) {
            $parameters['image'] = PostFinanceCheckout_Helper::getResourceUrl(
                $methodConfiguration->getImageBase(),
                $methodConfiguration->getImage(),
                PostFinanceCheckout_Helper::convertLanguageIdToIETF($cart->id_lang),
                $spaceId,
                $spaceViewId
            );
        }
        $parameters['description'] = '';
        $description = PostFinanceCheckout_Helper::translate(
            $methodConfiguration->getDescription(),
            $language
        );
        if (! empty($description) && $methodConfiguration->isShowDescription()) {
            
            $description = preg_replace('/((<a (?!.*target="_blank").*?)>)/', '$2 target="_blank">', $description);
            $parameters['description'] = $description;
        }
        $surchargeValues = PostFinanceCheckout_FeeHelper::getSurchargeValues($cart);
        if ($surchargeValues['surcharge_total'] > 0) {
            $parameters['surchargeValues'] = $surchargeValues;
        } else {
            $parameters['surchargeValues'] = array();
        }
        $feeValues = PostFinanceCheckout_FeeHelper::getFeeValues($cart, $methodConfiguration);
        if ($feeValues['fee_total'] > 0) {
            $parameters['feeValues'] = $feeValues;
        } else {
            $parameters['feeValues'] = array();
        }
        return $parameters;
    }

    public function hookActionMailSend($data)
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
        if ($this->active) {
            PostFinanceCheckout_Helper::startDBTransaction();
            try {
                $originalCart = new Cart($id_cart);
                
                // If transaction is no longer pending we stop here and the customer has to go through the checkout again
                PostFinanceCheckout_Service_Transaction::instance()->checkTransactionPending(
                    $originalCart
                );
                $rs = $originalCart->duplicate();
                if (! isset($rs['success']) || ! isset($rs['cart'])) {
                    $error = 'The cart duplication failed. May be some module prevents it.';
                    PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $this->id);
                    throw new Exception("There was a techincal issue, please try again.");
                }
                $cart = $rs['cart'];
                if (! ($cart instanceof Cart)) {
                    $error = 'The duplicated cart is not of type "Cart".';
                    PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $this->id);
                    throw new Exception("There was a techincal issue, please try again.");
                }
                foreach ($originalCart->getCartRules() as $rule) {
                    $ruleObject = $rule['obj'];
                    // Because free gift cart rules adds a product to the order, the product is already in the duplicated order,
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
                
                $methodConfiguration = null;
                if (strpos($payment_method, "postfinancecheckout_") === 0) {
                    $id = Tools::substr($payment_method, strpos($payment_method, "_") + 1);
                    $methodConfiguration = new PostFinanceCheckout_Model_MethodConfiguration($id);
                }
                
                if ($methodConfiguration == null || $methodConfiguration->getId() == null ||
                     $methodConfiguration->getState() !=
                     PostFinanceCheckout_Model_MethodConfiguration::STATE_ACTIVE || $methodConfiguration->getSpaceId() !=
                     Configuration::get(self::CK_SPACE_ID, null, null, $cart->id_shop)) {
                    $error = 'PostFinanceCheckout method configuration called with wrong payment method configuration. Method: ' .
                     $payment_method;
                     PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', (int) $this->id);
                    throw new Exception("There was a techincal issue, please try again.");
                }
                
                $title = $methodConfiguration->getConfigurationName();
                $translatedTitel = PostFinanceCheckout_Helper::translate(
                    $methodConfiguration->getTitle(),
                    $cart->id_lang
                );
                if ($translatedTitel !== null) {
                    $title = $translatedTitel;
                }
                
                PostFinanceCheckout::startRecordingMailMessages();
                parent::validateOrder(
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
                
                $lastOrderId = $this->currentOrder;
                $dataOrder = new Order($lastOrderId);
                $orders = $dataOrder->getBrother()->getResults();
                $orders[] = $dataOrder;
                foreach ($orders as $order) {
                    PostFinanceCheckout_Helper::updateOrderMeta(
                        $order,
                        'postFinanceCheckoutMethodId',
                        $methodConfiguration->getId()
                    );
                    PostFinanceCheckout_Helper::updateOrderMeta(
                        $order,
                        'postFinanceCheckoutMainOrderId',
                        $dataOrder->id
                    );
                    $order->save();
                }
                $emailMessages = PostFinanceCheckout::stopRecordingMailMessages();
                
                // Update cart <-> PostFinance Checkout mapping <-> order mapping
                $ids = PostFinanceCheckout_Helper::getCartMeta($originalCart, 'mappingIds');
                PostFinanceCheckout_Helper::updateOrderMeta($dataOrder, 'mappingIds', $ids);
                if (Configuration::get(self::CK_MAIL, null, null, $cart->id_shop)) {
                    PostFinanceCheckout_Helper::storeOrderEmails($dataOrder, $emailMessages);
                }
                PostFinanceCheckout_Helper::updateOrderMeta($dataOrder, 'originalCart', $originalCart->id);
                PostFinanceCheckout_Helper::commitDBTransaction();
            } catch (Exception $e) {
                PostFinanceCheckout_Helper::rollbackDBTransaction();
                throw $e;
            }
                        
            try {
                $transaction = PostFinanceCheckout_Service_Transaction::instance()->confirmTransaction(
                    $dataOrder,
                    $orders
                );
                PostFinanceCheckout_Service_Transaction::instance()->updateTransactionInfo($transaction, $dataOrder);
                $GLOBALS['postfinancecheckoutTransactionIds'] = array('spaceId' => $transaction->getLinkedSpaceId(),'transactionId' => $transaction->getId());
            } catch (Exception $e) {
                PrestaShopLogger::addLog($e->getMessage(), 3, null, null, false);
                PostFinanceCheckout_Helper::deleteOrderEmails($dataOrder);
                PostFinanceCheckout::startRecordingMailMessages();
                $canceledStatusId = Configuration::get(self::CK_STATUS_FAILED);
                foreach ($orders as $order) {
                    $order->setCurrentState($canceledStatusId);
                    $order->save();
                }
                PostFinanceCheckout::stopRecordingMailMessages();
                throw new Exception(
                    PostFinanceCheckout_Helper::getModuleInstance()->l('There was a techincal issue, please try again.', 'abstractmodule')
                );
            }
        } else {
            throw new Exception(
                PostFinanceCheckout_Helper::getModuleInstance()->l('There was a techincal issue, please try again.', 'abstractmodule')
            );
        }
    }
    
    public function hookDisplayOrderDetail($params)
    {
        $order = $params['order'];
        
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
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
            $documentVars['postFinanceCheckoutInvoice'] = $this->context->link->getModuleLink(
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
            $documentVars['postFinanceCheckoutPackingSlip'] = $this->context->link->getModuleLink(
                'postfinancecheckout',
                'documents',
                array(
                    'type' => 'packingSlip',
                    'id_order' => $order->id
                ),
                true
            );
        }
        $this->context->smarty->assign($documentVars);
        return $this->display(dirname(__DIR__), 'hook/order_detail.tpl');
    }
    
    public function hookActionAdminControllerSetMedia($arr)
    {
        if (Tools::strtolower(Tools::getValue('controller')) == 'adminorders') {
            $this->context->controller->addJS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/admin/jAlert.min.js'
            );
            $this->context->controller->addJS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/admin/order.js'
            );
            $this->context->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/order.css'
            );
            $this->context->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/jAlert.css'
            );
        }
        $this->context->controller->addJS(
            __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/admin/general.js'
        );
    }
    
    public function hookDisplayBackOfficeHeader($params)
    {
        if (Module::isEnabled($this->name)) {
            try {
                PostFinanceCheckout_Migration::migrateDb();
            } catch (Exception $e) {
                $this->displayError(
                    $this->l(sprintf('Error migrating the database for %s. Please check the log to resolve the issue.', 'abstractmodule'), 'PostFinance Checkout')
                );
                PrestaShopLogger::addLog(
                    $e->getMessage(),
                    3,
                    null,
                    'PostFinanceCheckout'
                );
            }
        }
        if (isset($_POST['submitChangeCurrency'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            $backendController = Context::getContext()->controller;
            if (Validate::isLoadedObject($order) && $order->module == $this->name) {
                $backendController->errors[] = Tools::displayError('You cannot change the currency for this order.', 'abstractmodule');
                unset($_POST['submitChangeCurrency']);
                return;
            }
        }
        $this->handleVoucherAddRequest();
        $this->handleVoucherDeleteRequest();
        $this->handleRefundRequest();
        $this->handleCancelProductRequest();
    }
    
    abstract protected function hasBackendControllerEditAccess(AdminController $backendController);
    
    abstract protected function hasBackendControllerDeleteAccess(AdminController $backendController);
    
    protected function handleVoucherAddRequest()
    {
        if (isset($_POST['submitNewVoucher'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            $backendController = Context::getContext()->controller;
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $postData = $_POST;
            unset($_POST['submitNewVoucher']);
            $backendController = Context::getContext()->controller;
            if ($this->hasBackendControllerEditAccess($backendController)) {
                $strategy = PostFinanceCheckout_Backend_StrategyProvider::getStrategy();
                try {
                    $strategy->processVoucherAddRequest($order, $postData);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                        '&vieworder&conf=4&token=' . $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckout_Helper::cleanExceptionMessage(
                        $e->getMessage()
                    );
                }
            } else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to edit this.'
                );
            }
        }
    }
    
    protected function handleVoucherDeleteRequest()
    {
        if (Tools::isSubmit('submitDeleteVoucher')) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $data = $_GET;
            unset($_GET['submitDeleteVoucher']);
            $backendController = Context::getContext()->controller;
            if ($this->hasBackendControllerEditAccess($backendController)) {
                $strategy = PostFinanceCheckout_Backend_StrategyProvider::getStrategy();
                try {
                    $strategy->processVoucherDeleteRequest($order, $data);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                        '&vieworder&conf=4&token=' . $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckout_Helper::cleanExceptionMessage(
                        $e->getMessage()
                    );
                }
            } else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.'
                );
            }
        }
    }
    
    protected function handleRefundRequest()
    {
        // We need to do some special handling for refunds requests
        if (isset($_POST['partialRefund'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $refundParameters = $_POST;
            $strategy = PostFinanceCheckout_Backend_StrategyProvider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $refundParameters)) {
                return;
            }
            unset($_POST['partialRefund']);
            
            $backendController = Context::getContext()->controller;
            if ($this->hasBackendControllerEditAccess($backendController)) {
                try {
                    $parsedData = $strategy->validateAndParseData($order, $refundParameters);
                    PostFinanceCheckout_Service_Refund::instance()->executeRefund($order, $parsedData);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                        '&vieworder&conf=30&token=' . $backendController->token
                    );
                } catch (Exception $e) {
                    $backendController->errors[] = PostFinanceCheckout_Helper::cleanExceptionMessage(
                        $e->getMessage()
                    );
                }
            } else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.'
                );
            }
        }
    }
    
    protected function handleCancelProductRequest()
    {
        if (isset($_POST['cancelProduct'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $cancelParameters = $_POST;
            
            $strategy = PostFinanceCheckout_Backend_StrategyProvider::getStrategy();
            if ($strategy->isVoucherOnlyPostFinanceCheckout($order, $cancelParameters)) {
                return;
            }
            unset($_POST['cancelProduct']);
            $backendController = Context::getContext()->controller;
            if ($this->hasBackendControllerDeleteAccess($backendController)) {
                $strategy = PostFinanceCheckout_Backend_StrategyProvider::getStrategy();
                if ($strategy->isCancelRequest($order, $cancelParameters)) {
                    try {
                        $strategy->processCancel($order, $cancelParameters);
                    } catch (Exception $e) {
                        $backendController->errors[] = $e->getMessage();
                    }
                } else {
                    try {
                        $parsedData = $strategy->validateAndParseData($order, $cancelParameters);
                        PostFinanceCheckout_Service_Refund::instance()->executeRefund(
                            $order,
                            $parsedData
                        );
                        Tools::redirectAdmin(
                            $backendController::$currentIndex . '&id_order=' . $order->id .
                            '&vieworder&conf=31&token=' . $backendController->token
                        );
                    } catch (Exception $e) {
                        $backendController->errors[] = PostFinanceCheckout_Helper::cleanExceptionMessage(
                            $e->getMessage()
                        );
                    }
                }
            } else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.'
                );
            }
        }
    }
    
    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $manualTasks = PostFinanceCheckout_Service_ManualTask::instance()->getNumberOfManualTasks();
        $url = PostFinanceCheckout_Helper::getBaseGatewayUrl();
        if (count($manualTasks) == 1) {
            $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, key($manualTasks));
            $url .= '/s/' . $spaceId . '/manual-task/list';
        }
        $templateVars = array(
            'manualTotal' => array_sum($manualTasks),
            'manualUrl' => $url
        );
        $this->context->smarty->assign($templateVars);
        $result = $this->display(dirname(__DIR__), 'views/templates/admin/hook/admin_after_header.tpl');
        return $result;
    }
    
    /**
     * Show transaction information
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        if ($order->module != $this->name) {
            return;
        }
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $methodId = PostFinanceCheckout_Helper::getOrderMeta($order, 'postFinanceCheckoutMethodId');
        $method = new PostFinanceCheckout_Model_MethodConfiguration($methodId);
        $tplVars = array(
            'currency' => new Currency($order->id_currency),
            'configurationName' => $method->getConfigurationName(),
            'methodImage' => PostFinanceCheckout_Helper::getResourceUrl(
                $transactionInfo->getImageBase(),
                $transactionInfo->getImage(),
                PostFinanceCheckout_Helper::convertLanguageIdToIETF($order->id_lang),
                $spaceId,
                $transactionInfo->getSpaceViewId()
            ),
            'transactionState' => PostFinanceCheckout_Helper::getTransactionState($transactionInfo),
            'failureReason' => PostFinanceCheckout_Helper::translate(
                $transactionInfo->getFailureReason()
            ),
            'authorizationAmount' => $transactionInfo->getAuthorizationAmount(),
            'transactionUrl' => PostFinanceCheckout_Helper::getTransactionUrl($transactionInfo),
            'labelsByGroup' => PostFinanceCheckout_Helper::getGroupedChargeAttemptLabels(
                $transactionInfo
            ),
            'voids' => PostFinanceCheckout_Model_VoidJob::loadByTransactionId($spaceId, $transactionId),
            'completions' => PostFinanceCheckout_Model_CompletionJob::loadByTransactionId(
                $spaceId,
                $transactionId
            ),
            'refunds' => PostFinanceCheckout_Model_RefundJob::loadByTransactionId(
                $spaceId,
                $transactionId
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_translate',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'translate'
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_url',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'getRefundUrl'
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_amount',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'getRefundAmount'
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_refund_type',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'getRefundType'
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_completion_url',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'getCompletionUrl'
            )
        );
        $this->context->smarty->registerPlugin(
            'function',
            'postfinancecheckout_void_url',
            array(
                'PostFinanceCheckout_SmartyFunctions',
                'getVoidUrl'
            )
        );
        
        $this->context->smarty->assign($tplVars);
        return $this->display(dirname(__DIR__), 'views/templates/admin/hook/admin_order_left.tpl');
    }
    
    /**
     * Show PostFinance Checkout documents tab
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderTabOrder($params)
    {
        $order = $params['order'];
        if ($order->module != $this->name) {
            return;
        }
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
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
        $this->context->smarty->assign($templateVars);
        return $this->display(dirname(__DIR__), 'views/templates/admin/hook/admin_order_tab_order.tpl');
    }
    
    /**
     * Show PostFinance Checkout documents table.
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderContentOrder($params)
    {
        $order = $params['order'];
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
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
                'name' => $this->l('Invoice', 'abstractmodule'),
                'url' => $this->context->link->getAdminLink('AdminPostFinanceCheckoutDocuments') .
                     '&action=postFinanceCheckoutInvoice&id_order=' . $order->id
            );
        }
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL) {
            $templateVars['postFinanceCheckoutDocuments'][] = array(
                'icon' => 'truck',
                'name' => $this->l('Packing Slip', 'abstractmodule'),
                'url' => $this->context->link->getAdminLink('AdminPostFinanceCheckoutDocuments') .
                     '&action=postFinanceCheckoutPackingSlip&id_order=' . $order->id
            );
        }
        $this->context->smarty->assign($templateVars);
        return $this->display(
            dirname(__DIR__),
            'views/templates/admin/hook/admin_order_content_order.tpl'
        );
    }
    
    public function hookDisplayAdminOrder($params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['isPostFinanceCheckoutTransaction'] = true;
        if ($transactionInfo->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
            if (! PostFinanceCheckout_Model_CompletionJob::isCompletionRunningForTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            ) && ! PostFinanceCheckout_Model_VoidJob::isVoidRunningForTransaction(
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
                $templateVars['voidUrl'] = $this->context->link->getAdminLink(
                    'AdminPostFinanceCheckoutOrder',
                    true
                ) . '&action=voidOrder&ajax=1&id_order=' .
                     $orderId;
                $templateVars['completionUrl'] = $this->context->link->getAdminLink(
                    'AdminPostFinanceCheckoutOrder',
                    true
                ) . '&action=completeOrder&ajax=1&id_order=' .
                     $orderId;
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
        
        if (PostFinanceCheckout_Model_CompletionJob::isCompletionRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['completionPending'] = true;
        }
        if (PostFinanceCheckout_Model_VoidJob::isVoidRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['voidPending'] = true;
        }
        if (PostFinanceCheckout_Model_RefundJob::isRefundRunningForTransaction(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        )) {
            $templateVars['refundPending'] = true;
        }
        $this->context->smarty->assign($templateVars);
        return $this->display(dirname(__DIR__), 'views/templates/admin/hook/admin_order.tpl');
    }
    
    public function hookActionAdminOrdersControllerBefore($params)
    {
        // We need to start a db transaction here to revert changes to the order, if the update to PostFinance Checkout fails.
        // But we can not use the ActionAdminOrdersControllerAfter, because these are ajax requests and all of
        // exit the process before the ActionAdminOrdersControllerAfter Hook is called.
        $action = Tools::getValue('action');
        if (in_array(
            $action,
            array(
                'editProductOnOrder',
                'deleteProductLine',
                'addProductOnOrder'
            )
        )) {
            $order = new Order((int) Tools::getValue('id_order'));
            if ($order->module != $this->name) {
                return;
            }
            PostFinanceCheckout_Helper::startDBTransaction();
        }
    }
    
    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        $orderPayment = $params['object'];
        if ($orderPayment instanceof OrderPayment) {
            if ($orderPayment->payment_method == $this->displayName) {
                $order = Order::getByReference($orderPayment->order_reference)->getFirst();
                $orderPayment->payment_method = $order->payment;
            }
        }
    }
    
    public function hookActionOrderEdited($params)
    {
        // We send the changed line items to PostFinance Checkout after the order has been edited
        $action = Tools::getValue('action');
        if (in_array(
            $action,
            array(
                'editProductOnOrder',
                'deleteProductLine',
                'addProductOnOrder'
            )
        )) {
            $modifiedOrder = $params['order'];
            if ($modifiedOrder->module != $this->name) {
                return;
            }
            
            $orders = $modifiedOrder->getBrother()->getResults();
            $orders[] = $modifiedOrder;
            
            $lineItems = PostFinanceCheckout_Service_LineItem::instance()->getItemsFromOrders($orders);
            $transactionInfo = PostFinanceCheckout_Helper::getTransactionInfoForOrder($modifiedOrder);
            if (! $transactionInfo) {
                PostFinanceCheckout_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    $this->l('Could not load the corresponding transaction for order with id %d.', 'abstractmodule'),
                                    $modifiedOrder->id
                                )
                            )
                        )
                    )
                );
            }
            if ($transactionInfo->getState() !=
                 \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                PostFinanceCheckout_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                $this->l('The line items for this order can not be changed.', 'abstractmodule')
                            )
                        )
                    )
                );
            }
            
            try {
                PostFinanceCheckout_Service_Transaction::instance()->updateLineItems(
                    $transactionInfo->getSpaceId(),
                    $transactionInfo->getTransactionId(),
                    $lineItems
                );
            } catch (Exception $e) {
                PostFinanceCheckout_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    $this->l('Could not update the line items at %s. Reason: %s', 'abstractmodule'),
                                    'PostFinance Checkout',
                                    PostFinanceCheckout_Helper::cleanExceptionMessage(
                                        $e->getMessage()
                                    )
                                )
                            )
                        )
                    )
                );
            }
            PostFinanceCheckout_Helper::commitDBTransaction();
        }
    }
}

