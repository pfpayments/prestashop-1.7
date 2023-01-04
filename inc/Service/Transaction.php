<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This service provides functions to deal with PostFinance Checkout transactions.
 */
class PostFinanceCheckoutServiceTransaction extends PostFinanceCheckoutServiceAbstract
{

    /**
     * Cache for cart transactions.
     *
     * @var \PostFinanceCheckout\Sdk\Model\Transaction[]
     */
    private static $transactionCache = array();

    /**
     * Cache for possible payment methods by cart.
     *
     * @var \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodCache = array();

    /**
     * The transaction API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionService
     */
    private $transactionService;
    
    /**
     * The transaction iframe API service to retrieve js url.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionIframeService
     */
    private $transactionIframeService;
    
    /**
     * The transaction payment page API service to retrieve redirection url.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionPaymentPageService
     */
    private $transactionPaymentPageService;

    /**
     * The charge attempt API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\ChargeAttemptService
     */
    private $chargeAttemptService;

    /**
     * Returns the transaction API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TransactionService
     */
    protected function getTransactionService()
    {
        if ($this->transactionService === null) {
            $this->transactionService = new \PostFinanceCheckout\Sdk\Service\TransactionService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }
        return $this->transactionService;
    }
    
    /**
     * Returns the transaction iframe API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TransactionIframeService
     */
    protected function getTransactionIframeService()
    {
        if ($this->transactionIframeService === null) {
            $this->transactionIframeService = new \PostFinanceCheckout\Sdk\Service\TransactionIframeService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }
        return $this->transactionIframeService;
    }
    
    /**
     * Returns the transaction API payment page service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\TransactionPaymentPageService
     */
    protected function getTransactionPaymentPageService()
    {
        if ($this->transactionPaymentPageService === null) {
            $this->transactionPaymentPageService = new \PostFinanceCheckout\Sdk\Service\TransactionPaymentPageService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }
        return $this->transactionPaymentPageService;
    }

    /**
     * Returns the charge attempt API service.
     *
     * @return \PostFinanceCheckout\Sdk\Service\ChargeAttemptService
     */
    protected function getChargeAttemptService()
    {
        if ($this->chargeAttemptService === null) {
            $this->chargeAttemptService = new \PostFinanceCheckout\Sdk\Service\ChargeAttemptService(
                PostFinanceCheckoutHelper::getApiClient()
            );
        }
        return $this->chargeAttemptService;
    }

    /**
     * Wait for the transaction to be in one of the given states.
     *
     * @param Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = microtime(true);
        while (true) {
            $transactionInfo = PostFinanceCheckoutModelTransactioninfo::loadByOrderId($order->id);
            if (in_array($transactionInfo->getState(), $states)) {
                return true;
            }

            if (microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }
            sleep(2);
        }
    }

    /**
     * Returns the URL to PostFinance Checkout's JavaScript library that is necessary to display the payment form.
     *
     * @param Cart $cart
     * @return string
     */
    public function getJavascriptUrl(Cart $cart)
    {
        $transaction = $this->getTransactionFromCart($cart);
        $js = $this->getTransactionIframeService()->javascriptUrl(
            $transaction->getLinkedSpaceId(),
            $transaction->getId()
        );
        
        return $js . "&className=postfinancecheckoutIFrameCheckoutHandler";
    }

    /**
     * Returns the URL to PostFinance Checkout's payment page.
     *
     * @param Cart $cart
     * @return string
     */
    public function getPaymentPageUrl($spaceId, $transactionId)
    {
        return $this->getTransactionPaymentPageService()->paymentPageUrl($spaceId, $transactionId);
    }

    /**
     * Returns the transaction with the given id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->getTransactionService()->read($spaceId, $transactionId);
    }

    /**
     * Returns the last failed charge attempt of the transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\ChargeAttempt
     */
    public function getFailedChargeAttempt($spaceId, $transactionId)
    {
        $chargeAttemptService = $this->getChargeAttemptService();
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transactionId),
                $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\ChargeAttemptState::FAILED)
            )
        );
        $query->setFilter($filter);
        $query->setOrderBys(array(
            $this->createEntityOrderBy('failedOn')
        ));
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Updates the line items of the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \PostFinanceCheckout\Sdk\Model\LineItem[] $lineItems
     * @return \PostFinanceCheckout\Sdk\Model\TransactionLineItemVersion
     */
    public function updateLineItems($spaceId, $transactionId, $lineItems)
    {
        $updateRequest = new \PostFinanceCheckout\Sdk\Model\TransactionLineItemUpdateRequest();
        $updateRequest->setTransactionId($transactionId);
        $updateRequest->setNewLineItems($lineItems);
        return $this->getTransactionService()->updateTransactionLineItems($spaceId, $updateRequest);
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param Order $order
     * @return PostFinanceCheckoutModelTransactioninfo
     */
    public function updateTransactionInfo(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $order)
    {
        $info = PostFinanceCheckoutModelTransactioninfo::loadByTransaction(
            $transaction->getLinkedSpaceId(),
            $transaction->getId()
        );
        $info->setTransactionId($transaction->getId());
        $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
        $info->setOrderId($order->id);
        $info->setState($transaction->getState());
        $info->setSpaceId($transaction->getLinkedSpaceId());
        $info->setSpaceViewId($transaction->getSpaceViewId());
        $info->setLanguage($transaction->getLanguage());
        $info->setCurrency($transaction->getCurrency());
        $info->setConnectorId(
            $transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getConnector() : null
        );
        $info->setPaymentMethodId(
            $transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getPaymentMethod() : null
        );
        $info->setImage($this->getResourcePath($this->getPaymentMethodImage($transaction, $order)));
        $info->setImageBase($this->getResourceBase($this->getPaymentMethodImage($transaction, $order)));
        $info->setLabels($this->getTransactionLabels($transaction));
        if ($transaction->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED ||
            $transaction->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE) {
            $failedChargeAttempt = $this->getFailedChargeAttempt($transaction->getLinkedSpaceId(), $transaction->getId());
            if ($failedChargeAttempt != null && $failedChargeAttempt->getFailureReason() != null) {
                $info->setFailureReason($failedChargeAttempt->getFailureReason()
                    ->getDescription());
            } elseif ($transaction->getFailureReason() != null) {
                $info->setFailureReason($transaction->getFailureReason()
                    ->getDescription());
            }
            $info->setUserFailureMessage($transaction->getUserFailureMessage());
        }
        $info->save();
        return $info;
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(\PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }
            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @return \PostFinanceCheckout\Sdk\Model\ChargeAttempt
     */
    protected function getChargeAttempt(\PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        $chargeAttemptService = $this->getChargeAttemptService();
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transaction->getId()),
                $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\ChargeAttemptState::SUCCESSFUL)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($transaction->getLinkedSpaceId(), $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Returns the payment method's image.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param Order $order
     * @return string
     */
    protected function getPaymentMethodImage(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() == null) {
            $moduleName = $order->module;
            if ($moduleName == "postfinancecheckout") {
                $id = PostFinanceCheckoutHelper::getOrderMeta($order, 'postFinanceCheckoutMethodId');
                $methodConfiguration = new PostFinanceCheckoutModelMethodconfiguration($id);
                return PostFinanceCheckoutHelper::getResourceUrl(
                    $methodConfiguration->getImageBase(),
                    $methodConfiguration->getImage()
                );
            }
            return null;
        }
        if ($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null) {
            return $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getResolvedImageUrl();
        }
        return null;
    }

    /**
     * Returns the payment methods that can be used with the current cart.
     *
     * @param Cart $cart
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Cart $cart)
    {
        $currentCartId = $cart->id;

        if (! isset(self::$possiblePaymentMethodCache[$currentCartId]) ||
            self::$possiblePaymentMethodCache[$currentCartId] == null) {
            $transaction = $this->getTransactionFromCart($cart);
            try {
                $paymentMethods = $this->getTransactionService()->fetchPaymentMethods(
                    $transaction->getLinkedSpaceId(),
                    $transaction->getId(),
                    'iframe'
                );
            } catch (\PostFinanceCheckout\Sdk\ApiException $e) {
                self::$possiblePaymentMethodCache[$currentCartId] = array();
                throw $e;
            } catch (PostFinanceCheckoutExceptionInvalidtransactionamount $e) {
                self::$possiblePaymentMethodCache[$currentCartId] = array();
                throw $e;
            }
            $methodConfigurationService = PostFinanceCheckoutServiceMethodconfiguration::instance();
            foreach ($paymentMethods as $paymentMethod) {
                $methodConfigurationService->updateData($paymentMethod);
            }
            self::$possiblePaymentMethodCache[$currentCartId] = $paymentMethods;
        }
        return self::$possiblePaymentMethodCache[$currentCartId];
    }

    public function checkTransactionPending(Cart $cart)
    {
        $ids = PostFinanceCheckoutHelper::getCartMeta($cart, 'mappingIds');
        $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);
        if ($transaction->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::PENDING) {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'The transaction timed out, please try again.',
                    'transaction'
                )
            );
        }
    }

    /**
     * Update the transaction with the given orders data.
     * The $dataSource is for the address and id information for the transaction.
     * The $orders are use to compile all lineItems, this array needs to include the $dataSource order
     *
     * @param Order $dataSource
     * @param Order[] $orders
     * @param int $methodConfigurationId
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function confirmTransaction(Order $dataSource, array $orders, $methodConfigurationId)
    {
        $last = new Exception('Unexpected Error');
        for ($i = 0; $i < 5; $i ++) {
            try {
                $ids = PostFinanceCheckoutHelper::getOrderMeta($dataSource, 'mappingIds');
                $spaceId = $ids['spaceId'];
                $transaction = $this->getTransactionService()->read($ids['spaceId'], $ids['transactionId']);

                if ($transaction->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::PENDING) {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'The checkout expired, please try again.',
                            'transaction'
                        )
                    );
                }
                $pendingTransaction = new \PostFinanceCheckout\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleOrderTransactionData($dataSource, $orders, $pendingTransaction);
                $pendingTransaction->setAllowedPaymentMethodConfigurations(array($methodConfigurationId));
                $result = $this->getTransactionService()->confirm($spaceId, $pendingTransaction);
                PostFinanceCheckoutHelper::updateOrderMeta(
                    $dataSource,
                    'mappingIds',
                    array(
                        'spaceId' => $result->getLinkedSpaceId(),
                        'transactionId' => $result->getId()
                    )
                );
                return $result;
            } catch (\PostFinanceCheckout\Sdk\VersioningException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    /**
     * Assemble the transaction data for the given orders.
     *
     * @param Order $dataSource
     * @param Order[] $orders
     * @param \PostFinanceCheckout\Sdk\Model\TransactionPending $transaction
     */
    protected function assembleOrderTransactionData(
        Order $dataSource,
        array $orders,
        \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction
    ) {
        $transaction->setCurrency(PostFinanceCheckoutHelper::convertCurrencyIdToCode($dataSource->id_currency));
        $transaction->setBillingAddress($this->getAddress($dataSource->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($dataSource->id_address_delivery));
        $transaction->setCustomerEmailAddress($this->getEmailAddressForCustomerId($dataSource->id_customer));
        $transaction->setCustomerId($dataSource->id_customer);
        $transaction->setLanguage(PostFinanceCheckoutHelper::convertLanguageIdToIETF($dataSource->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($dataSource->id_carrier), 200)
        );

        $transaction->setLineItems(PostFinanceCheckoutServiceLineitem::instance()->getItemsFromOrders($orders));

        $orderComment = $this->getOrderComment($orders);
        if (! empty($orderComment)) {
            $transaction->setMetaData(array(
                'orderComment' => $orderComment
            ));
        }

        $transaction->setMerchantReference($dataSource->id);
        $transaction->setInvoiceMerchantReference($this->fixLength($this->removeNonAscii($dataSource->reference), 100));

        $transaction->setSuccessUrl(
            Context::getContext()->link->getModuleLink(
                'postfinancecheckout',
                'return',
                array(
                    'order_id' => $dataSource->id,
                    'secret' => PostFinanceCheckoutHelper::computeOrderSecret($dataSource),
                    'action' => 'success',
                    'utm_nooverride' => '1'
                ),
                true
            )
        );

        $transaction->setFailedUrl(
            Context::getContext()->link->getModuleLink(
                'postfinancecheckout',
                'return',
                array(
                    'order_id' => $dataSource->id,
                    'secret' => PostFinanceCheckoutHelper::computeOrderSecret($dataSource),
                    'action' => 'failure',
                    'utm_nooverride' => '1'
                ),
                true
            )
        );
    }

    /**
     * Returns the transaction for the given cart.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Cart $cart
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function getTransactionFromCart(Cart $cart)
    {
        $currentCartId = $cart->id;
        $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, $cart->id_shop_group, $cart->id_shop);
        if (! isset(self::$transactionCache[$currentCartId]) || self::$transactionCache[$currentCartId] == null) {
            $ids = PostFinanceCheckoutHelper::getCartMeta($cart, 'mappingIds');
            if (empty($ids) || ! isset($ids['spaceId']) || $ids['spaceId'] != $spaceId) {
                $transaction = $this->createTransactionFromCart($cart);
            } else {
                $transaction = $this->loadAndUpdateTransactionFromCart($cart);
            }
            self::$transactionCache[$currentCartId] = $transaction;
        }
        return self::$transactionCache[$currentCartId];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Cart $cart
     * @return \PostFinanceCheckout\Sdk\Model\TransactionCreate
     * @throws \PostFinanceCheckoutExceptionInvalidtransactionamount
     */
    protected function createTransactionFromCart(Cart $cart)
    {
        $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, $cart->id_shop_group, $cart->id_shop);
        $createTransaction = new \PostFinanceCheckout\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(\PostFinanceCheckout\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $createTransaction->setDeviceSessionIdentifier(Context::getContext()->cookie->pfc_device_id);

        $spaceViewId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_VIEW_ID, null, null, $cart->id_shop);
        if (! empty($spaceViewId)) {
            $createTransaction->setSpaceViewId($spaceViewId);
        }
        $this->assembleCartTransactionData($cart, $createTransaction);
        $transaction = $this->getTransactionService()->create($spaceId, $createTransaction);
        PostFinanceCheckoutHelper::updateCartMeta(
            $cart,
            'mappingIds',
            array(
                'spaceId' => $transaction->getLinkedSpaceId(),
                'transactionId' => $transaction->getId()
            )
        );
        return $transaction;
    }

    /**
     * Loads the transaction for the given cart and updates it if necessary.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param Cart $cart
     * @return \PostFinanceCheckout\Sdk\Model\TransactionPending
     */
    protected function loadAndUpdateTransactionFromCart(Cart $cart)
    {
        $last = new Exception('Unexpected Error');
        for ($i = 0; $i < 5; $i ++) {
            try {
                $ids = PostFinanceCheckoutHelper::getCartMeta($cart, 'mappingIds');
                $transaction = $this->getTransaction($ids['spaceId'], $ids['transactionId']);
                $customerId = $transaction->getCustomerId();
                if ($transaction->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::PENDING ||
                    (! empty($customerId) && $customerId != $cart->id_customer)) {
                    return $this->createTransactionFromCart($cart);
                }
                $pendingTransaction = new \PostFinanceCheckout\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleCartTransactionData($cart, $pendingTransaction);
                return $this->getTransactionService()->update($ids['spaceId'], $pendingTransaction);
            } catch (\PostFinanceCheckout\Sdk\VersioningException $e) {
                $last = $e;
            }
        }
        throw $last;
    }

    /**
     * Assemble the transaction data for the given quote.
     *
     * @param \Cart                                                       $cart
     * @param \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction
     *
     * @return \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending
     * @throws \PostFinanceCheckoutExceptionInvalidtransactionamount
     */
    protected function assembleCartTransactionData(
        Cart $cart,
        \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction
    ) {
        $transaction->setCurrency(PostFinanceCheckoutHelper::convertCurrencyIdToCode($cart->id_currency));
        $transaction->setBillingAddress($this->getAddress($cart->id_address_invoice));
        $transaction->setShippingAddress($this->getAddress($cart->id_address_delivery));
        if ($cart->id_customer != 0) {
            $transaction->setCustomerEmailAddress($this->getEmailAddressForCustomerId($cart->id_customer));
            $transaction->setCustomerId($cart->id_customer);
        }
        $transaction->setLanguage(PostFinanceCheckoutHelper::convertLanguageIdToIETF($cart->id_lang));
        $transaction->setShippingMethod(
            $this->fixLength($this->getShippingMethodNameForCarrierId($cart->id_carrier), 200)
        );

        $transaction->setLineItems(PostFinanceCheckoutServiceLineitem::instance()->getItemsFromCart($cart));

        $transaction->setAllowedPaymentMethodConfigurations(array());
        return $transaction;
    }

    /**
     * Returns the billing address of the current session.
     *
     * @param int $addressId
     * @return \PostFinanceCheckout\Sdk\Model\AddressCreate
     */
    protected function getAddress($addressId)
    {
        $prestaAddress = new Address($addressId);

        $address = new \PostFinanceCheckout\Sdk\Model\AddressCreate();
        $address->setCity($this->fixLength($prestaAddress->city, 100));
        $address->setFamilyName($this->fixLength($prestaAddress->lastname, 100));
        $address->setGivenName($this->fixLength($prestaAddress->firstname, 100));
        $address->setOrganizationName($this->fixLength($prestaAddress->company, 100));
        $address->setPhoneNumber($prestaAddress->phone);

        if ($prestaAddress->id_country != null) {
            $country = new Country((int) $prestaAddress->id_country);
            $address->setCountry($country->iso_code);
        }
        if ($prestaAddress->id_state != null) {
            $state = new State((int) $prestaAddress->id_state);
            $code = $state->iso_code;
            if (! empty($code)) {
                $address->setPostalState($code);
            }
        }
        $address->setPostCode($this->fixLength($prestaAddress->postcode, 40));
        $address->setStreet($this->fixLength(trim($prestaAddress->address1 . "\n" . $prestaAddress->address2), 300));
        $address->setEmailAddress($this->getEmailAddressForCustomerId($prestaAddress->id_customer));
        $address->setDateOfBirth($this->getDateOfBirthForCustomerId($prestaAddress->id_customer));
        $address->setGender($this->getGenderForCustomerId($prestaAddress->id_customer));
        return $address;
    }

    /**
     * Returns the current customer's email address.
     *
     * @param
     *            $id
     * @return string
     */
    protected function getEmailAddressForCustomerId($id)
    {
        $customer = new Customer($id);
        return $customer->email;
    }

    /**
     * Returns the current customer's date of birth
     *
     * @param
     *            $id
     * @return string
     */
    protected function getDateOfBirthForCustomerId($id)
    {
        $customer = new Customer($id);
        if (! empty($customer->birthday) && $customer->birthday != '0000-00-00' &&
            Validate::isBirthDate($customer->birthday)) {
            return DateTime::createFromFormat("Y-m-d", $customer->birthday);
        }
        return null;
    }

    /**
     * Returns the current customer's gender.
     *
     * @param
     *            $id
     * @return string
     */
    protected function getGenderForCustomerId($id)
    {
        $customer = new Customer($id);
        $gender = new Gender($customer->id_gender);
        if (! Validate::isLoadedObject($gender)) {
            return null;
        }
        if ($gender->type == '0') {
            return \PostFinanceCheckout\Sdk\Model\Gender::MALE;
        } elseif ($gender->type == '1') {
            return \PostFinanceCheckout\Sdk\Model\Gender::FEMALE;
        }
        return null;
    }

    /**
     * Returns the shipping name
     *
     * @param int $carrierId
     * @return string
     */
    protected function getShippingMethodNameForCarrierId($carrierId)
    {
        $carrier = new Carrier($carrierId);
        return $carrier->name;
    }

    /**
     *
     * @param Order[] $orders
     */
    private function getOrderComment(array $orders)
    {
        $messages = array();
        foreach ($orders as $order) {
            $messageCollection = new PrestaShopCollection('Message');
            $messageCollection->where('id_order', '=', (int) $order->id);
            foreach ($messageCollection->getResults() as $orderMessage) {
                $messages[] = $orderMessage->message;
            }
        }
        $unique = array_unique($messages);
        $single = implode("\n", $unique);
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', strip_tags($single));
        return $this->fixLength($cleaned, 512);
    }
}
