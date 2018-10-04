<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2018 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Provider of language information from the gateway.
 */
class PostFinanceCheckout_Provider_Language extends PostFinanceCheckout_Provider_Abstract
{

    protected function __construct()
    {
        parent::__construct('postfinancecheckout_languages');
    }

    /**
     * Returns the language by the given code.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns the primary language in the given group.
     *
     * @param string $code
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = Tools::substr($code, 0, 2);
        foreach ($this->getAll() as $language) {
            if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
                return $language;
            }
        }
        
        return false;
    }

    /**
     * Returns a list of language.
     *
     * @return \PostFinanceCheckout\Sdk\Model\RestLanguage[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $languageService = new \PostFinanceCheckout\Sdk\Service\LanguageService(PostFinanceCheckout_Helper::getApiClient());
        return $languageService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}
