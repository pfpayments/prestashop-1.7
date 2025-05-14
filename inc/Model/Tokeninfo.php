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

class PostFinanceCheckoutModelTokeninfo extends ObjectModel
{
    public $id_token_info;

    public $token_id;

    public $state;

    public $space_id;

    public $name;

    public $customer_id;

    public $payment_method_id;

    public $connector_id;

    public $date_add;

    public $date_upd;

    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pfc_token_info',
        'primary' => 'id_token_info',
        'fields' => array(
            'token_id' => array(
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
            'name' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'customer_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
            ),
            'payment_method_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'connector_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
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

    public function getTokenId()
    {
        return $this->token_id;
    }

    public function setTokenId($id)
    {
        $this->token_id = $id;
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

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getCustomerId()
    {
        return $this->customer_id;
    }

    public function setCustomerId($id)
    {
        $this->customer_id = $id;
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

    /**
     *
     * @param int $spaceId
     * @param int $tokenId
     * @return PostFinanceCheckoutModelTokeninfo
     */
    public static function loadByToken($spaceId, $tokenId)
    {
        $tokenInfos = new PrestaShopCollection('PostFinanceCheckoutModelTokeninfo');
        $tokenInfos->where('space_id', '=', $spaceId);
        $tokenInfos->where('token_id', '=', $tokenId);
        $result = $tokenInfos->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelTokeninfo();
        }
        return $result;
    }
}
