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

class PostFinanceCheckout_Model_RefundJob extends ObjectModel
{

    const STATE_CREATED = 'created';

    const STATE_SENT = 'sent';

    const STATE_PENDING = 'pending';
    
    const STATE_APPLY = 'apply';

    const STATE_SUCCESS = 'success';

    const STATE_FAILURE = 'failure';

    public $id_refund_job;

    public $refund_id;

    public $external_id;

    public $state;

    public $space_id;

    public $transaction_id;

    public $order_id;
    
    public $refund_parameters;
    
    public $apply_tries = 0;

    public $failure_reason;

    public $date_add;

    public $date_upd;

    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pfc_refund_job',
        'primary' => 'id_refund_job',
        'fields' => array(
            'refund_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
            ),
            'external_id' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
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
            'transaction_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'order_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'refund_parameters' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything'
            ),
            'apply_tries' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt'
            ),
            'failure_reason' => array(
                'type' => self::TYPE_STRING,
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

    public function getRefundId()
    {
        return $this->refund_id;
    }

    public function setExternalId($id)
    {
        $this->external_id = $id;
    }

    public function getExternalId()
    {
        return $this->external_id;
    }

    public function setRefundId($id)
    {
        $this->refund_id = $id;
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

    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    public function setTransactionId($id)
    {
        $this->transaction_id = $id;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function setOrderId($id)
    {
        $this->order_id = $id;
    }
    
    public function setRefundParameters($params)
    {
        $this->refund_parameters = base64_encode(serialize($params));
    }
    
    public function getRefundParameters()
    {
        $decoded =  base64_decode($this->refund_parameters, true);
        if($decoded === false){
            $decoded = $this->refund_parameters;
        }
        return unserialize($decoded);
    }
    
    public function getApplyTries()
    {
        return $this->apply_tries;
    }
    
    public function increaseApplyTries()
    {
        $this->apply_tries++;
    }

    public function getFailureReason()
    {
        $decoded =  base64_decode($this->failure_reason, true);
        if($decoded === false){
            $decoded = $this->failure_reason;
        }
        return unserialize($decoded);
    }

    public function setFailureReason($failureReason)
    {
        $this->failure_reason = base64_encode(serialize($failureReason));
    }

    /**
     *
     * @param int $spaceId
     * @param int $refundId
     * @return PostFinanceCheckout_Model_RefundJob
     */
    public static function loadByRefundId($spaceId, $refundId)
    {
        $refundJobs = new PrestaShopCollection('PostFinanceCheckout_Model_RefundJob');
        $refundJobs->where('space_id', '=', $spaceId);
        $refundJobs->where('refund_id', '=', $refundId);
        $result = $refundJobs->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckout_Model_RefundJob();
        }
        return $result;
    }
    
    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return PostFinanceCheckout_Model_RefundJob[]
     */
    public static function loadByTransactionId($spaceId, $transactionId)
    {
        $refundJobs = new PrestaShopCollection('PostFinanceCheckout_Model_RefundJob');
        $refundJobs->where('space_id', '=', $spaceId);
        $refundJobs->where('transaction_id', '=', $transactionId);
        $result = $refundJobs->getResults();
        if (!$result) {
            return array();
        }
        return $result;
    }

    /**
     *
     * @param int $spaceId
     * @param int $externalId
     * @return PostFinanceCheckout_Model_RefundJob
     */
    public static function loadByExternalId($spaceId, $externalId)
    {
        $refundJobs = new PrestaShopCollection('PostFinanceCheckout_Model_RefundJob');
        $refundJobs->where('space_id', '=', $spaceId);
        $refundJobs->where('external_id', '=', $externalId);
        $result = $refundJobs->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckout_Model_RefundJob();
        }
        return $result;
    }

    public static function isRefundRunningForTransaction($spaceId, $transactionId)
    {
        $result = DB::getInstance()->getValue(
            'SELECT id_refund_job FROM ' . _DB_PREFIX_ . 'pfc_refund_job WHERE space_id = "' .
                 pSQL($spaceId) . '" AND transaction_id="' . pSQL($transactionId) .
                 '" AND state != "' . pSQL(self::STATE_FAILURE) . '" AND state !="' .
            psql(self::STATE_SUCCESS) . '"',
            false
        );
        if ($result !== false) {
            return true;
        }
        return false;
    }

    public static function loadRunningRefundForTransaction($spaceId, $transactionId)
    {
        $refundJobs = new PrestaShopCollection('PostFinanceCheckout_Model_RefundJob');
        $refundJobs->where('space_id', '=', $spaceId);
        $refundJobs->where('transaction_id', '=', $transactionId);
        $refundJobs->where('state', '!=', self::STATE_FAILURE);
        $refundJobs->where('state', '!=', self::STATE_SUCCESS);
        $result = $refundJobs->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckout_Model_RefundJob();
        }
        return $result;
    }

    public static function loadNotSentJobIds()
    {
        $time = new DateTime();
        $time->sub(new DateInterval('PT10M'));
        $result = DB::getInstance()->query(
            'SELECT id_refund_job FROM ' . _DB_PREFIX_ . 'pfc_refund_job WHERE state = "' .
                 pSQL(self::STATE_CREATED) . '" AND date_upd < "' .
            pSQL($time->format('Y-m-d H:i:s')) . '"',
            false
        );
        $ids = array();
        while ($row = DB::getInstance()->nextRow($result)) {
            $ids[] = $row['id_refund_job'];
        }
        return $ids;
    }
    
    public static function loadNotAppliedJobIds()
    {
        $time = new DateTime();
        $result = DB::getInstance()->query(
            'SELECT id_refund_job FROM ' . _DB_PREFIX_ . 'pfc_refund_job WHERE state = "' .
            pSQL(self::STATE_APPLY) . '" AND date_upd < "' .
            pSQL($time->format('Y-m-d H:i:s')) . '"',
            false
        );
        $ids = array();
        while ($row = DB::getInstance()->nextRow($result)) {
            $ids[] = $row['id_refund_job'];
        }
        return $ids;
    }
}
