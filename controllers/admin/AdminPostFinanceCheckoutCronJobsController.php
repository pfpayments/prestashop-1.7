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

class AdminPostFinanceCheckoutCronJobsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->context->smarty->addTemplateDir($this->getTemplatePath());
        $this->tpl_folder = 'cronjob/';
        $this->bootstrap = true;
    }

    public function initContent()
    {
        $this->handleList();
        parent::initContent();
    }

    private function handleList()
    {
        $this->display = 'list';
        $this->context->smarty->assign('jobs', PostFinanceCheckoutCron::getAllCronJobs());
    }
}
