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

class PostFinanceCheckoutModelTransactioninfo extends ObjectModel
{
    public $id_transaction_info;

    public $transaction_id;

    public $state;

    public $space_id;

    public $space_view_id;

    public $language;

    public $currency;

    public $authorization_amount;

    public $image;

    public $image_base;

    public $labels;

    public $payment_method_id;

    public $connector_id;

    public $order_id;

    public $failure_reason;

    public $locked_at;

    public $date_add;

    public $date_upd;

    public $user_failure_message;

    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pfc_transaction_info',
        'primary' => 'id_transaction_info',
        'fields' => array(
            'transaction_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'state' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'space_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'space_view_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
            ),
            'language' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'currency' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'authorization_amount' => array(
                'type' => self::TYPE_FLOAT,
                'validate' => 'isAnything'
            ),
            'image' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
                'size' => 2047
            ),
            'image_base' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
                'size' => 2047
            ),
            'user_failure_message' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
                'size' => 2047
            ),
            'labels' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything'
            ),
            'payment_method_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
            ),
            'connector_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
            ),
            'order_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'failure_reason' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything'
            ),
            'locked_at' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isAnything',
                'copy_post' => false
            ),
            'date_add' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'copy_post' => false
            ),
            'date_upd' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'copy_post' => false
            )
        )
    );

    public function getId()
    {
        return $this->id;
    }

    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    public function setTransactionId($id)
    {
        $this->transaction_id = $id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function getSpaceId()
    {
        return $this->space_id;
    }

    public function setSpaceId($id)
    {
        $this->space_id = $id;
    }

    public function getSpaceViewId()
    {
        return $this->space_view_id;
    }

    public function setSpaceViewId($id)
    {
        $this->space_view_id = $id;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public function getAuthorizationAmount()
    {
        return $this->authorization_amount;
    }

    public function setAuthorizationAmount($amount)
    {
        $this->authorization_amount = $amount;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }

    public function getImageBase()
    {
        return $this->image_base;
    }

    public function setImageBase($imageBase)
    {
        $this->image_base = $imageBase;
    }

    public function getLabels()
    {
        $decoded = PostFinanceCheckoutTools::base64Decode($this->labels, true);
        if ($decoded === false) {
            $decoded = $this->labels;
        }
        return unserialize($decoded);
    }

    public function setLabels(array $labels)
    {
        $this->labels = PostFinanceCheckoutTools::base64Encode(serialize($labels));
    }

    public function getPaymentMethodId()
    {
        return $this->payment_method_id;
    }

    public function setPaymentMethodId($id)
    {
        $this->payment_method_id = $id;
    }

    public function getConnectorId()
    {
        return $this->connector_id;
    }

    public function setConnectorId($id)
    {
        $this->connector_id = $id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = $id;
    }

    public function getFailureReason()
    {
        $decoded = PostFinanceCheckoutTools::base64Decode($this->failure_reason, true);
        if ($decoded === false) {
            $decoded = $this->failure_reason;
        }
        return unserialize($decoded);
    }

    public function setFailureReason($failureReason)
    {
        $this->failure_reason = PostFinanceCheckoutTools::base64Encode(serialize($failureReason));
    }

    public function getUserFailureMessage()
    {
        return $this->user_failure_message;
    }

    public function setUserFailureMessage($message)
    {
        $this->user_failure_message = $message;
    }

    /**
     *
     * @param int $orderId
     * @return PostFinanceCheckoutModelTransactioninfo
     */
    public static function loadByOrderId($orderId)
    {
        $transactionInfos = new PrestaShopCollection('PostFinanceCheckoutModelTransactioninfo');
        $transactionInfos->where('order_id', '=', $orderId);
        $result = $transactionInfos->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelTransactioninfo();
        }
        return $result;
    }

    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return PostFinanceCheckoutModelTransactioninfo
     */
    public static function loadByTransaction($spaceId, $transactionId)
    {
        $transactionInfos = new PrestaShopCollection('PostFinanceCheckoutModelTransactioninfo');
        $transactionInfos->where('space_id', '=', $spaceId);
        $transactionInfos->where('transaction_id', '=', $transactionId);
        $result = $transactionInfos->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelTransactioninfo();
        }
        return $result;
    }
}
