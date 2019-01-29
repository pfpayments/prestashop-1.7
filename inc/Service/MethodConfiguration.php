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
 * PostFinanceCheckout_Service_Method_Configuration Class.
 */
class PostFinanceCheckout_Service_MethodConfiguration extends PostFinanceCheckout_Service_Abstract
{

    /**
     * Updates the data of the payment method configuration.
     *
     * @param \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration
     */
    public function updateData(\PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        
        $entities = PostFinanceCheckout_Model_MethodConfiguration::loadByConfiguration(
            $configuration->getLinkedSpaceId(),
            $configuration->getId()
        );
        foreach ($entities as $entity) {
            if ($this->hasChanged($configuration, $entity)) {
                $entity->setConfigurationName($configuration->getName());
                $entity->setState($this->getConfigurationState($configuration));
                $entity->setTitle($configuration->getResolvedTitle());
                $entity->setDescription($configuration->getResolvedDescription());
                $entity->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                $entity->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                $entity->setSortOrder($configuration->getSortOrder());
                $entity->save();
            }
        }
    }

    private function hasChanged(
        \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration,
        PostFinanceCheckout_Model_MethodConfiguration $entity
    ) {
        if ($configuration->getName() != $entity->getConfigurationName()) {
            return true;
        }
        
        if ($this->getConfigurationState($configuration) != $entity->getState()) {
            return true;
        }
        
        if ($configuration->getSortOrder() != $entity->getSortOrder()) {
            return true;
        }
        
        if ($configuration->getResolvedTitle() != $entity->getTitle()) {
            return true;
        }
        
        if ($configuration->getResolvedDescription() !=
             $entity->getDescription()) {
            return true;
        }
        
        $image = $this->getResourcePath($configuration->getResolvedImageUrl());
        if ($image != $entity->getImage()) {
            return true;
        }
        
        $imageBase = $this->getResourceBase($configuration->getResolvedImageUrl());
        if ($imageBase != $entity->getImageBase()) {
            return true;
        }
        
        return false;
    }

    /**
     * Synchronizes the payment method configurations from PostFinance Checkout.
     */
    public function synchronize()
    {
        $existingFound = array();
        
        $existingConfigurations = PostFinanceCheckout_Model_MethodConfiguration::loadAll();
        
        $spaceIdCache = array();
        
        $paymentMethodConfigurationService = new \PostFinanceCheckout\Sdk\Service\PaymentMethodConfigurationService(
            PostFinanceCheckout_Helper::getApiClient()
        );
        
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(PostFinanceCheckout::CK_SPACE_ID, null, null, $shopId);
            
            if ($spaceId) {
                if (!array_key_exists($spaceId, $spaceIdCache)) {
                    $spaceIdCache[$spaceId] = $paymentMethodConfigurationService->search(
                        $spaceId,
                        new \PostFinanceCheckout\Sdk\Model\EntityQuery()
                    );
                }
                $configurations = $spaceIdCache[$spaceId];
                foreach ($configurations as $configuration) {
                    $method = PostFinanceCheckout_Model_MethodConfiguration::loadByConfigurationAndShop($spaceId, $configuration->getId(), $shopId);
                    if ($method->getId() !== null) {
                        $existingFound[] = $method->getId();
                    }
                    $method->setShopId($shopId);
                    $method->setSpaceId($spaceId);
                    $method->setConfigurationId($configuration->getId());
                    $method->setConfigurationName($configuration->getName());
                    $method->setState($this->getConfigurationState($configuration));
                    $method->setTitle($configuration->getResolvedTitle());
                    $method->setDescription($configuration->getResolvedDescription());
                    $method->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                    $method->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                    $method->setSortOrder($configuration->getSortOrder());
                    $method->save();
                }
            }
        }
        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setState(PostFinanceCheckout_Model_MethodConfiguration::STATE_HIDDEN);
                $existingConfiguration->save();
            }
        }
        Cache::clean('postfinancecheckout_methods');
    }

    /**
     * Returns the payment method for the given id.
     *
     * @param int $id
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethod
     */
    protected function getPaymentMethod($id)
    {
        /* @var PostFinanceCheckout_Provider_Payment_Method */
        $methodProvider = PostFinanceCheckout_Provider_PaymentMethod::instance();
        return $methodProvider->find($id);
    }

    /**
     * Returns the state for the payment method configuration.
     *
     * @param \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration
     * @return string
     */
    protected function getConfigurationState(
        \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration $configuration
    ) {
        switch ($configuration->getState()) {
            case \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE:
                return PostFinanceCheckout_Model_MethodConfiguration::STATE_ACTIVE;
            case \PostFinanceCheckout\Sdk\Model\CreationEntityState::INACTIVE:
                return PostFinanceCheckout_Model_MethodConfiguration::STATE_INACTIVE;
            default:
                return PostFinanceCheckout_Model_MethodConfiguration::STATE_HIDDEN;
        }
    }
}
