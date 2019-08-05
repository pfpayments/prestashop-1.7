<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckoutWebhookModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;

    public $ssl = true;

    public function handleWebhookErrors($errno, $errstr, $errfile, $errline)
    {
        $fatal = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
        if ($errno & $fatal) {
            throw new ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
        }
        return false;
    }

    public function postProcess()
    {
        // We set the status to 500, so if we encounter a state where prestashop just dies instead of throwing an error,
        // the webhook is marked as failed.
        header('HTTP/1.1 500 Internal Server Error');
        $webhookService = PostFinanceCheckoutServiceWebhook::instance();
        set_error_handler(array(
            $this,
            'handleWebhookErrors'
        ));
        try {
            $requestBody = trim(Tools::file_get_contents("php://input"));

            $parsed = Tools::jsonDecode($requestBody);
            if ($parsed == false) {
                throw new Exception('Could not parse request body.');
            }
            $request = new PostFinanceCheckoutWebhookRequest($parsed);
            $webhookEntity = $webhookService->getWebhookEntityForId($request->getListenerEntityId());
            if ($webhookEntity === null) {
                throw new Exception(
                    sprintf(
                        'Could not retrieve webhook model for listener entity id: %s',
                        $request->getListenerEntityId()
                    )
                );
            }
            $webhookHandlerClassName = $webhookEntity->getHandlerClassName();
            $webhookHandler = $webhookHandlerClassName::instance();
            $webhookHandler->process($request);
            header('HTTP/1.1 200 OK');
            die();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3, null, 'PostFinanceCheckoutWebhookEntity');
            echo $e->getMessage();
            die();
        }
    }

    public function setMedia()
    {
        // We do not need styling here
    }

    protected function displayMaintenancePage()
    {
        // We never display the maintenance page.
    }

    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }

    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}
