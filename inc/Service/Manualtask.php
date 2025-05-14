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
 * This service provides methods to handle manual tasks.
 */
class PostFinanceCheckoutServiceManualtask extends PostFinanceCheckoutServiceAbstract
{
    const CONFIG_KEY = 'PFC_MANUAL_TASKS';

    /**
     * Returns the number of open manual tasks.
     *
     * @return array
     */
    public function getNumberOfManualTasks()
    {
        $numberOfManualTasks = array();
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $shopNumberOfManualTasks = Configuration::get(self::CONFIG_KEY, null, null, $shopId);
            if ($shopNumberOfManualTasks != null && $shopNumberOfManualTasks > 0) {
                $numberOfManualTasks[$shopId] = $shopNumberOfManualTasks;
            }
        }
        return $numberOfManualTasks;
    }

    /**
     * Updates the number of open manual tasks.
     *
     * @return array
     */
    public function update()
    {
        $numberOfManualTasks = array();
        $spaceIds = array();
        $manualTaskService = new \PostFinanceCheckout\Sdk\Service\ManualTaskService(
            PostFinanceCheckoutHelper::getApiClient()
        );
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, null, $shopId);
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $shopNumberOfManualTasks = $manualTaskService->count(
                    $spaceId,
                    $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\ManualTaskState::OPEN)
                );
                Configuration::updateValue(self::CONFIG_KEY, $shopNumberOfManualTasks, false, null, $shopId);
                if ($shopNumberOfManualTasks > 0) {
                    $numberOfManualTasks[$shopId] = $shopNumberOfManualTasks;
                }
                $spaceIds[] = $spaceId;
            } else {
                Configuration::updateValue(self::CONFIG_KEY, 0, false, null, $shopId);
            }
        }
        return $numberOfManualTasks;
    }
}
