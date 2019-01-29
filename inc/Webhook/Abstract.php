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

/**
 * Abstract webhook processor.
 */
abstract class PostFinanceCheckout_Webhook_Abstract
{
    private static $instances = array();

    /**
     * @return static
     */
    public static function instance()
    {
        $class = get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    /**
     * Processes the received webhook request.
     *
     * @param PostFinanceCheckout_Webhook_Request $request
     */
    abstract public function process(PostFinanceCheckout_Webhook_Request $request);
}
