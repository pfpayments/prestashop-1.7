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

abstract class PostFinanceCheckout_AbstractMigration
{

    const CK_DB_VERSION = 'PFC_DB_VERSION';

    //This method should be abstract, but PHP < 7.0 throws a strict warning for an abstract static method.
    protected static function getMigrations()
    {
        return array();
    }
    
    public static function installDb()
    {
        try {
            static::migrateDb();
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                $e->getMessage(),
                2,
                null,
                'PostFinanceCheckout'
            );
            return false;
        }
        return true;
    }

    public static function migrateDb()
    {
        $currentVersion = Configuration::getGlobalValue(self::CK_DB_VERSION);
        if ($currentVersion === false) {
            $currentVersion = '0.0.0';
        }
        foreach (static::getMigrations() as $version => $functionName) {
            if (version_compare($currentVersion, $version, '<')) {
                PostFinanceCheckout_Helper::startDBTransaction();
                try {
                    call_user_func(array(
                        get_called_class(),
                        $functionName
                    ));
                    Configuration::updateGlobalValue(self::CK_DB_VERSION, $version);
                    PostFinanceCheckout_Helper::commitDBTransaction();
                } catch (Exception $e) {
                    PostFinanceCheckout_Helper::rollbackDBTransaction();
                    throw $e;
                }
                $currentVersion = $version;
            }
        }
    }

    protected static function installTableBase()
    {
        $instance = Db::getInstance();
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_method_configuration(
				`id_method_configuration` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_shop` int(10) unsigned NOT NULL,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`configuration_id` bigint(20) unsigned NOT NULL,
				`configuration_name` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
				`title` longtext COLLATE utf8_unicode_ci,
				`description` longtext COLLATE utf8_unicode_ci,
				`image` varchar(2047) COLLATE utf8_unicode_ci DEFAULT NULL,
                `sort_order` bigint(20) NOT NULL,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 0,
                `show_description` tinyint(1) unsigned NOT NULL DEFAULT 1,
                `show_image` tinyint(1) unsigned NOT NULL DEFAULT 1,
                `tax_rule_group_id` int(10) unsigned DEFAULT 0,
                `fee_base` int(10) unsigned DEFAULT 3,
                `fee_rate` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `fee_fixed` decimal(20,6) NOT NULL DEFAULT '0.000000',
                `fee_add_tax` tinyint(1) unsigned NOT NULL DEFAULT 0,
				`date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_method_configuration`),
				UNIQUE KEY `unq_space_configuration_shop` (`space_id`,`configuration_id`, `id_shop`),
				INDEX `idx_space_id` (`space_id`),
				INDEX `idx_configuration_id` (`configuration_id`),
                INDEX `idx_id_shop` (`id_shop`),
                INDEX `idx_state` (`state`),
                INDEX `idx_active` (`active`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        
       
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_transaction_info(
				`id_transaction_info` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`transaction_id` bigint(20) unsigned NOT NULL,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`space_view_id` bigint(20) unsigned DEFAULT NULL,
				`language` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`currency` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`authorization_amount` decimal(19,8) NOT NULL,
				`image` varchar(2047) COLLATE utf8_unicode_ci DEFAULT NULL,
				`labels` longtext COLLATE utf8_unicode_ci,
				`payment_method_id` bigint(20) unsigned DEFAULT NULL,
				`connector_id` bigint(20) unsigned DEFAULT NULL,
				`order_id` int(10) unsigned NOT NULL,
				`failure_reason` longtext COLLATE utf8_unicode_ci,
				`locked_at` datetime,
				`date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_transaction_info`),
				UNIQUE KEY `unq_transaction_id_space_id` (`transaction_id`,`space_id`),
				UNIQUE KEY `unq_order_id` (`order_id`),
                INDEX `state` (`state`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_token_info(
				`id_token_info` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`token_id` bigint(20) unsigned NOT NULL,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`customer_id` int(10) unsigned NOT NULL,
				`payment_method_id` int(10) unsigned NOT NULL,
				`connector_id` bigint(20) unsigned DEFAULT NULL,
				`date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_token_info`),
				UNIQUE KEY `unq_token_id_space_id` (`token_id`,`space_id`),
				INDEX `idx_customer_id` (`customer_id`),
				INDEX `idx_payment_method_id` (`payment_method_id`),
                INDEX `idx_state` (`state`),
				INDEX `idx_connector_id` (`connector_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_cart_meta(
				`cart_id` int(10) unsigned NOT NULL,
                `meta_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
                `meta_value` longtext COLLATE utf8_unicode_ci NULL,
				UNIQUE KEY `unq_cart_id_key` (`cart_id`,`meta_key`),
                INDEX `idx_cart_id` (`cart_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_order_meta(
				`order_id` int(10) unsigned NOT NULL,
                `meta_key` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
                `meta_value` longtext COLLATE utf8_unicode_ci NULL,
				UNIQUE KEY `unq_order_id_key` (`order_id`,`meta_key`),
                INDEX `idx_order_id` (`order_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
                
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_void_job(
				`id_void_job` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `void_id` bigint(20) unsigned,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`transaction_id` bigint(20) unsigned NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`order_id` bigint(20) unsigned NOT NULL,
				`failure_reason` longtext COLLATE utf8_unicode_ci,
                `date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_void_job`),
				INDEX `idx_transaction_id_space_id` (`transaction_id`,`space_id`),
				INDEX `idx_void_id_space_id` (`void_id`,`space_id`),
                INDEX `idx_state` (`state`),
                INDEX `idx_date_upd` (`date_upd`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_completion_job(
				`id_completion_job` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `completion_id` bigint(20) unsigned,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`transaction_id` bigint(20) unsigned NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`order_id` bigint(20) unsigned NOT NULL,
				`failure_reason` longtext COLLATE utf8_unicode_ci,
                `date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
				PRIMARY KEY (`id_completion_job`),
				INDEX `idx_transaction_id_space_id` (`transaction_id`,`space_id`),
				INDEX `idx_completion_id_space_id` (`completion_id`,`space_id`),
                INDEX `idx_state` (`state`),
                INDEX `idx_date_upd` (`date_upd`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
        $result = $instance->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "pfc_refund_job(
                `id_refund_job` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `refund_id` bigint(20) unsigned,
                `external_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`transaction_id` bigint(20) unsigned NOT NULL,
				`space_id` bigint(20) unsigned NOT NULL,
				`order_id` bigint(20) unsigned NOT NULL,
                `amount` decimal(19,8) NOT NULL,
                `refund_parameters` longtext COLLATE utf8_unicode_ci,
				`failure_reason` longtext COLLATE utf8_unicode_ci,
                `apply_tries` bigint(10) NOT NULL DEFAULT '0',
                `date_add` datetime NOT NULL,
				`date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_refund_job`),
                INDEX `idx_transaction_id_space_id` (`transaction_id`,`space_id`),
                INDEX `idx_refund_id_space_id` (`refund_id`,`space_id`),
                UNIQUE KEY `unq_external_id_space_id` (`external_id`,`space_id`),
                INDEX `idx_state` (`state`),
                INDEX `idx_date_upd` (`date_upd`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
         
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
    }
    
    protected static function installOrderStatusConfigBase()
    {
        
        $authorizedStatus = PostFinanceCheckout_OrderStatus::getAuthorizedOrderStatus();
        $waitingStatus = PostFinanceCheckout_OrderStatus::getWaitingOrderStatus();
        $manualStatus = PostFinanceCheckout_OrderStatus::getManualOrderStatus();
        
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_FAILED, Configuration::get('PS_OS_ERROR'));
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_AUTHORIZED, $authorizedStatus->id);
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_VOIDED, Configuration::get('PS_OS_CANCELED'));
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_COMPLETED, $waitingStatus->id);
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_MANUAL, $manualStatus->id);
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_DECLINED, Configuration::get('PS_OS_CANCELED'));
        Configuration::updateGlobalValue(PostFinanceCheckout::CK_STATUS_FULFILL, Configuration::get('PS_OS_PAYMENT'));
    }
    
    protected static function installOrderPaymentSaveHookBase()
    {
        PostFinanceCheckout_Helper::getModuleInstance()->registerHook('actionObjectOrderPaymentAddBefore');
    }
    
    protected static function updateCustomerIdOnTokenInfoBase()
    {
        $instance = Db::getInstance();
        $result = $instance->execute(
            "ALTER TABLE `" . _DB_PREFIX_ . "pfc_token_info` CHANGE `customer_id` `customer_id` int(10) unsigned NULL DEFAULT NULL;"
        );
        if ($result === false) {
            throw new Exception($instance->getMsgError());
        }
    }
    
    protected static function updateImageBase()
    {
        $instance = Db::getInstance();
        $exists = $instance->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "pfc_method_configuration` LIKE 'image_base'");
        if (empty($exists)) {
            $result = $instance->execute(
                "ALTER TABLE `" . _DB_PREFIX_ . "pfc_method_configuration` ADD COLUMN `image_base` varchar(2047) COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER image;"
            );
            if ($result === false) {
                throw new Exception($instance->getMsgError());
            }
        }
        
        $exists = $instance->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "pfc_transaction_info` LIKE 'image_base'");
        if (empty($exists)) {
            $result = $instance->execute(
                "ALTER TABLE `" . _DB_PREFIX_ . "pfc_transaction_info` ADD COLUMN `image_base` VARCHAR(2047)  COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER image;"
            );
            if ($result === false) {
                throw new Exception($instance->getMsgError());
            }
        }
    }
    
    protected static function userFailureMessageBase()
    {
        $instance = Db::getInstance();
        $exists = $instance->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "pfc_transaction_info` LIKE 'user_failure_message'");
        if (empty($exists)) {
            $result = $instance->execute(
                "ALTER TABLE `" . _DB_PREFIX_ . "pfc_transaction_info` ADD COLUMN `user_failure_message` VARCHAR(2047)  COLLATE utf8_unicode_ci NULL DEFAULT NULL AFTER image;"
            );
            if ($result === false) {
                throw new Exception($instance->getMsgError());
            }
        }
    }
}
