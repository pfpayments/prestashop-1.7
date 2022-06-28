<?php
/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckoutModelMethodconfiguration extends ObjectModel
{
    const STATE_ACTIVE = 'active';

    const STATE_INACTIVE = 'inactive';

    const STATE_HIDDEN = 'hidden';

    public $id_method_configuration;

    public $id_shop;

    public $state;

    public $space_id;

    public $configuration_id;

    public $configuration_name;

    public $title;

    public $description;

    public $image;

    public $image_base;

    public $sort_order;

    public $date_add;

    public $date_upd;

    public $active = 1;

    public $show_description = 1;

    public $show_image = 1;

    public $fee_rate = 0;

    public $fee_fixed = 0;

    public $fee_base = PostFinanceCheckoutBasemodule::TOTAL_MODE_BOTH_INC;

    public $fee_add_tax = 0;

    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pfc_method_configuration',
        'primary' => 'id_method_configuration',
        'multishop' => true,
        'fields' => array(
            'id_shop' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
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
            'configuration_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'configuration_name' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 150
            ),
            'title' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything'
            ),
            'description' => array(
                'type' => self::TYPE_STRING,
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
            'sort_order' => array(
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
            ),

            'active' => array(
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool'
            ),
            'show_description' => array(
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool'
            ),
            'show_image' => array(
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool'
            ),
            'fee_base' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true
            ),
            'fee_rate' => array(
                'type' => self::TYPE_FLOAT,
                'validate' => 'isFloat',
                'required' => true
            ),
            'fee_fixed' => array(
                'type' => self::TYPE_FLOAT,
                'validate' => 'isPrice',
                'required' => true
            ),
            'fee_add_tax' => array(
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool'
            )
        )
    );

    public function getId()
    {
        return $this->id;
    }

    public function getShopId()
    {
        return $this->id_shop;
    }

    public function setShopId($shopId)
    {
        return $this->id_shop = $shopId;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getSpaceId()
    {
        return $this->space_id;
    }

    public function setSpaceId($spaceId)
    {
        $this->space_id = $spaceId;
    }

    public function getConfigurationId()
    {
        return $this->configuration_id;
    }

    public function setConfigurationId($id)
    {
        $this->configuration_id = $id;
    }

    public function setConfigurationName($name)
    {
        $this->configuration_name = $name;
    }

    public function getConfigurationName()
    {
        return $this->configuration_name;
    }

    public function setTitle(array $title)
    {
        $this->title = PostFinanceCheckoutTools::base64Encode(serialize($title));
    }

    public function getTitle()
    {
        $decoded = PostFinanceCheckoutTools::base64Decode($this->title, true);
        if ($decoded === false) {
            $decoded = $this->title;
        }
        return unserialize($decoded);
    }

    public function setDescription(array $description)
    {
        $this->description = PostFinanceCheckoutTools::base64Encode(serialize($description));
    }

    public function getDescription()
    {
        $decoded = PostFinanceCheckoutTools::base64Decode($this->description, true);
        if ($decoded === false) {
            $decoded = $this->description;
        }
        return unserialize($decoded);
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

    public function getSortOrder()
    {
        return $this->sort_order;
    }

    public function setSortOrder($order)
    {
        $this->sort_order = $order;
    }

    public function isActive()
    {
        return $this->active;
    }

    public function setActive($bool)
    {
        $this->active = $bool;
    }

    public function isShowDescription()
    {
        return $this->show_description;
    }

    public function setShowDescription($bool)
    {
        $this->show_description = $bool;
    }

    public function isShowImage()
    {
        return $this->show_image;
    }

    public function setShowImage($bool)
    {
        $this->show_image = $bool;
    }

    public function getFeeFixed()
    {
        return $this->fee_fixed;
    }

    public function setFeeFixed($fee)
    {
        $this->fee_fixed = $fee;
    }

    public function getFeeRate()
    {
        return $this->fee_rate;
    }

    public function setFeeRate($rate)
    {
        $this->fee_rate = $rate;
    }

    public function getFeeBase()
    {
        return $this->fee_base;
    }

    public function setFeeBase($base)
    {
        $this->fee_base = $base;
    }

    public function isFeeAddTax()
    {
        return $this->fee_add_tax;
    }

    public function setFeeAddTax($bool)
    {
        $this->fee_add_tax = $bool;
    }

    /**
     *
     * @param int $id
     * @param int $shopId
     * @return PostFinanceCheckoutModelMethodconfiguration | false
     */
    public static function loadByIdWithChecks($id, $shopId)
    {
        $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, null, $shopId);
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        $collection->where('id_method_configuration', '=', $id);
        $collection->where('id_shop', '=', $shopId);
        $collection->where('space_id', '=', $spaceId);
        return $collection->getFirst();
    }

    /**
     *
     * @param int $spaceId
     * @param int $configurationId
     * @return PostFinanceCheckoutModelMethodconfiguration[]
     */
    public static function loadByConfiguration($spaceId, $configurationId)
    {
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        $collection->where('space_id', '=', $spaceId);
        $collection->where('configuration_id', '=', $configurationId);
        return $collection->getResults();
    }

    /**
     *
     * @param int $spaceId
     * @param int $configurationId
     * @param int $shopId
     * @return PostFinanceCheckoutModelMethodconfiguration
     */
    public static function loadByConfigurationAndShop($spaceId, $configurationId, $shopId)
    {
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        $collection->where('space_id', '=', $spaceId);
        $collection->where('configuration_id', '=', $configurationId);
        $collection->where('id_shop', '=', $shopId);
        $result = $collection->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelMethodconfiguration();
        }
        return $result;
    }

    /**
     *
     * @param int $shopId
     * @return PostFinanceCheckoutModelMethodconfiguration[]
     */
    public static function loadValidForShop($shopId)
    {
        $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, null, $shopId);
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        $collection->where('space_id', '=', $spaceId);
        $collection->where('id_shop', '=', $shopId);
        $collection->where('state', '=', self::STATE_ACTIVE);
        return $collection->getResults();
    }

    /**
     *
     * @param int $shopId
     * @return PostFinanceCheckoutModelMethodconfiguration
     */
    public static function loadActiveForShop($shopId)
    {
        $spaceId = Configuration::get(PostFinanceCheckoutBasemodule::CK_SPACE_ID, null, null, $shopId);
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        $collection->where('space_id', '=', $spaceId);
        $collection->where('id_shop', '=', $shopId);
        $collection->where('state', '=', self::STATE_ACTIVE);
        $collection->where('active', '=', true);
        return $collection->getResults();
    }

    /**
     *
     * @return PostFinanceCheckoutModelMethodconfiguration[]
     */
    public static function loadAll()
    {
        $collection = new PrestaShopCollection('PostFinanceCheckoutModelMethodconfiguration');
        return $collection->getResults();
    }
}
