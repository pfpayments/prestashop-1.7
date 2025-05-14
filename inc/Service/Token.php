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

/**
 * This service provides functions to deal with PostFinance Checkout tokens.
 */
class PostFinanceCheckoutServiceToken extends PostFinanceCheckoutServiceAbstract
{

    /**
     * The token API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TokenService
     */
    private $tokenService;

    /**
     * The token version API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TokenVersionService
     */
    private $tokenVersionService;

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->getTokenVersionService()->read($spaceId, $tokenVersionId);
        $this->updateInfo($spaceId, $tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('token.id', $tokenId),
                $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->getTokenVersionService()->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateInfo($spaceId, current($tokenVersions));
        } else {
            $info = PostFinanceCheckoutModelTokeninfo::loadByToken($spaceId, $tokenId);
            if ($info->getId()) {
                $info->delete();
            }
        }
    }

    protected function updateInfo($spaceId, \PostFinanceCheckout\Sdk\Model\TokenVersion $tokenVersion)
    {
        $info = PostFinanceCheckoutModelTokeninfo::loadByToken($spaceId, $tokenVersion->getToken()->getId());
        if (! in_array(
            $tokenVersion->getToken()->getState(),
            array(
                \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE,
                \PostFinanceCheckout\Sdk\Model\CreationEntityState::INACTIVE
            )
        )) {
            if ($info->getId()) {
                $info->delete();
            }
            return;
        }

        $info->setCustomerId($tokenVersion->getToken()
            ->getCustomerId());
        $info->setName($tokenVersion->getName());

        $info->setPaymentMethodId(
            $tokenVersion->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getId()
        );
        $info->setConnectorId($tokenVersion->getPaymentConnectorConfiguration()
            ->getConnector());

        $info->setSpaceId($spaceId);
        $info->setState($tokenVersion->getToken()
            ->getState());
        $info->setTokenId($tokenVersion->getToken()
            ->getId());
        $info->save();
    }

    public function deleteToken($spaceId, $tokenId)
    {
        $this->getTokenService()->delete($spaceId, $tokenId);
    }

    /**
     * Returns the token API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TokenService
     */
    protected function getTokenService()
    {
        if ($this->tokenService == null) {
            $this->tokenService = new \PostFinanceCheckout\Sdk\Service\TokenService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }

        return $this->tokenService;
    }

    /**
     * Returns the token version API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TokenVersionService
     */
    protected function getTokenVersionService()
    {
        if ($this->tokenVersionService == null) {
            $this->tokenVersionService = new \PostFinanceCheckout\Sdk\Service\TokenVersionService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }

        return $this->tokenVersionService;
    }
}
