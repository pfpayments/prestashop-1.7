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

class PostFinanceCheckoutModelCompletionjob extends ObjectModel
{
    const STATE_CREATED = 'created';

    const STATE_ITEMS_UPDATED = 'item';

    const STATE_SENT = 'sent';

    const STATE_SUCCESS = 'success';

    const STATE_FAILURE = 'failure';

    public $id_completion_job;

    public $completion_id;

    public $state;

    public $space_id;

    public $transaction_id;

    public $order_id;

    public $failure_reason;

    public $date_add;

    public $date_upd;

    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'pfc_completion_job',
        'primary' => 'id_completion_job',
        'fields' => array(
            'completion_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything'
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

    public function getCompletionId()
    {
        return $this->completion_id;
    }

    public function setCompletionId($id)
    {
        $this->completion_id = $id;
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

    /**
     *
     * @param int $spaceId
     * @param int $completionId
     * @return PostFinanceCheckoutModelCompletionjob
     */
    public static function loadByCompletionId($spaceId, $completionId)
    {
        $completionJobs = new PrestaShopCollection('PostFinanceCheckoutModelCompletionjob');
        $completionJobs->where('space_id', '=', $spaceId);
        $completionJobs->where('completion_id', '=', $completionId);
        $result = $completionJobs->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelCompletionjob();
        }
        return $result;
    }

    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return PostFinanceCheckoutModelCompletionjob[]
     */
    public static function loadByTransactionId($spaceId, $transactionId)
    {
        $completionJobs = new PrestaShopCollection('PostFinanceCheckoutModelCompletionjob');
        $completionJobs->where('space_id', '=', $spaceId);
        $completionJobs->where('transaction_id', '=', $transactionId);
        $result = $completionJobs->getResults();
        if (! $result) {
            return array();
        }
        return $result;
    }

    public static function isCompletionRunningForTransaction($spaceId, $transactionId)
    {
        $result = DB::getInstance()->getValue(
            'SELECT id_completion_job FROM ' . _DB_PREFIX_ . 'pfc_completion_job WHERE space_id = "' . (int) $spaceId .
            '" AND transaction_id="' . (int) $transactionId . '" AND state != "' . pSQL(self::STATE_SUCCESS) .
            '" AND state != "' . pSQL(self::STATE_FAILURE) . '"',
            false
        );

        if ($result !== false) {
            return true;
        }
        return false;
    }

    public static function loadRunningCompletionForTransaction($spaceId, $transactionId)
    {
        $completionJobs = new PrestaShopCollection('PostFinanceCheckoutModelCompletionjob');
        $completionJobs->where('space_id', '=', $spaceId);
        $completionJobs->where('transaction_id', '=', $transactionId);
        $completionJobs->where('state', '!=', self::STATE_SUCCESS);
        $completionJobs->where('state', '!=', self::STATE_FAILURE);
        $result = $completionJobs->getFirst();
        if ($result === false) {
            $result = new PostFinanceCheckoutModelCompletionjob();
        }
        return $result;
    }

    public static function loadNotSentJobIds()
    {
        $time = new DateTime();
        $time->sub(new DateInterval('PT10M'));
        $result = DB::getInstance()->query(
            'SELECT id_completion_job FROM ' . _DB_PREFIX_ . 'pfc_completion_job WHERE (state = "' .
            pSQL(self::STATE_CREATED) . '" OR state = "' . pSQL(self::STATE_ITEMS_UPDATED) . '") AND date_upd < "' .
            pSQL($time->format('Y-m-d H:i:s')) . '"',
            false
        );
        $ids = array();
        while ($row = DB::getInstance()->nextRow($result)) {
            $ids[] = $row['id_completion_job'];
        }
        return $ids;
    }
}
