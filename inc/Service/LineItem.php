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
 * This service provides methods to handle manual tasks.
 */
class PostFinanceCheckout_Service_LineItem extends PostFinanceCheckout_Service_Abstract
{

    /**
     * Returns the line items from the given cart
     *
     * @param Cart $cart
     * @return \PostFinanceCheckout\Sdk\Model\LineItemCreate[]
     */
    public function getItemsFromCart(Cart $cart)
    {
        $currencyCode = PostFinanceCheckout_Helper::convertCurrencyIdToCode($cart->id_currency);
        $items = array();
        $summary = $cart->getSummaryDetails();
        $taxAddress = new Address((int) $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
        
        // Needed for discounts;
        $usedTaxes = array();
        $minPrice = false;
        $cheapestProduct = null;
     
        foreach ($summary['products'] as $productItem) {
            $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
            $totalAmount = $this->roundAmount((float) $productItem['total_wt'], $currencyCode);
            $totalAmountE = (float) $productItem['total'];
            $item->setAmountIncludingTax($totalAmount);
            $item->setName($productItem['name']);
            $item->setQuantity($productItem['quantity']);
            $item->setShippingRequired($productItem['is_virtual'] != '1');
            if (! empty($productItem['reference'])) {
                $item->setSku($productItem['reference']);
            }
            $taxManager = TaxManagerFactory::getManager(
                $taxAddress,
                Product::getIdTaxRulesGroupByIdProduct($productItem['id_product'])
            );
            $productTaxCalculator = $taxManager->getTaxCalculator();
            $psTaxes = $productTaxCalculator->getTaxesAmount($productItem['total']);
            ksort($psTaxes);
            $taxesKey = implode('-', array_keys($psTaxes));
            $addToUsed = false;
            if (! isset($usedTaxes[$taxesKey])) {
                $usedTaxes[$taxesKey] = array(
                    'products' => array(),
                    'taxes' => array()
                );
                $addToUsed = true;
            }
            if ($totalAmount > 0 && ($minPrice === false || $minPrice >= $totalAmount)) {
                $minPrice = $totalAmount;
                $cheapestProduct = $productItem['id_product'];
            }
            $taxes = array();
            foreach ($psTaxes as $id => $taxAmount) {
                $psTax = new Tax($id);
                $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                $tax->setTitle($psTax->name[$cart->id_lang]);
                $tax->setRate(round($psTax->rate, 8));
                $taxes[] = $tax;
                if ($addToUsed) {
                    $usedTaxes[$taxesKey]['taxes'][] = $tax;
                }
                if (! isset($usedTaxes[$taxesKey]['products'][$productItem['id_product']])) {
                    $usedTaxes[$taxesKey]['products'][$productItem['id_product']] = array();
                }
                $usedTaxes[$taxesKey]['products'][$productItem['id_product']][$productItem['id_product_attribute']] = $totalAmount;
            }
            $item->setTaxes($taxes);
            $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::PRODUCT);
            if ($productItem['id_product'] == Configuration::get(PostFinanceCheckout::CK_FEE_ITEM)) {
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::FEE);
                $item->setShippingRequired(false);
            }
            $item->setUniqueId(
                'cart-' . $cart->id . '-item-' . $productItem['id_product'] . '-' .
                $productItem['id_product_attribute']
            );
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add shipping costs
        $shippingCosts = (float) $summary['total_shipping'];
        $shippingCostExcl = (float) $summary['total_shipping_tax_exc'];
        if ($shippingCosts > 0) {
            $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
            $item->setAmountIncludingTax($this->roundAmount($shippingCosts, $currencyCode));
            $item->setQuantity(1);
            $item->setShippingRequired(false);
            $item->setSku('shipping');
            $name = PostFinanceCheckout_Helper::getModuleInstance()->l('Shipping', 'lineitem');
            $taxCalculatorFound = false;
            if (isset($summary['carrier']) && $summary['carrier'] instanceof Carrier) {
                $name = $summary['carrier']->name;
                $shippingTaxCalculator = $summary['carrier']->getTaxCalculator($taxAddress);
                $psTaxes = $shippingTaxCalculator->getTaxesAmount($shippingCostExcl);
                $taxes = array();
                foreach ($psTaxes as $id => $amount) {
                    $psTax = new Tax($id);
                    $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                    $tax->setTitle($psTax->name[$cart->id_lang]);
                    $tax->setRate(round($psTax->rate, 8));
                    $taxes[] = $tax;
                }
                $item->setTaxes($taxes);
                $taxCalculatorFound = true;
            }
            $item->setName($name);
            if (! $taxCalculatorFound) {
                $taxRate = 0;
                $taxName = PostFinanceCheckout_Helper::getModuleInstance()->l('Tax', 'lineitem');
                if ($shippingCostExcl > 0) {
                    $taxRate = ($shippingCosts - $shippingCostExcl) / $shippingCostExcl * 100;
                }
                if ($taxRate > 0) {
                    $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(array(
                        $tax
                    ));
                }
            }
            $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::SHIPPING);
            $item->setUniqueId('cart-' . $cart->id . '-shipping');
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add wrapping costs
        $wrappingCosts = (float) $summary['total_wrapping'];
        $wrappingCostExcl = (float) $summary['total_wrapping_tax_exc'];
        if ($wrappingCosts > 0) {
            $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
            $item->setAmountIncludingTax($this->roundAmount($wrappingCosts, $currencyCode));
            $item->setName(PostFinanceCheckout_Helper::getModuleInstance()->l('Wrapping Fee', 'lineitem'));
            $item->setQuantity(1);
            $item->setShippingRequired(false);
            $item->setSku('wrapping');
            if (Configuration::get('PS_ATCP_SHIPWRAP')) {
                if ($wrappingCostExcl > 0) {
                    $taxRate = 0;
                    $taxName = PostFinanceCheckout_Helper::getModuleInstance()->l('Tax', 'lineitem');
                    $taxRate = ($wrappingCosts - $wrappingCostExcl) / $wrappingCostExcl * 100;
                }
                if ($taxRate > 0) {
                    $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                    $tax->setTitle($taxName);
                    $tax->setRate(round($taxRate, 8));
                    $item->setTaxes(array(
                        $tax
                    ));
                }
            } else {
                $wrappingTaxManager = TaxManagerFactory::getManager(
                    $taxAddress,
                    (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP')
                );
                $wrappingTaxCalculator = $wrappingTaxManager->getTaxCalculator();
                $psTaxes = $wrappingTaxCalculator->getTaxesAmount(
                    $wrappingCostExcl,
                    $wrappingCosts,
                    _PS_PRICE_COMPUTE_PRECISION_,
                    Configuration::get('PS_PRICE_ROUND_MODE')
                );
                $taxes = array();
                foreach ($psTaxes as $id => $amount) {
                    $psTax = new Tax($id);
                    $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                    
                    $tax->setTitle($psTax->name[$cart->id_lang]);
                    $tax->setRate(round($psTax->rate, 8));
                    $taxes[] = $tax;
                }
                $item->setTaxes($taxes);
            }
            $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::FEE);
            $item->setUniqueId('cart-' . $cart->id . '-wrapping');
            $items[] = $this->cleanLineItem($item);
        }
        
        // Add discounts
        if (count($summary['discounts']) > 0) {
            $productTotalExc = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
            foreach ($summary['discounts'] as $discount) {
                $discountItems = $this->getDiscountItems($discount['description'], 'discount-' . $discount['id_cart_rule'], 'cart-' . $cart->id . '-discount-' . $discount['id_cart_rule'], (float) $discount['value_real'], (float) $discount['value_tax_exc'], new CartRule($discount['id_cart_rule']), $usedTaxes, $cheapestProduct, $productTotalExc, $cart->id, $currencyCode);
                $items = array_merge($items, $discountItems);
            }
        }
        
        $cleaned = PostFinanceCheckout_Helper::cleanupLineItems(
            $items,
            $cart->getOrderTotal(true, Cart::BOTH),
            $currencyCode
        );
        return $cleaned;
    }

    /**
     * Returns the line items from the given cart
     *
     * @param Order[] $orders
     * @return \PostFinanceCheckout\Sdk\Model\LineItemCreate[]
     */
    public function getItemsFromOrders(array $orders)
    {
        $items = $this->getItemsFromOrdersInner($orders);
        $orderTotal = 0;
        foreach ($orders as $order) {
            $orderTotal += ((float) $order->total_products_wt) + ((float) $order->total_shipping) -
            ((float) $order->total_discounts) + ((float) $order->total_wrapping);
        }
        $cleaned = PostFinanceCheckout_Helper::cleanupLineItems(
            $items,
            $orderTotal,
            PostFinanceCheckout_Helper::convertCurrencyIdToCode($order->id_currency)
        );
        return $cleaned;
    }

    protected function getItemsFromOrdersInner(array $orders)
    {
        $items = array();
        
        foreach ($orders as $order) {
            /*@var Order $order */
            $currencyCode = PostFinanceCheckout_Helper::convertCurrencyIdToCode(
                $order->id_currency
            );
            
            $usedTaxes = array();
            $minPrice = false;
            $cheapestProduct = null;
            
            foreach ($order->getProducts() as $orderItem) {
                $uniqueId = 'order-' . $order->id . '-item-' . $orderItem['product_id'] . '-' .
                     $orderItem['product_attribute_id'];
                
                $itemCosts = (float) $orderItem['total_wt'];
                $itemCostsE = (float) $orderItem['total_price'];
                if (isset($orderItem['total_customization_wt'])) {
                    $itemCosts = (float) $orderItem['total_customization_wt'];
                    $itemCostsE = (float) $orderItem['total_customization'];
                }
                $sku = $orderItem['reference'];
                if (empty($sku)) {
                    $sku = $orderItem['product_name'];
                }
                if ($itemCosts > 0 && ($minPrice === false || $minPrice > $itemCosts)) {
                    $minPrice = $itemCosts;
                    $cheapestProduct = $orderItem['product_id'];
                }
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($itemCosts, $currencyCode));
                $item->setName($orderItem['product_name']);
                $item->setQuantity($orderItem['product_quantity']);
                $item->setShippingRequired($orderItem['is_virtual'] != '1');
                $item->setSku($sku);
                $productTaxCalculator = $orderItem['tax_calculator'];
                if ($itemCosts != $itemCostsE) {
                    $psTaxes = $productTaxCalculator->getTaxesAmount($itemCostsE);
                    ksort($psTaxes);
                    $taxesKey = implode('-', array_keys($psTaxes));
                    $addToUsed = false;
                    if (! isset($usedTaxes[$taxesKey])) {
                        $usedTaxes[$taxesKey] = array(
                            'products' => array(),
                            'taxes' => array()
                        );
                        $addToUsed = true;
                    }
                    $taxes = array();
                    foreach ($psTaxes as $id => $taxAmount) {
                        $psTax = new Tax($id);
                        $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                        $tax->setTitle($psTax->name[$order->id_lang]);
                        $tax->setRate(round($psTax->rate, 8));
                        $taxes[] = $tax;
                        if ($addToUsed) {
                            $usedTaxes[$taxesKey]['taxes'][] = $tax;
                        }
                        if (! isset($usedTaxes[$taxesKey]['products'][$orderItem['product_id']])) {
                            $usedTaxes[$taxesKey]['products'][$orderItem['product_id']] = array();
                        }
                        $usedTaxes[$taxesKey]['products'][$orderItem['product_id']][$orderItem['product_attribute_id']] = $itemCosts;
                    }
                    $item->setTaxes($taxes);
                }
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::PRODUCT);
                if ($orderItem['product_id'] ==
                     Configuration::get(PostFinanceCheckout::CK_FEE_ITEM)) {
                    $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::FEE);
                    $item->setShippingRequired(false);
                }
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
            $taxAddress = new Address((int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            $shippingItem = null;
            // Add shipping costs
            $shippingCosts = (float) $order->total_shipping;
            $shippingCostExcl = (float) $order->total_shipping_tax_excl;
            if ($shippingCosts > 0) {
                $uniqueId = 'order-' . $order->id . '-shipping';
                
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $name = PostFinanceCheckout_Helper::getModuleInstance()->l('Shipping', 'lineitem');
                $item->setName($name);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku('shipping');
                $item->setAmountIncludingTax($this->roundAmount($shippingCosts, $currencyCode));
                
                $carrier = new Carrier($order->id_carrier);
                if ($carrier->id && $taxAddress->id) {
                    $item->setName($carrier->name);
                    $shippingTaxCalculator = $carrier->getTaxCalculator($taxAddress);
                    $psTaxes = $shippingTaxCalculator->getTaxesAmount($itemCostsE);
                    $taxes = array();
                    foreach ($psTaxes as $id => $amount) {
                        $psTax = new Tax($id);
                        $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                        $tax->setTitle($psTax->name[$order->id_lang]);
                        $tax->setRate(round($psTax->rate, 8));
                        $taxes[] = $tax;
                    }
                    $item->setTaxes($taxes);
                } else {
                    $taxRate = 0;
                    $taxName = PostFinanceCheckout_Helper::getModuleInstance()->l('Tax', 'lineitem');
                    if ($shippingCostExcl > 0) {
                        $taxRate = ($shippingCosts - $shippingCostExcl) / $shippingCostExcl * 100;
                    }
                    
                    if ($taxRate > 0) {
                        $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                        $tax->setTitle($taxName);
                        $tax->setRate(round($taxRate, 8));
                        $item->setTaxes(
                            array(
                                $tax
                            )
                        );
                    }
                }
                    
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::SHIPPING);
                $item->setUniqueId($uniqueId);
                $shippingItem = $this->cleanLineItem($item);
                $items[] = $shippingItem;
            }
                    
            // Add wrapping costs
            $wrappingCosts = (float) $order->total_wrapping;
            $wrappingCostExcl = (float) $order->total_wrapping_tax_excl;
            if ($wrappingCosts > 0) {
                $uniqueId = 'order-' . $order->id . '-wrapping';
                
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($this->roundAmount($wrappingCosts, $currencyCode));
                $item->setName(PostFinanceCheckout_Helper::getModuleInstance()->l('Wrapping Fee', 'lineitem'));
                $item->setQuantity(1);
                $item->setSku('wrapping');
                $wrappingTaxCalculator = null;
                if (Configuration::get('PS_ATCP_SHIPWRAP')) {
                    $wrappingTaxCalculator = Adapter_ServiceLocator::get(
                        'AverageTaxOfProductsTaxCalculator'
                    )->setIdOrder($order->id);
                } else {
                    $wrappingTaxManager = TaxManagerFactory::getManager(
                        $taxAddress,
                        (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP')
                    );
                    $wrappingTaxCalculator = $wrappingTaxManager->getTaxCalculator();
                }
                $psTaxes = $wrappingTaxCalculator->getTaxesAmount(
                    $wrappingCostExcl,
                    $wrappingCosts,
                    _PS_PRICE_COMPUTE_PRECISION_,
                    $order->round_mode
                );
                $taxes = array();
                foreach ($psTaxes as $id => $amount) {
                    $psTax = new Tax($id);
                    $tax = new \PostFinanceCheckout\Sdk\Model\TaxCreate();
                    $tax->setTitle($psTax->name[$order->id_lang]);
                    $tax->setRate(round($psTax->rate, 8));
                    $taxes[] = $tax;
                }
                $item->setTaxes($taxes);
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::FEE);
                $item->setShippingRequired(false);
                $item->setUniqueId($uniqueId);
                $items[] = $this->cleanLineItem($item);
            }
            foreach ($order->getCartRules() as $orderCartRule) {
                $cartRuleObj = new CartRule($orderCartRule['id_cart_rule']);
                $discountItems = $this->getDiscountItems(
                    $orderCartRule['name'],
                    'discount-' . $orderCartRule['id_order_cart_rule'],
                    'order-' . $order->id . '-discount-' . $orderCartRule['id_order_cart_rule'],
                    (float) $orderCartRule['value'],
                    (float) $orderCartRule['value_tax_excl'],
                    $cartRuleObj,
                    $usedTaxes,
                    $cheapestProduct,
                    $order->total_products,
                    $order->id_cart,
                    $currencyCode
                );
                $items = array_merge($items, $discountItems);
            }
            //We do not collapse the refunds with the equal tax rates as one. This would cause issues during refunds of orders
            
            $discountOnly =$this->isFreeShippingDiscountOnly($order);
            if ($discountOnly && $shippingItem != null) {
                $itemFreeShipping = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $name = PostFinanceCheckout_Helper::getModuleInstance()->l('Shipping Discount', 'lineitem');
                
                $itemFreeShipping->setName($discountOnly['name']);
                $itemFreeShipping->setQuantity(1);
                $itemFreeShipping->setShippingRequired(false);
                $itemFreeShipping->setSku('discount-' . $discountOnly['id_order_cart_rule']);
                $itemFreeShipping->setAmountIncludingTax($shippingItem->getAmountIncludingTax()*-1);
                $itemFreeShipping->setTaxes($shippingItem->getTaxes());
                $itemFreeShipping->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                $itemFreeShipping->setUniqueId('order-' . $order->id . '-discount-' . $discountOnly['id_order_cart_rule']);
                $items[] = $this->cleanLineItem($itemFreeShipping);
            }
        }
        return $items;
    }
    
    
    private function isFreeShippingDiscountOnly($order)
    {
        $shippingOnly = false;
        foreach ($order->getCartRules() as $orderCartRule) {
            $cartRuleObj = new CartRule($orderCartRule['id_cart_rule']);
            if ($cartRuleObj->free_shipping) {
                if ($cartRuleObj->reduction_percent == 0 && $cartRuleObj->reduction_amount==0) {
                    $shippingOnly = $orderCartRule;
                } else {
                    //If there is a cart rule, that has free shipping and an amount value, the amount of the shipping
                    //fee is included in the total amount of the discount. So we do not need an extra line item for the shipping discount.
                    return false;
                }
            }
        }
        return $shippingOnly;
    }
        
    private function getDiscountItems($nameBase, $skuBase, $uniqueIdBase, $discountWithTax, $discountWithoutTax, CartRule $cartRule, array $usedTaxes, $cheapestProductId, $productTotalWithoutTax, $cartIdUsed, $currencyCode)
    {
        
        $reductionPercent = $cartRule->reduction_percent;
        $reductionAmount = $cartRule->reduction_amount;
        $reductionProduct = $cartRule->reduction_product;
        
        $discountTotal =  $this->roundAmount($discountWithTax * -1, $currencyCode);
        //Discount Rate
        if ($reductionPercent > 0) {
            if ($reductionProduct > 0) {
                // Sepcific Product
                // Find attribute
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($discountTotal);
                $item->setName($nameBase);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku($skuBase);
                $taxes = array();
                foreach ($usedTaxes as $id => $values) {
                    // For Taxes the product attribute is not required
                    if (isset($values['products'][$reductionProduct])) {
                        $taxes = $values['taxes'];
                    }
                }
                $item->setTaxes($taxes);
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                $item->setUniqueId($uniqueIdBase);
                return array($this->cleanLineItem($item));
            } elseif ($reductionProduct == - 1) {
                // Use Tax of cheapest item
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($discountTotal);
                $item->setName($nameBase);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku($skuBase);
                $taxes = array();
                foreach ($usedTaxes as $id => $values) {
                    // For Taxes the product attribute is not required
                    if (isset($values['products'][$cheapestProductId])) {
                        $taxes = $values['taxes'];
                    }
                }
                $item->setTaxes($taxes);
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                $item->setUniqueId($uniqueIdBase);
                return array($this->cleanLineItem($item));
            } else {
                $selectedProducts = array();
                
                if ($reductionProduct == - 2) {
                    $selectedProducts = PostFinanceCheckout_CartRuleAccessor::checkProductRestrictionsStatic(
                        $cartRule,
                        new Cart($cartIdUsed)
                    );
                    // Selection of Product
                }
                $discountItems = array();
                $totalDiscountComputed = 0;
                foreach ($usedTaxes as $id => $values) {
                    $amount = 0;
                    foreach ($values['products'] as $pId => $pd) {
                        foreach ($pd as $paId => $amountValue) {
                            if (empty($selectedProducts) || in_array($pId . '-' . $paId, $selectedProducts)) {
                                $amount += $amountValue;
                            }
                        }
                    }
                    $totalAmount = $this->roundAmount(
                        $amount * $reductionPercent / 100 * -1,
                        $currencyCode
                    );
                    if ($totalAmount == 0) {
                        continue;
                    }
                    $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                    $totalDiscountComputed += $totalAmount;
                    $item->setAmountIncludingTax($totalAmount);
                    $item->setName($nameBase);
                    $item->setQuantity(1);
                    $item->setShippingRequired(false);
                    $item->setSku($skuBase . '-' . $id);
                    $item->setTaxes($values['taxes']);
                    $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                    $item->setUniqueId($uniqueIdBase . '-' .
                        $id);
                    $discountItems[] = $this->cleanLineItem($item);
                }
                if (count($discountItems) == 1) {
                    // We had multiple taxes in the cart, but all products the discount was applied to have the same tax.
                    // So we set the value to the given amount by prestashop to avoid any further issues.
                    $discountItem = end($discountItems);
                    $discountItem->setAmountIncludingTax($discountTotal);
                    return array($discountItem);
                } else {
                    $diffComp = $discountTotal - $totalDiscountComputed;
                    $diff =  $this->roundAmount($diffComp, $currencyCode);
                    if ($diff != 0) {
                        $modify = end($discountItems);
                        $modify->setAmountIncludingTax($this->roundAmount($modify->getAmountIncludingTax() + $diff, $currencyCode));
                    }
                    return $discountItems;
                }
            }
        }
        // Discount Absolute
        if ((float) $reductionAmount > 0) {
            if ($reductionProduct > 0) {
                // Sepcific Product
                // Find attribute
                $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                $item->setAmountIncludingTax($discountTotal);
                $item->setName($nameBase);
                $item->setQuantity(1);
                $item->setShippingRequired(false);
                $item->setSku($skuBase);
                $taxes = array();
                foreach ($usedTaxes as $id => $values) {
                    // For Taxes the product attribute is not required
                    if (isset($values['products'][$reductionProduct])) {
                        $taxes = $values['taxes'];
                    }
                }
                $item->setTaxes($taxes);
                $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                $item->setUniqueId($uniqueIdBase);
                return array($this->cleanLineItem($item));
            } elseif ($reductionProduct == 0) {
                $ratio = $discountWithoutTax / $productTotalWithoutTax;
                
                $discountItems = array();
                $totalDiscountComputed = 0;
                foreach ($usedTaxes as $id => $values) {
                    $amount = 0;
                    foreach ($values['products'] as $pId => $pd) {
                        foreach ($pd as $paId => $amountValue) {
                            $amount += $amountValue * $ratio;
                        }
                    }
                    
                    $totalAmount = $this->roundAmount($amount *-1, $currencyCode);
                    if ($totalAmount == 0) {
                        continue;
                    }
                    $item = new \PostFinanceCheckout\Sdk\Model\LineItemCreate();
                    $totalDiscountComputed += $totalAmount;
                    $item->setAmountIncludingTax($totalAmount);
                    $item->setName($nameBase);
                    $item->setQuantity(1);
                    $item->setShippingRequired(false);
                    $item->setSku($skuBase . '-' . $id);
                    $item->setTaxes($values['taxes']);
                    $item->setType(\PostFinanceCheckout\Sdk\Model\LineItemType::DISCOUNT);
                    $item->setUniqueId($uniqueIdBase . '-' .
                        $id);
                    $discountItems[] = $this->cleanLineItem($item);
                }
                
                if (count($discountItems) == 1) {
                    // We had multiple taxes in the cart, but all products the discount was applied to have the same tax.
                    // So we set the value to the given amount by prestashop to avoid further issues.
                    $discountItem = end($discountItems);
                    $discountItem->setAmountIncludingTax($discountTotal);
                    return array($discountItem);
                } else {
                    $diffComp = $discountTotal - $totalDiscountComputed;
                    $diff =  $this->roundAmount($diffComp, $currencyCode);
                    if ($diff != 0) {
                        $modify = end($discountItems);
                        $modify->setAmountIncludingTax($this->roundAmount($modify->getAmountIncludingTax() + $diff, $currencyCode));
                    }
                    return $discountItems;
                }
            } else {
                // the other two cases ($reductionProduct == -1 or -2)  are not available for fixed amount discounts
                return array();
            }
        }
        //Free Shipping Only Discounts are not processed here
        return array();
    }

    /**
     * Cleans the given line item for it to meet the API's requirements.
     *
     * @param \PostFinanceCheckout\Sdk\Model\LineItemCreate $lineItem
     * @return \PostFinanceCheckout\Sdk\Model\LineItemCreate
     */
    protected function cleanLineItem(\PostFinanceCheckout\Sdk\Model\LineItemCreate $lineItem)
    {
        $lineItem->setSku($this->fixLength($lineItem->getSku(), 200));
        $lineItem->setName($this->fixLength($lineItem->getName(), 150));
        return $lineItem;
    }
}
