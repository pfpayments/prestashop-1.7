<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class PostFinanceCheckout_FeeHelper
{
    public static function removeFeeProductFromCart(Cart $cart){
        $feeProductId = Configuration::get(PostFinanceCheckout::CK_FEE_ITEM);
        
        if ($feeProductId != null) {
            $defaultAttributeId = Product::getDefaultAttribute($feeProductId);
            
            SpecificPrice::deleteByIdCart($cart->id, $feeProductId, $defaultAttributeId);
            $cart->deleteProduct($feeProductId, $defaultAttributeId);
        }
        PostFinanceCheckout_VersionAdapter::clearCartRuleStaticCache();
    }
    
    public static function addFeeProductToCart(PostFinanceCheckout_Model_MethodConfiguration $methodConfiguration, Cart $cart){
        
        $feeProductId = Configuration::get(PostFinanceCheckout::CK_FEE_ITEM);
        
        if ($feeProductId != null) {
            $defaultAttributeId = Product::getDefaultAttribute($feeProductId);
            
            self::removeFeeProductFromCart($cart);
            $feeValues = self::getFeeValues($cart, $methodConfiguration);
            
            if ($feeValues['fee_total'] > 0) {
                $cart->updateQty(1, $feeProductId, $defaultAttributeId);
                $specificPrice = new SpecificPrice();
                $specificPrice->id_product = (int) $feeProductId;
                $specificPrice->id_product_attribute = (int) $defaultAttributeId;
                $specificPrice->id_cart = (int) $cart->id;
                $specificPrice->id_shop = (int) $cart->id_shop;
                $specificPrice->id_currency = $cart->id_currency;
                $specificPrice->id_country = 0;
                $specificPrice->id_group = 0;
                $specificPrice->id_customer = 0;
                $specificPrice->from_quantity = 1;
                $specificPrice->price = $feeValues['fee_total'];
                $specificPrice->reduction_type = 'amount';
                $specificPrice->reduction_tax = 1;
                $specificPrice->reduction = 0;
                $specificPrice->from = date("Y-m-d H:i:s", time() - 3600);
                $specificPrice->to = date("Y-m-d H:i:s", time() + 48 * 3600);
                $specificPrice->add();
            }
        }
        PostFinanceCheckout_VersionAdapter::clearCartRuleStaticCache();
    }
    
    public static function getFeeValues(Cart $cart,
        PostFinanceCheckout_Model_MethodConfiguration $methodConfiguration)
    {
        $feeProductId = Configuration::get(PostFinanceCheckout::CK_FEE_ITEM);
        if (empty($feeProductId)) {
            return array(
                'fee_total' => 0,
                'fee_total_wt' => 0
            );
        }
        
        $configuration = PostFinanceCheckout_VersionAdapter::getConfigurationInterface();
        
        $currency = Currency::getCurrencyInstance($cart->id_currency);
        
        $fixed = $methodConfiguration->getFeeFixed();
        
        $feeFixedConverted = Tools::convertPrice($fixed,
            Currency::getCurrencyInstance((int) $cart->id_currency));
        
        $rate = $methodConfiguration->getFeeRate();
        $feeBaseType = $methodConfiguration->getFeeBase();
        
        switch ($feeBaseType) {
            case PostFinanceCheckout::TOTAL_MODE_BOTH_INC:
                $taxes = true;
                $feeType = Cart::BOTH;
                break;
            case PostFinanceCheckout::TOTAL_MODE_BOTH_EXC:
                $taxes = false;
                $feeType = Cart::BOTH;
                break;
            case PostFinanceCheckout::TOTAL_MODE_WITHOUT_SHIPPING_INC:
                $taxes = true;
                $feeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case PostFinanceCheckout::TOTAL_MODE_WITHOUT_SHIPPING_EXC:
                $taxes = false;
                $feeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case PostFinanceCheckout::TOTAL_MODE_PRODUCTS_INC:
                $taxes = true;
                $feeType = Cart::ONLY_PRODUCTS;
                break;
            case PostFinanceCheckout::TOTAL_MODE_PRODUCTS_EXC:
                $taxes = false;
                $feeType = Cart::ONLY_PRODUCTS;
                break;
        }
        
        $feeBase = $cart->getOrderTotal($taxes, $feeType);
        $feeRateAmount = $feeBase * $rate / 100;
        
        $feeTotal = $feeFixedConverted + $feeRateAmount;
        
        $product = new Product($feeProductId);
        
        $taxGroup = $product->getIdTaxRulesGroup();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');
        
        $result = array(
            'fee_total' => Tools::ps_round($feeTotal, $computePrecision),
            'fee_total_wt' => Tools::ps_round($feeTotal, $computePrecision)
        );
        
        if ($taxGroup != 0) {
            $addressFactory = PostFinanceCheckout_VersionAdapter::getAddressFactory();
            $taxAddressType = Configuration::get('PS_TAX_ADDRESS_TYPE');
            if ($taxAddressType == 'id_address_invoice') {
                $idAddress = (int) $cart->id_address_invoice;
            } else {
                $idAddress = (int) $cart->id_address_delivery;
            }
            $address = $addressFactory->findOrCreate($idAddress, true);
            $taxCalculator = TaxManagerFactory::getManager($address, $taxGroup)->getTaxCalculator();
            if ($methodConfiguration->isFeeAddTax()) {
                $result['fee_total_wt'] = Tools::ps_round(
                    $taxCalculator->addTaxes($feeTotal), $computePrecision);
                $result['fee_total'] = Tools::ps_round($feeTotal, $computePrecision);
            } else {
                $result['fee_total_wt'] = Tools::ps_round($feeTotal, $computePrecision);
                $result['fee_total'] = Tools::ps_round(
                    $taxCalculator->removeTaxes($feeTotal), $computePrecision);
            }
        }
        return $result;
    }
    
}

