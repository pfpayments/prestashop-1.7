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

class PostFinanceCheckoutOrderstatus
{
    private static $orderStatesConfig = array(
        'PFC_REDIRECTED' => array(
            'color' => '#4169e1',
            'name' => 'PostFinance Checkout Processing',
            'invoice' => 0,
            'logable' => 0,
            'image' => 'redirected'
        ),
        'PFC_AUTHORIZED' => array(
            'color' => '#0000cd',
            'name' => 'PostFinance Checkout Authorized',
            'invoice' => 0,
            'logable' => 1,
            'image' => 'authorized'
        ),
        'PFC_WAITING' => array(
            'color' => '#000080',
            'name' => 'PostFinance Checkout Waiting',
            'invoice' => 0,
            'logable' => 1,
            'image' => 'waiting'
        ),
        'PFC_MANUAL' => array(
            'color' => '#191970',
            'name' => 'PostFinance Checkout Manual Decision',
            'invoice' => 0,
            'logable' => 1,
            'image' => 'manual'
        )
    );

    private static $orderStates = array();

    public static function getRedirectOrderStatus()
    {
        return self::getOrderStatus('PFC_REDIRECTED');
    }

    public static function getAuthorizedOrderStatus()
    {
        return self::getOrderStatus('PFC_AUTHORIZED');
    }

    public static function getWaitingOrderStatus()
    {
        return self::getOrderStatus('PFC_WAITING');
    }

    public static function getManualOrderStatus()
    {
        return self::getOrderStatus('PFC_MANUAL');
    }

    public static function registerOrderStatus()
    {
        foreach (array_keys(self::$orderStatesConfig) as $key) {
            self::getOrderStatusId($key);
        }
    }

    private static function getOrderStatusId($key)
    {
        $result = Configuration::getGlobalValue($key);
        if (! empty($result)) {
            return $result;
        }
        // Just in case order state is deleted after installation
        return self::createOrderState($key);
    }

    /**
     *
     * @return OrderState
     */
    private static function getOrderStatus($key)
    {
        if (! isset(self::$orderStates[$key]) || self::$orderStates[$key] === null) {
            self::$orderStates[$key] = new OrderState(self::getOrderStatusId($key));
        }
        return self::$orderStates[$key];
    }

    private static function createOrderState($key)
    {
        $config = self::$orderStatesConfig[$key];
        $state = new OrderState();
        $state->color = $config['color'];
        $state->deleted = 0;
        $state->hidden = 0;
        $state->logable = $config['logable'];
        ;
        foreach (Language::getLanguages() as $language) {
            $state->name[$language['id_lang']] = $config['name'];
        }
        $state->delivery = 0;
        $state->invoice = $config['invoice'];
        $state->paid = 0;
        $state->send_email = 0;
        $state->template = '';
        $state->unremovable = 1;
        $state->module_name = 'postfinancecheckout';
        $state->add();
        $source = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'views/img/logo' . DIRECTORY_SEPARATOR .
            $config['image'] . '.gif';
        $destination = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'os' . DIRECTORY_SEPARATOR .
            (int) $state->id . '.gif';
        copy($source, $destination);
        self::setOrderStatusId($key, $state->id);
        return $state->id;
    }

    private static function setOrderStatusId($key, $id)
    {
        return Configuration::updateGlobalValue($key, $id);
    }
}
