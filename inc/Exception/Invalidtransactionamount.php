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
 * This exception indicating an error with the transaction amount
 */
class PostFinanceCheckoutExceptionInvalidtransactionamount extends Exception
{
    private $itemTotal;

    private $orderTotal;

    public function __construct($itemTotal, $orderTotal)
    {
        parent::__construct("The item total '" . $itemTotal . "' does not match the order total '" . $orderTotal . "'.");
        $this->itemTotal = $itemTotal;
        $this->orderTotal = $orderTotal;
    }

    public function getItemTotal()
    {
        return $this->itemTotal;
    }

    public function getOrderTotal()
    {
        return $this->orderTotal;
    }
}
