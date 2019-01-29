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
 * This service handles webhooks.
 */
class PostFinanceCheckout_Service_Webhook extends PostFinanceCheckout_Service_Abstract
{
    
    /**
     * The webhook listener API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\WebhookListenerService
     */
    private $webhookListenerService;
    
    /**
     * The webhook url API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\WebhookUrlService
     */
    private $webhookUrlService;
    private $webhookEntities = array();

    /**
     * Constructor to register the webhook entites.
     */
    public function __construct()
    {
        $this->webhookEntities[1487165678181] = new PostFinanceCheckout_Webhook_Entity(
            1487165678181,
            'Manual Task',
            array(
                    \PostFinanceCheckout\Sdk\Model\ManualTaskState::DONE,
                    \PostFinanceCheckout\Sdk\Model\ManualTaskState::EXPIRED,
                    \PostFinanceCheckout\Sdk\Model\ManualTaskState::OPEN
                ),
            'PostFinanceCheckout_Webhook_ManualTask'
        );
        $this->webhookEntities[1472041857405] = new PostFinanceCheckout_Webhook_Entity(
            1472041857405,
            'Payment Method Configuration',
            array(
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETED,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETING,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::INACTIVE
                ),
            'PostFinanceCheckout_Webhook_MethodConfiguration',
            true
        );
        $this->webhookEntities[1472041829003] = new PostFinanceCheckout_Webhook_Entity(
            1472041829003,
            'Transaction',
            array(
                    \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED,
                    \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE,
                    \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED,
                    \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
                    \PostFinanceCheckout\Sdk\Model\TransactionState::VOIDED,
                    \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
                ),
            'PostFinanceCheckout_Webhook_Transaction'
        );
        $this->webhookEntities[1472041819799] = new PostFinanceCheckout_Webhook_Entity(
            1472041819799,
            'Delivery Indication',
            array(
                    \PostFinanceCheckout\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED
                ),
            'PostFinanceCheckout_Webhook_DeliveryIndication'
        );
        
        $this->webhookEntities[1472041831364] = new PostFinanceCheckout_Webhook_Entity(
            1472041831364,
            'Transaction Completion',
            array(
                    \PostFinanceCheckout\Sdk\Model\TransactionCompletionState::FAILED,
                    \PostFinanceCheckout\Sdk\Model\TransactionCompletionState::SUCCESSFUL
                ),
            'PostFinanceCheckout_Webhook_TransactionCompletion'
        );
        
        $this->webhookEntities[1472041867364] = new PostFinanceCheckout_Webhook_Entity(
            1472041867364,
            'Transaction Void',
            array(
                    \PostFinanceCheckout\Sdk\Model\TransactionVoidState::FAILED,
                    \PostFinanceCheckout\Sdk\Model\TransactionVoidState::SUCCESSFUL
                ),
            'PostFinanceCheckout_Webhook_TransactionVoid'
        );
        
        $this->webhookEntities[1472041839405] = new PostFinanceCheckout_Webhook_Entity(
            1472041839405,
            'Refund',
            array(
                    \PostFinanceCheckout\Sdk\Model\RefundState::FAILED,
                    \PostFinanceCheckout\Sdk\Model\RefundState::SUCCESSFUL
                ),
            'PostFinanceCheckout_Webhook_Refund'
        );
        $this->webhookEntities[1472041806455] = new PostFinanceCheckout_Webhook_Entity(
            1472041806455,
            'Token',
            array(
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETED,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETING,
                    \PostFinanceCheckout\Sdk\Model\CreationEntityState::INACTIVE
                ),
            'PostFinanceCheckout_Webhook_Token'
        );
        $this->webhookEntities[1472041811051] = new PostFinanceCheckout_Webhook_Entity(
            1472041811051,
            'Token Version',
            array(
                    \PostFinanceCheckout\Sdk\Model\TokenVersionState::ACTIVE,
                    \PostFinanceCheckout\Sdk\Model\TokenVersionState::OBSOLETE
                ),
            'PostFinanceCheckout_Webhook_TokenVersion'
        );
    }

    
    /**
     * Installs the necessary webhooks in PostFinance Checkout.
     */
    public function install()
    {
        $spaceIds = array();
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(PostFinanceCheckout::CK_SPACE_ID, null, null, $shopId);
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }
                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->webhookEntities as $webhookEntity) {
                    /* @var PostFinanceCheckout_Webhook_Entity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }
                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }
                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     * @param int|string $id
     * @return PostFinanceCheckout_Webhook_Entity
     */
    public function getWebhookEntityForId($id)
    {
        if (isset($this->webhookEntities[$id])) {
            return $this->webhookEntities[$id];
        }
        return null;
    }

    /**
     * Create a webhook listener.
     *
     * @param PostFinanceCheckout_Webhook_Entity $entity
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl
     * @return \PostFinanceCheckout\Sdk\Model\WebhookListenerCreate
     */
    protected function createWebhookListener(PostFinanceCheckout_Webhook_Entity $entity, $spaceId, \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $webhookListener = new \PostFinanceCheckout\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setName('Prestashop ' . $entity->getName());
        $webhookListener->setState(\PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        return $this->getWebhookListenerService()->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl
     * @return \PostFinanceCheckout\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                    $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE),
                    $this->createEntityFilter('url.id', $webhookUrl->getId())
                )
        );
        $query->setFilter($filter);
        return $this->getWebhookListenerService()->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \PostFinanceCheckout\Sdk\Model\WebhookUrlCreate
     */
    protected function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \PostFinanceCheckout\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setUrl($this->getUrl());
        $webhookUrl->setState(\PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookUrl->setName('Prestashop');
        return $this->getWebhookUrlService()->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \PostFinanceCheckout\Sdk\Model\WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url', $this->getUrl())
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->getWebhookUrlService()->search($spaceId, $query);
        if (!empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        $link = Context::getContext()->link;
        
        $shopIds = Shop::getShops(true, null, true);
        asort($shopIds);
        $shopId = reset($shopIds);
        
        $languageIds = Language::getLanguages(true, $shopId, true);
        asort($languageIds);
        $languageId = reset($languageIds);
        
        $url = $link->getModuleLink('postfinancecheckout', 'webhook', array(), true, $languageId, $shopId);
        //We have to  parse the link, because of issue http://forge.prestashop.com/browse/BOOM-5799
        $urlQuery = parse_url($url, PHP_URL_QUERY);
        if (stripos($urlQuery, 'controller=module') !== false && stripos($urlQuery, 'controller=webhook') !== false) {
            $url = str_replace('controller=module', 'fc=module', $url);
        }
        return $url;
    }

    /**
     * Returns the webhook listener API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\WebhookListenerService
     */
    protected function getWebhookListenerService()
    {
        if ($this->webhookListenerService == null) {
            $this->webhookListenerService = new \PostFinanceCheckout\Sdk\Service\WebhookListenerService(PostFinanceCheckout_Helper::getApiClient());
        }
        return $this->webhookListenerService;
    }

    /**
     * Returns the webhook url API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\WebhookUrlService
     */
    protected function getWebhookUrlService()
    {
        if ($this->webhookUrlService == null) {
            $this->webhookUrlService = new \PostFinanceCheckout\Sdk\Service\WebhookUrlService(PostFinanceCheckout_Helper::getApiClient());
        }
        return $this->webhookUrlService;
    }
}
