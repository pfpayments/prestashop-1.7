<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckoutMigration extends PostFinanceCheckoutAbstractmigration
{
    protected static function getMigrations()
    {
        return array(
            '1.0.0' => 'initializeTables',
            '1.0.1' => 'orderStatusUpdate',
            '1.0.2' => 'tokenInfoImproved',
            '1.0.3' => 'updateImageBase',
            '1.0.4' => 'userFailureMessage',
            '1.0.5' => 'addCronJob'
        );
    }

    public static function initializeTables()
    {
        static::installTableBase();
    }

    public static function orderStatusUpdate()
    {
        static::installOrderStatusConfigBase();
        static::installOrderPaymentSaveHookBase();
    }

    public static function tokenInfoImproved()
    {
        static::updateCustomerIdOnTokenInfoBase();
    }

    public static function userFailureMessage()
    {
        static::userFailureMessageBase();
    }
    
    public static function addCronJob()
    {
        $dbInstance = DB::getInstance();
        $result = $dbInstance->execute(
            "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_cron_job(
                `id_cron_job` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `constraint_key` int(10),
                `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                `security_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                `date_scheduled` datetime,
                `date_started` datetime,
                `date_finished` datetime,
                `error_msg` longtext COLLATE utf8_unicode_ci,
                PRIMARY KEY (`id_cron_job`),
                UNIQUE KEY `unq_constraint_key` (`constraint_key`),
                INDEX `idx_state` (`state`),
                INDEX `idx_security_token` (`security_token`),
                INDEX `idx_date_scheduled` (`date_scheduled`),
                INDEX `idx_date_started` (`date_started`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci"
        );
        
        if ($result === false) {
            throw new Exception($dbInstance->getMsgError());
        }
        $moduleInstance = PostFinanceCheckoutHelper::getModuleInstance();
        $moduleInstance->registerHook('postFinanceCheckoutCron');
        $moduleInstance->registerHook('displayTop');
        $moduleInstance->unregisterHook('actionCronJob');
        
        $controllers = $moduleInstance->getBackendControllers();
        if (!Tab::getIdFromClassName('AdminPostFinanceCheckoutCronJobs')) {
            PostFinanceCheckoutBasemodule::addTab($moduleInstance, 'AdminPostFinanceCheckoutCronJobs', $controllers['AdminPostFinanceCheckoutCronJobs']['name'], $controllers['AdminPostFinanceCheckoutCronJobs']['parentId']);
        }
    }
}
