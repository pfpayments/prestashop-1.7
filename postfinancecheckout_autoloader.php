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

spl_autoload_register(
    function ($class) {
        $prefix = 'PostFinanceCheckout';

        // base directory for the prefix
        $baseDir = dirname(__FILE__) . '/inc/';

        // does the class use the prefix?
        $len = Tools::strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // no, move to the next registered autoloader
            return;
        }

        $cleanName = Tools::substr($class, $len);

        // $replaced = str_replace("_", DIRECTORY_SEPARATOR, $cleanName);
        $replaced = preg_replace('/([a-z])([A-Z])/', '$1/$2', $cleanName);

        $file = $baseDir . $replaced . '.php';

        // if the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
);
