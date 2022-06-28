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

/**
 * Webhook processor to handle transaction completion state transitions.
 */
class PostFinanceCheckoutBackendDefaultstrategy implements PostFinanceCheckoutBackendIstrategy
{
    const REFUND_TYPE_PARTIAL_REFUND = 'partial';

    const REFUND_TYPE_CANCEL_PRODUCT = 'cancel';

    public function validateAndParseData(Order $order, array $postData)
    {
        if (isset($postData['partialRefund'])) {
            return $this->validateDataPartialRefundType($order, $postData);
        }
        if (isset($postData['cancelProduct'])) {
            return $this->validateDataCancelProductType($order, $postData);
        }
        throw new Exception(
            PostFinanceCheckoutHelper::getModuleInstance()->l('The refund type is not supported.', 'defaultstrategy')
        );
    }

    public function simplifiedRefund(array $postData)
    {
        $cancelledItems = array();
        // count of items
        $quantity = 0;
        // cost of items
        $amount = 0;
        foreach ($postData["cancel_product"] as $key => $value) {
            if (strpos($key, 'quantity') !== false) {
                $quantity += $value;
            }
            if (strpos($key, 'amount') !== false) {
                $amount += $value;
            }
        }

        if ($amount >= 0) {
            return array(
                'refundType' => self::REFUND_TYPE_PARTIAL_REFUND,
                'orderDetailList' => [],
                'fullDetailList' => [],
                'shippingCostAmount' => $postData["cancel_product"]["shipping_amount"],
                'voucher' => isset($postData["cancel_product"]["voucher"]),
                'choosen' => false,
                'taxMethod' => false,
                'amount' => $amount,
                'languageId' => Context::getContext()->language->id,
                'reinjectQuantities' => isset($postData['reinjectQuantities']),
                'generateDiscountRefund' => isset($postData['generateDiscountRefund']),
                'postFinanceCheckoutOffline' => isset($postData['postfinancecheckout_offline'])
            );
        } else {
            if (! empty($quantity)) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Please enter a quantity to proceed with your refund.',
                        'defaultstrategy'
                    )
                );
            } else {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Please enter an amount to proceed with your refund.',
                        'defaultstrategy'
                    )
                );
            }
        }
    }

    private function validateDataPartialRefundType(Order $order, array $postData)
    {
        if (isset($postData['partialRefundProduct']) && ($refunds = $postData['partialRefundProduct']) &&
            is_array($refunds)) {
            $amount = 0;
            $order_detail_list = array();
            $full_quantity_list = array();
            $tax_method = $postData['TaxMethod'];
            foreach ($refunds as $id_order_detail => $amount_detail) {
                $quantity = $postData['partialRefundProductQuantity'];
                if (! $quantity[$id_order_detail]) {
                    continue;
                }
                $full_quantity_list[$id_order_detail] = (int) $quantity[$id_order_detail];
                $order_detail_list[$id_order_detail] = array(
                    'quantity' => (int) $quantity[$id_order_detail],
                    'id_order_detail' => (int) $id_order_detail
                );

                $order_detail = new OrderDetail((int) $id_order_detail);
                if (empty($amount_detail)) {
                    $order_detail_list[$id_order_detail]['unit_price'] = (! $tax_method ? $order_detail->unit_price_tax_excl : $order_detail->unit_price_tax_incl);
                    $order_detail_list[$id_order_detail]['amount'] = $order_detail->unit_price_tax_incl *
                        $order_detail_list[$id_order_detail]['quantity'];
                    $order_detail_list[$id_order_detail]['amount_modified'] = false;
                } else {
                    $order_detail_list[$id_order_detail]['amount'] = (float) str_replace(',', '.', $amount_detail);
                    $order_detail_list[$id_order_detail]['unit_price'] = $order_detail_list[$id_order_detail]['amount'] /
                        $order_detail_list[$id_order_detail]['quantity'];
                    $order_detail_list[$id_order_detail]['amount_modified'] = true;
                }
                $amount += $order_detail_list[$id_order_detail]['amount'];
                if (! $order->hasBeenDelivered() || ($order->hasBeenDelivered() && isset($postData['reinjectQuantities'])) && $order_detail_list[$id_order_detail]['quantity'] > 0) {
                    $product = new Product(
                        $order_detail->product_id,
                        false,
                        (int) Context::getContext()->language->id,
                        (int) $order_detail->id_shop
                    );
                    if (! ((Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management &&
                        $order_detail->id_warehouse != 0) || $order_detail->id_warehouse == 0)) {
                        throw new Exception(
                            sprintf(
                                PostFinanceCheckoutHelper::getModuleInstance()->l(
                                    'The product "%s" cannot be re-stocked.',
                                    'defaultstrategy'
                                ),
                                $product->name
                            )
                        );
                    }
                }
            }
            $shipping_cost_amount = (float) str_replace(',', '.', $postData['partialRefundShippingCost']) ? (float) str_replace(
                ',',
                '.',
                $postData['partialRefundShippingCost']
            ) : false;

            if ($amount == 0 && $shipping_cost_amount == 0) {
                if (! empty($refunds)) {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Please enter a quantity to proceed with your refund.',
                            'defaultstrategy'
                        )
                    );
                } else {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Please enter an amount to proceed with your refund.',
                            'defaultstrategy'
                        )
                    );
                }
            }
            $choosen = false;
            $voucher = 0;

            if (isset($postData['refund_voucher_off']) && (int) $postData['refund_voucher_off'] == 1) {
                // Refund vouchers
                $amount -= $voucher = (float) $postData['order_discount_price'];
            } elseif (isset($postData['refund_voucher_off']) && (int) $postData['refund_voucher_off'] == 2) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'This type of refund is not possible for this order.',
                        'defaultstrategy'
                    )
                );
            }

            if ($shipping_cost_amount > 0) {
                if (! $tax_method) {
                    $tax = new Tax();
                    $tax->rate = $order->carrier_tax_rate;
                    $tax_calculator = new TaxCalculator(array(
                        $tax
                    ));
                    $amount += $tax_calculator->addTaxes($shipping_cost_amount);
                } else {
                    $amount += $shipping_cost_amount;
                }
            }
            if ($amount >= 0) {
                return array(
                    'refundType' => self::REFUND_TYPE_PARTIAL_REFUND,
                    'orderDetailList' => $order_detail_list,
                    'fullDetailList' => $full_quantity_list,
                    'shippingCostAmount' => $shipping_cost_amount,
                    'voucher' => $voucher,
                    'choosen' => $choosen,
                    'taxMethod' => $tax_method,
                    'amount' => $amount,
                    'languageId' => Context::getContext()->language->id,
                    'reinjectQuantities' => isset($postData['reinjectQuantities']),
                    'generateDiscountRefund' => isset($postData['generateDiscountRefund']),
                    'postFinanceCheckoutOffline' => isset($postData['postfinancecheckout_offline'])
                );
            } else {
                if (! empty($refunds)) {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Please enter a quantity to proceed with your refund.',
                            'defaultstrategy'
                        )
                    );
                } else {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Please enter an amount to proceed with your refund.',
                            'defaultstrategy'
                        )
                    );
                }
            }
        } else {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'The partial refund data is incorrect.',
                    'defaultstrategy'
                )
            );
        }
    }

    private function validateDataCancelProductType(Order $order, array $postData)
    {
        if (! isset($postData['id_order_detail']) && ! isset($postData['id_customization'])) {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l('You must select a product.', 'defaultstrategy')
            );
        } elseif (! isset($postData['cancelQuantity']) && ! isset($postData['cancelCustomizationQuantity'])) {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l('You must enter a quantity.', 'defaultstrategy')
            );
        } else {
            $productList = isset($postData['id_order_detail']) ? $postData['id_order_detail'] : false;
            if ($productList) {
                $productList = array_map('intval', $productList);
            }

            $customizationList = isset($postData['id_customization']) ? $postData['id_customization'] : false;
            if ($customizationList) {
                $customizationList = array_map('intval', $customizationList);
            }

            $qtyList = isset($postData['cancelQuantity']) ? $postData['cancelQuantity'] : false;
            if ($qtyList) {
                $qtyList = array_map('intval', $qtyList);
            }

            $customizationQtyList = isset($postData['cancelCustomizationQuantity']) ? $postData['cancelCustomizationQuantity'] : false;
            if ($customizationQtyList) {
                $customizationQtyList = array_map('intval', $customizationQtyList);
            }

            $full_product_list = $productList;
            $full_quantity_list = $qtyList;

            if ($customizationList) {
                foreach ($customizationList as $key => $id_order_detail) {
                    $full_product_list[(int) $id_order_detail] = $id_order_detail;
                    if (isset($customizationQtyList[$key])) {
                        $full_quantity_list[(int) $id_order_detail] += $customizationQtyList[$key];
                    }
                }
            }

            if ($productList || $customizationList) {
                if ($productList) {
                    $id_cart = Cart::getCartIdByOrderId($order->id);
                    $customization_quantities = Customization::countQuantityByCart($id_cart);

                    foreach ($productList as $key => $id_order_detail) {
                        $qtyCancelProduct = abs($qtyList[$key]);
                        if (! $qtyCancelProduct) {
                            $this->errors[] = Tools::displayError(
                                'No quantity has been selected for this product.',
                                'defaultstrategy'
                            );
                        }

                        $order_detail = new OrderDetail($id_order_detail);
                        $customization_quantity = 0;
                        if (array_key_exists($order_detail->product_id, $customization_quantities) &&
                            array_key_exists(
                                $order_detail->product_attribute_id,
                                $customization_quantities[$order_detail->product_id]
                            )) {
                            $customization_quantity = (int) $customization_quantities[$order_detail->product_id][$order_detail->product_attribute_id];
                        }

                        if (($order_detail->product_quantity - $customization_quantity -
                            $order_detail->product_quantity_refunded - $order_detail->product_quantity_return) <
                            $qtyCancelProduct) {
                            $this->errors[] = Tools::displayError(
                                'An invalid quantity was selected for this product.',
                                'defaultstrategy'
                            );
                        }
                    }
                }

                if ($customizationList) {
                    $customization_quantities = Customization::retrieveQuantitiesFromIds(array_keys($customizationList));

                    foreach ($customizationList as $id_customization => $id_order_detail) {
                        $qtyCancelProduct = abs($customizationQtyList[$id_customization]);
                        $customization_quantity = $customization_quantities[$id_customization];

                        if (! $qtyCancelProduct) {
                            throw new Exception(
                                PostFinanceCheckoutHelper::getModuleInstance()->l(
                                    'No quantity has been selected for this product.',
                                    'defaultstrategy'
                                )
                            );
                        }

                        if ($qtyCancelProduct >
                            ($customization_quantity['quantity'] -
                            ($customization_quantity['quantity_refunded'] + $customization_quantity['quantity_returned']))) {
                            throw new Exception(
                                PostFinanceCheckoutHelper::getModuleInstance()->l(
                                    'An invalid quantity was selected for this product.',
                                    'defaultstrategy'
                                )
                            );
                        }
                    }
                }
                $prodcut_list_slip = array();
                foreach ($full_product_list as $id_order_detail) {
                    $order_detail = new OrderDetail((int) $id_order_detail);
                    $prodcut_list_slip[$id_order_detail] = array(
                        'id_order_detail' => $id_order_detail,
                        'quantity' => $full_quantity_list[$id_order_detail],
                        'unit_price' => $order_detail->unit_price_tax_excl,
                        'amount' => $order_detail->unit_price_tax_incl * $full_quantity_list[$id_order_detail]
                    );
                }

                $products = $order->getProducts(false, $full_product_list, $full_quantity_list);

                $total = 0;
                foreach ($products as $product) {
                    $total += $product['unit_price_tax_incl'] * $product['product_quantity'];
                }

                if (isset($postData['shippingBack'])) {
                    $total += $order->total_shipping;
                }

                $choosen = false;
                $voucher = 0;
                if (isset($postData['refund_total_voucher_off']) && (int) $postData['refund_total_voucher_off'] == 1) {
                    $total -= $voucher = isset($postData['order_discount_price']) ? (float) $postData['order_discount_price'] : 0;
                } elseif (isset($postData['refund_total_voucher_off']) &&
                    (int) $postData['refund_total_voucher_off'] == 2) {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'This type of refund is not possible for this order.',
                            'defaultstrategy'
                        )
                    );
                }

                return array(
                    'refundType' => self::REFUND_TYPE_CANCEL_PRODUCT,
                    'amount' => $total,
                    'generateCreditSlip' => isset($postData['generateCreditSlip']),
                    'generateDiscount' => isset($postData['generateDiscount']),
                    'shippingBack' => isset($postData['shippingBack']),
                    'reinjectQuantities' => isset($postData['reinjectQuantities']),
                    'productList' => $productList,
                    'qtyList' => $qtyList,
                    'voucher' => $voucher,
                    'choosen' => $choosen,
                    'fullProductList' => $full_product_list,
                    'fullQuantiyList' => $full_quantity_list,
                    'productListSlip' => $prodcut_list_slip,
                    'customizationList' => $customizationList,
                    'customizationQtyList' => $customizationQtyList,
                    'languageId' => Context::getContext()->language->id,
                    'postFinanceCheckoutOffline' => isset($postData['postfinancecheckout_offline'])
                );
            } else {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'No product or quantity has been selected.',
                        'defaultstrategy'
                    )
                );
            }
        }
    }

    public function createReductions(Order $order, array $parsedData)
    {
        if ($parsedData['refundType'] == self::REFUND_TYPE_PARTIAL_REFUND) {
            return $this->createReductionsPartialRefundType($order, $parsedData);
        } elseif ($parsedData['refundType'] == self::REFUND_TYPE_CANCEL_PRODUCT) {
            return $this->createReductionsCancelProductType($order, $parsedData);
        } else {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'The refund type is not supported.',
                    'defaultstrategy'
                )
            );
        }
    }

    private function createReductionsPartialRefundType(Order $order, array $parsedData)
    {
        $configuration = PostFinanceCheckoutVersionadapter::getConfigurationInterface();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        $amount = 0;
        $reductions = array();

        foreach ($parsedData['orderDetailList'] as $idOrderDetail => $details) {
            $quantity = (int) $details['quantity'];
            $orderDetail = new OrderDetail((int) $idOrderDetail);
            $uniqueId = 'order-' . $order->id . '-item-' . $orderDetail->product_id . '-' .
                $orderDetail->product_attribute_id;

            $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($uniqueId);

            if (! $details['amount_modified']) {
                $reduction->setQuantityReduction((int) $quantity);
                $reduction->setUnitPriceReduction(0);
            } else {
                // Merchant did most likely not refund complete amount
                $amount = $details['amount'];
                $unitPrice = $amount / $quantity;
                $originalUnitPrice = (! $parsedData['taxMethod'] ? $orderDetail->unit_price_tax_excl : $orderDetail->unit_price_tax_incl);
                if (Tools::ps_round($originalUnitPrice, $computePrecision) !=
                    Tools::ps_round($unitPrice, $computePrecision)) {
                    $reduction->setQuantityReduction(0);
                    $reduction->setUnitPriceReduction(round($amount / $orderDetail->product_quantity, 8));
                } else {
                    $reduction->setQuantityReduction((int) $quantity);
                    $reduction->setUnitPriceReduction(0);
                }
            }
            $reductions[] = $reduction;
        }
        $shippingCostAmount = $parsedData['shippingCostAmount'];

        if ($shippingCostAmount > 0) {
            $uniqueId = 'order-' . $order->id . '-shipping';
            if (isset($parsedData['TaxMethod']) && ! $parsedData['TaxMethod']) {
                $tax = new Tax();
                $tax->rate = $order->carrier_tax_rate;
                $taxCalculator = new TaxCalculator(array(
                    $tax
                ));
                $totalShippingCost = $taxCalculator->addTaxes($shippingCostAmount);
            } else {
                $totalShippingCost = $shippingCostAmount;
            }
            $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($uniqueId);
            $reduction->setQuantityReduction(0);
            $reduction->setUnitPriceReduction(round($totalShippingCost, 8));
            $reductions[] = $reduction;
        }
        if ($parsedData['voucher'] > 0) {
            // It is only possible to refund all vouchers at once
            $usedTaxes = $this->getUsedTaxes($order);
            foreach ($order->getCartRules() as $orderCartRule) {
                $uniqueIds = $this->getUsedDiscountUniqueIds(
                    'order-' . $order->id . '-discount-' . $orderCartRule['id_order_cart_rule'],
                    new CartRule($orderCartRule['id_cart_rule']),
                    $orderCartRule['value_tax_excl'],
                    $order,
                    $usedTaxes
                );
                foreach ($uniqueIds as $uniqueId) {
                    $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($uniqueId);
                    $reduction->setQuantityReduction(1);
                    $reduction->setUnitPriceReduction(0);
                    $reductions[] = $reduction;
                }
            }
        }
        return $reductions;
    }

    private function createReductionsCancelProductType(Order $order, array $parsedData)
    {
        $reductions = array();
        foreach (array_keys($parsedData['fullProductList']) as $idOrderDetail) {
            $quantity = $parsedData['fullQuantiyList'][$idOrderDetail];
            $orderDetail = new OrderDetail((int) $idOrderDetail);
            $uniqueId = 'order-' . $order->id . '-item-' . $orderDetail->product_id . '-' .
                $orderDetail->product_attribute_id;
            $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($uniqueId);
            $reduction->setQuantityReduction((int) $quantity);
            $reduction->setUnitPriceReduction(0);
            $reductions[] = $reduction;
        }

        if ($parsedData['shippingBack'] && $order->total_shipping > 0) {
            $uniqueId = 'order-' . $order->id . '-shipping';
            $totalShippingCost = $order->total_shipping;
            $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($uniqueId);
            $reduction->setQuantityReduction(0);
            $reduction->setUnitPriceReduction(round($totalShippingCost, 8));
            $reductions[] = $reduction;
        }

        if ($parsedData['voucher'] > 0) {
            // It is only possible to refund all vouchers at once
            $usedTaxes = $this->getUsedTaxes($order);
            foreach ($order->getCartRules() as $orderCartRule) {
                $uniqueIds = $this->getUsedDiscountUniqueIds(
                    'order-' . $order->id . '-discount-' . $orderCartRule['id_order_cart_rule'],
                    new CartRule($orderCartRule['id_cart_rule']),
                    $orderCartRule['value_tax_excl'],
                    $order,
                    $usedTaxes
                );
                foreach ($uniqueIds as $uniqueId) {
                    $reduction = new \PostFinanceCheckout\Sdk\Model\LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($uniqueId);
                    $reduction->setQuantityReduction(1);
                    $reduction->setUnitPriceReduction(0);
                    $reductions[] = $reduction;
                }
            }
        }
        return $reductions;
    }

    private function getUsedDiscountUniqueIds(
        $uniqueIdBase,
        CartRule $cartRule,
        $discountWithoutTax,
        Order $order,
        $usedTaxes
    ) {
        $reductionPercent = $cartRule->reduction_percent;
        $reductionAmount = $cartRule->reduction_amount;
        $reductionProduct = $cartRule->reduction_product;

        $usedUniqueIds = array();

        if ($cartRule->gift_product != 0) {
            $usedUniqueIds[] = $uniqueIdBase . '-gift';
        }

        $currencyCode = PostFinanceCheckoutHelper::convertCurrencyIdToCode($order->id_currency);
        // Discount Rate
        if ($reductionPercent > 0) {
            if ($reductionProduct > 0) {
                $usedUniqueIds[] = $uniqueIdBase;
            } elseif ($reductionProduct == - 1) {
                $usedUniqueIds[] = $uniqueIdBase;
            } else {
                $selectedProducts = array();
                if ($reductionProduct == - 2) {
                    $selectedProducts = PostFinanceCheckoutCartruleaccessor::checkProductRestrictionsStatic(
                        $cartRule,
                        new Cart($order->id_cart)
                    );
                    // Selection of Product
                }
                $discountUniqueIds = array();
                foreach ($usedTaxes as $id => $values) {
                    $amount = 0;
                    foreach ($values['products'] as $pId => $pd) {
                        foreach ($pd as $paId => $amountValue) {
                            if (empty($selectedProducts) || in_array($pId . '-' . $paId, $selectedProducts)) {
                                $amount += $amountValue;
                            }
                        }
                    }
                    $totalAmount = PostFinanceCheckoutHelper::roundAmount(
                        $amount * $reductionPercent / 100 * - 1,
                        $currencyCode
                    );
                    if ($totalAmount == 0) {
                        continue;
                    }
                    $discountUniqueIds[] = $uniqueIdBase . '-' . $id;
                }
                $usedUniqueIds = array_merge($usedUniqueIds, $discountUniqueIds);
            }
        }
        // Discount Absolute
        if ((float) $reductionAmount > 0) {
            if ($reductionProduct > 0) {
                $usedUniqueIds[] = $uniqueIdBase;
            } elseif ($reductionProduct == 0) {
                $ratio = $discountWithoutTax / $order->total_products;
                $discountUniqueIds = array();
                foreach ($usedTaxes as $id => $values) {
                    $amount = 0;
                    foreach ($values['products'] as $pId => $pd) {
                        foreach ($pd as $paId => $amountValue) {
                            $amount += $amountValue * $ratio;
                        }
                    }
                    $totalAmount = PostFinanceCheckoutHelper::roundAmount($amount * - 1, $currencyCode);
                    if ($totalAmount == 0) {
                        continue;
                    }
                    $discountUniqueIds[] = $uniqueIdBase . '-' . $id;
                }
                $usedUniqueIds = array_merge($usedUniqueIds, $discountUniqueIds);
            }
        }
        return $usedUniqueIds;
    }

    private function getUsedTaxes(Order $order)
    {
        $usedTaxes = array();
        foreach ($order->getProducts() as $orderItem) {
            $itemCosts = (float) $orderItem['total_wt'];
            $itemCostsE = (float) $orderItem['total_price'];
            if (isset($orderItem['total_customization_wt'])) {
                $itemCosts = (float) $orderItem['total_customization_wt'];
                $itemCostsE = (float) $orderItem['total_customization'];
            }
            $productTaxCalculator = $orderItem['tax_calculator'];
            if ($itemCosts != $itemCostsE) {
                $psTaxes = $productTaxCalculator->getTaxesAmount($itemCostsE);
                ksort($psTaxes);
                $taxesKey = implode('-', array_keys($psTaxes));
                if (! isset($usedTaxes[$taxesKey])) {
                    $usedTaxes[$taxesKey] = array(
                        'products' => array()
                    );
                }

                if (! isset($usedTaxes[$taxesKey]['products'][$orderItem['product_id']])) {
                    $usedTaxes[$taxesKey]['products'][$orderItem['product_id']] = array();
                }
                $usedTaxes[$taxesKey]['products'][$orderItem['product_id']][$orderItem['product_attribute_id']] = $itemCosts;
            }
        }
        return $usedTaxes;
    }

    public function applyRefund(Order $order, array $parsedData)
    {
        if ($parsedData['refundType'] == self::REFUND_TYPE_PARTIAL_REFUND) {
            return $this->applyRefundPartialRefundType($order, $parsedData);
        }
        if ($parsedData['refundType'] == self::REFUND_TYPE_CANCEL_PRODUCT) {
            return $this->applyRefundCancelProductType($order, $parsedData);
        }
    }

    private function applyRefundPartialRefundType(Order $order, array $parsedData)
    {
        foreach ($parsedData['orderDetailList'] as $idOrderDetail => $details) {
            if (! $order->hasBeenDelivered() ||
                ($order->hasBeenDelivered() && $parsedData['reinjectQuantities']) && $details['quantity'] > 0) {
                // Admin OrderController;
                $order_detail = new OrderDetail((int) $idOrderDetail);
                $this->reinjectQuantity($order_detail, $details['quantity'], false, $parsedData['languageId']);
            }
        }

        $order_carrier = new OrderCarrier((int) $order->getIdOrderCarrier());
        if (Validate::isLoadedObject($order_carrier)) {
            $order_carrier->weight = (float) $order->getTotalWeight();
            if ($order_carrier->update()) {
                $order->weight = sprintf("%.3f " . Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
            }
        }

        if (! OrderSlip::create(
            $order,
            $parsedData['orderDetailList'],
            $parsedData['shippingCostAmount'],
            $parsedData['voucher'],
            $parsedData['choosen'],
            ($parsedData['taxMethod'] ? false : true)
        )) {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'You cannot generate a partial credit slip.',
                    'defaultstrategy'
                )
            );
        }
        Hook::exec(
            'actionOrderSlipAdd',
            array(
                'order' => $order,
                'productList' => $parsedData['orderDetailList'],
                'qtyList' => $parsedData['fullQuantityList']
            ),
            null,
            false,
            true,
            false,
            $order->id_shop
        );

        foreach ($parsedData['orderDetailList'] as &$product) {
            $order_detail = new OrderDetail((int) $product['id_order_detail']);
            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                StockAvailable::synchronize($order_detail->product_id);
            }
        }
        $result = array();
        // Generate voucher
        if ($parsedData['generateDiscountRefund']) {
            $cart_rule = new CartRule();
            $cart_rule->description = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l('Credit slip for order #%d', 'defaultstrategy'),
                $order->id
            );
            $language_ids = Language::getIDs(false);
            foreach ($language_ids as $id_lang) {
                // Define a temporary name
                $cart_rule->name[$id_lang] = sprintf('V0C%1$dO%2$d', $order->id_customer, $order->id);
            }

            // Define a temporary code
            $cart_rule->code = sprintf('V0C%1$dO%2$d', $order->id_customer, $order->id);
            $cart_rule->quantity = 1;
            $cart_rule->quantity_per_user = 1;

            // Specific to the customer
            $cart_rule->id_customer = $order->id_customer;
            $now = time();
            $cart_rule->date_from = date('Y-m-d H:i:s', $now);
            $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
            $cart_rule->partial_use = 1;
            $cart_rule->active = 1;

            $cart_rule->reduction_amount = $this->getRefundTotal($parsedData);
            $cart_rule->reduction_tax = $order->getTaxCalculationMethod() != PS_TAX_EXC;
            $cart_rule->minimum_amount_currency = $order->id_currency;
            $cart_rule->reduction_currency = $order->id_currency;

            if (! $cart_rule->add()) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'You cannot generate a voucher.',
                        'defaultstrategy'
                    )
                );
            }
            // Update the voucher code and name
            foreach ($language_ids as $id_lang) {
                $cart_rule->name[$id_lang] = sprintf('V%1$dC%2$dO%3$d', $cart_rule->id, $order->id_customer, $order->id);
            }
            $cart_rule->code = sprintf('V%1$dC%2$dO%3$d', $cart_rule->id, $order->id_customer, $order->id);

            if (! $cart_rule->update()) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'You cannot generate a voucher.',
                        'defaultstrategy'
                    )
                );
            }
            $result['voucherCreated'] = true;
            $result['voucherCode'] = $cart_rule->code;
            $result['voucherAmount'] = Tools::displayPrice($cart_rule->reduction_amount, $order->id_currency, false);
        }
        return $result;
    }

    private function applyRefundCancelProductType(Order $order, array $parsedData)
    {
        $this->applyCancelProductTypeOrderModifications($order, $parsedData);
        return $this->applyCancelProductTypeCreateSlipVoucher($order, $parsedData);
    }

    private function applyCancelProductTypeOrderModifications(Order $order, array $parsedData)
    {
        if ($parsedData['productList']) {
            $qtyList = $parsedData['qtyList'];
            foreach ($parsedData['productList'] as $key => $id_order_detail) {
                $qty_cancel_product = abs($qtyList[$key]);
                $order_detail = new OrderDetail((int) ($id_order_detail));

                if (! $order->hasBeenDelivered() ||
                    ($order->hasBeenDelivered() && $parsedData['reinjectQuantities']) && $qty_cancel_product > 0) {
                    $this->reinjectQuantity($order_detail, $qty_cancel_product, false, $parsedData['languageId']);
                }

                // Delete product
                $order_detail = new OrderDetail((int) $id_order_detail);
                if (! $order->deleteProduct($order, $order_detail, $qty_cancel_product)) {
                    throw new Exception(
                        sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'An error occurred while attempting to delete the product. %s',
                                'defaultstrategy'
                            ),
                            ' <span class="bold">' . $order_detail->product_name . '</span>'
                        )
                    );
                }
                // Update weight SUM
                $order_carrier = new OrderCarrier((int) $order->getIdOrderCarrier());
                if (Validate::isLoadedObject($order_carrier)) {
                    $order_carrier->weight = (float) $order->getTotalWeight();
                    if ($order_carrier->update()) {
                        $order->weight = sprintf("%.3f " . Configuration::get('PS_WEIGHT_UNIT'), $order_carrier->weight);
                    }
                }

                if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') &&
                    StockAvailable::dependsOnStock($order_detail->product_id)) {
                    StockAvailable::synchronize($order_detail->product_id);
                }
                Hook::exec(
                    'actionProductCancel',
                    array(
                        'order' => $order,
                        'id_order_detail' => (int) $id_order_detail
                    ),
                    null,
                    false,
                    true,
                    false,
                    $order->id_shop
                );
            }
        }
        if ($parsedData['customizationList']) {
            $customizationQtyList = $parsedData['customizationQtyList'];
            foreach ($parsedData['customizationList'] as $id_customization => $id_order_detail) {
                $order_detail = new OrderDetail((int) ($id_order_detail));
                $qtyCancelProduct = abs($customizationQtyList[$id_customization]);
                if (! $order->deleteCustomization($id_customization, $qtyCancelProduct, $order_detail)) {
                    throw new Exception(
                        sprintf(
                            PostFinanceCheckoutHelper::getModuleInstance()->l(
                                'An error occurred while attempting to delete product customization. %d',
                                'defaultstrategy'
                            ),
                            $id_customization
                        )
                    );
                }
            }
        }
    }

    private function applyCancelProductTypeCreateSlipVoucher(Order $order, array $parsedData)
    {
        $result = array();
        // Generate credit slip
        if ($parsedData['generateCreditSlip']) {
            if (! OrderSlip::create(
                $order,
                $parsedData['productListSlip'],
                $parsedData['shippingBack'],
                $parsedData['voucher'],
                $parsedData['choosen']
            )) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'A credit slip cannot be generated.',
                        'defaultstrategy'
                    )
                );
            }
            Hook::exec(
                'actionOrderSlipAdd',
                array(
                    'order' => $order,
                    'productList' => $parsedData['fullProductList'],
                    'qtyList' => $parsedData['fullQuantityList']
                ),
                null,
                false,
                true,
                false,
                $order->id_shop
            );
        }

        // Generate voucher
        if ($parsedData['generateDiscount']) {
            $cartrule = new CartRule();
            $language_ids = Language::getIDs((bool) $order);
            $cartrule->description = sprintf(
                PostFinanceCheckoutHelper::getModuleInstance()->l('Credit slip for order #%d', 'defaultstrategy'),
                $order->id
            );
            foreach ($language_ids as $id_lang) {
                // Define a temporary name
                $cartrule->name[$id_lang] = 'V0C' . (int) ($order->id_customer) . 'O' . (int) ($order->id);
            }
            // Define a temporary code
            $cartrule->code = 'V0C' . (int) ($order->id_customer) . 'O' . (int) ($order->id);
            $cartrule->quantity = 1;
            $cartrule->quantity_per_user = 1;
            // Specific to the customer
            $cartrule->id_customer = $order->id_customer;
            $now = time();
            $cartrule->date_from = date('Y-m-d H:i:s', $now);
            $cartrule->date_to = date('Y-m-d H:i:s', $now + (3600 * 24 * 365.25)); /* 1 year */
            $cartrule->active = 1;

            $cartrule->reduction_amount = $parsedData['amount'];
            $cartrule->reduction_tax = true;
            $cartrule->minimum_amount_currency = $order->id_currency;
            $cartrule->reduction_currency = $order->id_currency;

            if (! $cartrule->add()) {
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'You cannot generate a voucher.',
                        'defaultstrategy'
                    )
                );
            } else {
                // Update the voucher code and name
                foreach ($language_ids as $id_lang) {
                    $cartrule->name[$id_lang] = 'V' . (int) ($cartrule->id) . 'C' . (int) ($order->id_customer) . 'O' .
                        $order->id;
                }
                $cartrule->code = 'V' . (int) ($cartrule->id) . 'C' . (int) ($order->id_customer) . 'O' . $order->id;
                if (! $cartrule->update()) {
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'You cannot generate a voucher.',
                            'defaultstrategy'
                        )
                    );
                }
                $result['voucherCreated'] = true;
                $result['voucherCode'] = $cartrule->code;
                $result['voucherAmount'] = Tools::displayPrice($cartrule->reduction_amount, $order->id_currency, false);
            }
        }
        return $result;
    }

    public function afterApplyRefundActions(Order $order, array $parsedData, array $appliedData)
    {
        $customer = new Customer((int) ($order->id_customer));
        if ($parsedData['refundType'] == self::REFUND_TYPE_PARTIAL_REFUND ||
            ($parsedData['refundType'] == self::REFUND_TYPE_CANCEL_PRODUCT && $parsedData['generateCreditSlip'])) {
            // Send credit slip email, if configuration is set to send emails
            if (Configuration::get(PostFinanceCheckoutBasemodule::CK_MAIL, null, null, $order->id_shop)) {
                $params = array();
                $params['{lastname}'] = $customer->lastname;
                $params['{firstname}'] = $customer->firstname;
                $params['{id_order}'] = $order->id;
                $params['{order_name}'] = $order->getUniqReference();
                @Mail::Send(
                    (int) $order->id_lang,
                    'credit_slip',
                    Mail::l('New credit slip regarding your order', (int) $order->id_lang),
                    $params,
                    $customer->email,
                    $customer->firstname . ' ' . $customer->lastname,
                    null,
                    null,
                    null,
                    null,
                    _PS_MAIL_DIR_,
                    false,
                    (int) $order->id_shop
                );
            }
        }
        // The voucher email is sent regardless of the configuration
        if (isset($appliedData['voucherCreated']) && $appliedData['voucherCreated']) {
            $params = array();
            $params['{lastname}'] = $customer->lastname;
            $params['{firstname}'] = $customer->firstname;
            $params['{id_order}'] = $order->id;
            $params['{order_name}'] = $order->getUniqReference();
            $params['{voucher_amount}'] = $appliedData['voucherAmount'];
            $params['{voucher_num}'] = $appliedData['voucherCode'];
            @Mail::Send(
                (int) $order->id_lang,
                'voucher',
                sprintf(Mail::l('New voucher for your order #%s', (int) $order->id_lang), $order->reference),
                $params,
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                null,
                null,
                _PS_MAIL_DIR_,
                false,
                (int) $order->id_shop
            );
        }
    }

    public function getPostFinanceCheckoutRefundType(array $parsedData)
    {
        if ($parsedData['postFinanceCheckoutOffline']) {
            return \PostFinanceCheckout\Sdk\Model\RefundType::MERCHANT_INITIATED_OFFLINE;
        }
        return \PostFinanceCheckout\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE;
    }

    public function getRefundTotal(array $parsedData)
    {
        return $parsedData['amount'];
    }

    public function isVoucherOnlyPostFinanceCheckout(Order $order, array $postData)
    {
        return PostFinanceCheckoutVersionadapter::isVoucherOnlyPostFinanceCheckout($postData);
    }

    public function isCancelRequest(Order $order, array $postData)
    {
        if (! ($order->hasBeenShipped() || $order->hasBeenPaid())) {
            return true;
        }
        return false;
    }

    public function processCancel(Order $order, array $postData)
    {
        PostFinanceCheckoutHelper::startDBTransaction();
        $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
        if (! $transactionInfo) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw new Exception(
                sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Could not load the coresponding transaction for order with id %d',
                        'defaultstrategy'
                    ),
                    $order->id
                )
            );
        }
        PostFinanceCheckoutHelper::lockByTransactionId(
            $transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId()
        );
        if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l(
                    'The line items for this order can not be changed',
                    'defaultstrategy'
                )
            );
        }
        try {
            $parsedData = $this->validateDataCancelProductType($order, $postData);
            $this->applyCancelProductTypeOrderModifications($order, $parsedData);
            $orders = $order->getBrother()->getResults();
            $orders[] = $order;
            $lineItems = PostFinanceCheckoutServiceLineitem::instance()->getItemsFromOrders($orders);
            PostFinanceCheckoutServiceTransaction::instance()->updateLineItems(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId(),
                $lineItems
            );
        } catch (Exception $e) {
            PostFinanceCheckoutHelper::rollbackDBTransaction();
            throw new Exception(
                sprintf(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'Could not update the line items at %s. Reason: %s',
                        'defaultstrategy'
                    ),
                    'PostFinance Checkout',
                    PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                )
            );
        }
        PostFinanceCheckoutHelper::commitDBTransaction();
    }

    private function reinjectQuantity($orderDetail, $qtyCancelProduct, $delete, $languageId)
    {
        // Reinject product
        $reinjectableQuantity = (int) $orderDetail->product_quantity - (int) $orderDetail->product_quantity_reinjected;
        $quantityToReinject = $qtyCancelProduct > $reinjectableQuantity ? $reinjectableQuantity : $qtyCancelProduct;
        $product = new Product($orderDetail->product_id, false, (int) $languageId, (int) $orderDetail->id_shop);

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management &&
            $orderDetail->id_warehouse != 0) {
            $manager = StockManagerFactory::getManager();
            $movements = StockMvt::getNegativeStockMvts(
                $orderDetail->id_order,
                $orderDetail->product_id,
                $orderDetail->product_attribute_id,
                $quantityToReinject
            );
            $leftToReinject = $quantityToReinject;
            foreach ($movements as $movement) {
                if ($leftToReinject > $movement['physical_quantity']) {
                    $quantityToReinject = $movement['physical_quantity'];
                }

                $leftToReinject -= $quantityToReinject;
                if (Pack::isPack((int) $product->id)) {
                    // Gets items
                    if ($product->pack_stock_type == 1 || $product->pack_stock_type == 2 ||
                        ($product->pack_stock_type == 3 && Configuration::get('PS_PACK_STOCK_TYPE') > 0)) {
                        $productsPack = Pack::getItems((int) $product->id, (int) Configuration::get('PS_LANG_DEFAULT'));
                        // Foreach item
                        foreach ($productsPack as $productPack) {
                            if ($productPack->advanced_stock_management == 1) {
                                $manager->addProduct(
                                    $productPack->id,
                                    $productPack->id_pack_product_attribute,
                                    new Warehouse($movement['id_warehouse']),
                                    $productPack->pack_quantity * $quantityToReinject,
                                    null,
                                    $movement['price_te'],
                                    true
                                );
                            }
                        }
                    }
                    if ($product->pack_stock_type == 0 || $product->pack_stock_type == 2 || ($product->pack_stock_type ==
                        3 && (Configuration::get('PS_PACK_STOCK_TYPE') == 0 ||
                        Configuration::get('PS_PACK_STOCK_TYPE') == 2))) {
                        $manager->addProduct(
                            $orderDetail->product_id,
                            $orderDetail->product_attribute_id,
                            new Warehouse($movement['id_warehouse']),
                            $quantityToReinject,
                            null,
                            $movement['price_te'],
                            true
                        );
                    }
                } else {
                    $manager->addProduct(
                        $orderDetail->product_id,
                        $orderDetail->product_attribute_id,
                        new Warehouse($movement['id_warehouse']),
                        $quantityToReinject,
                        null,
                        $movement['price_te'],
                        true
                    );
                }
            }
            $idProduct = $orderDetail->product_id;
            if ($delete) {
                $orderDetail->delete();
            }
            StockAvailable::synchronize($idProduct);
        } elseif ($orderDetail->id_warehouse == 0) {
            StockAvailable::updateQuantity(
                $orderDetail->product_id,
                $orderDetail->product_attribute_id,
                $quantityToReinject,
                $orderDetail->id_shop
            );

            if ($delete) {
                $orderDetail->delete();
            }
        }
    }

    public function processVoucherDeleteRequest(Order $order, array $data)
    {
        $order_cart_rule = new OrderCartRule($data['id_order_cart_rule']);
        if (Validate::isLoadedObject($order_cart_rule) && $order_cart_rule->id_order == $order->id) {
            PostFinanceCheckoutHelper::startDBTransaction();
            if ($order_cart_rule->id_order_invoice) {
                $order_invoice = new OrderInvoice($order_cart_rule->id_order_invoice);
                if (! Validate::isLoadedObject($order_invoice)) {
                    PostFinanceCheckoutHelper::rollbackDBTransaction();
                    throw new Exception(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            "Can't load Order Invoice object",
                            'defaultstrategy'
                        )
                    );
                }

                // Update amounts of Order Invoice
                $order_invoice->total_discount_tax_excl -= $order_cart_rule->value_tax_excl;
                $order_invoice->total_discount_tax_incl -= $order_cart_rule->value;

                $order_invoice->total_paid_tax_excl += $order_cart_rule->value_tax_excl;
                $order_invoice->total_paid_tax_incl += $order_cart_rule->value;

                // Update Order Invoice
                $order_invoice->update();
            }

            // Update amounts of order
            $order->total_discounts -= $order_cart_rule->value;
            $order->total_discounts_tax_incl -= $order_cart_rule->value;
            $order->total_discounts_tax_excl -= $order_cart_rule->value_tax_excl;

            $order->total_paid += $order_cart_rule->value;
            $order->total_paid_tax_incl += $order_cart_rule->value;
            $order->total_paid_tax_excl += $order_cart_rule->value_tax_excl;

            // Delete Order Cart Rule and update Order
            $order_cart_rule->delete();
            $order->update();

            $transactionInfo = PostFinanceCheckoutHelper::getTransactionInfoForOrder($order);
            if (! $transactionInfo) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                throw new Exception(
                    sprintf(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Could not load the coresponding transaction for order with id %d.',
                            'defaultstrategy'
                        ),
                        $order->id
                    )
                );
            }
            PostFinanceCheckoutHelper::lockByTransactionId(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            if ($transactionInfo->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                throw new Exception(
                    PostFinanceCheckoutHelper::getModuleInstance()->l(
                        'The line items for this order can not be changed.',
                        'defaultstrategy'
                    )
                );
            }
            try {
                $orders = $order->getBrother()->getResults();
                $orders[] = $order;
                $lineItems = PostFinanceCheckoutServiceLineitem::instance()->getItemsFromOrders($orders);
                PostFinanceCheckoutServiceTransaction::instance()->updateLineItems(
                    $transactionInfo->getSpaceId(),
                    $transactionInfo->getTransactionId(),
                    $lineItems
                );
                PostFinanceCheckoutHelper::commitDBTransaction();
            } catch (Exception $e) {
                PostFinanceCheckoutHelper::rollbackDBTransaction();
                throw new Exception(
                    sprintf(
                        PostFinanceCheckoutHelper::getModuleInstance()->l(
                            'Could not update the line items at %s. Reason: %s',
                            'defaultstrategy'
                        ),
                        'PostFinance Checkout',
                        PostFinanceCheckoutHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
            }
        } else {
            throw new Exception(
                PostFinanceCheckoutHelper::getModuleInstance()->l('You cannot edit this cart rule.', 'defaultstrategy')
            );
        }
    }

    public function processVoucherAddRequest(Order $order, array $data)
    {
        throw new Exception(
            PostFinanceCheckoutHelper::getModuleInstance()->l(
                'You cannot add a discount to this order.',
                'defaultstrategy'
            )
        );
    }
}
