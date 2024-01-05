[![Build Status](https://travis-ci.org/pfpayments/prestashop-1.7.svg?branch=master)](https://travis-ci.org/pfpayments/prestashop-1.7)



# PrestaShop 1.7 PostFinance Checkout Integration
This repository contains the PrestaShop PostFinance Checkout payment module that enables the shop to process payments with [PostFinance Checkout](https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).

## To install module manually by dragging up zip file, please download [.zip archive](https://plugin-documentation.postfinance-checkout.ch/pfpayments/prestashop-1.7/1.2.40/postfinancecheckout.zip) of module with correct structure required by Prestashop installation.

## Important Notice for Existing Merchants

**Compatibility Update for Our Prestashop Plugin**

Effective from version 1.2.36 of our plugin, we have made important changes to enhance its functionality. To ensure a seamless experience, please take note of the following instructions:

### 1. Uninstall the Mailhook Plugin

If you are an existing merchant using our Prestashop plugin, it is crucial that you uninstall the [Mailhook](https://github.com/wallee-payment/prestashop-mailhook) plugin from your Prestashop shop modules. This step is necessary because our plugin now includes the Mailhook functionality by default. 
Also please disable all plugins that overrides default mail behavior. If you can't install/enable or uninstall the plugin, please rename or delete Mail.php from override folder if it exists there.

### 2. Download the Correct Plugin Archive

We have provided a [ZIP archive](#WalleeDocPath(/postfinancecheckout.zip)) that contains the correct structural setup of our plugin, along with the Mailhook plugin. To guarantee the proper operation of our solution, please use this archive for installation.

Your prompt attention to these instructions is greatly appreciated. If you have any questions or require further assistance, please do not hesitate to contact our support team.


##### To use this extension, a [PostFinance Checkout](https://checkout.postfinance.ch/en-ch/user/signup) account is required.

## Requirements

* [PrestaShop](https://www.prestashop.com/) 1.7.8.7
* [PHP](http://php.net/) 5.6 or later

## Documentation

* [English](https://plugin-documentation.postfinance-checkout.ch/pfpayments/prestashop-1.7/1.2.40/docs/en/documentation.html)

## Support

Support queries can be issued on the [PostFinance Checkout support site](https://www.postfinance.ch/en/business/support.html).

## License

Please see the [license file](https://github.com/pfpayments/prestashop-1.7/blob/1.2.40/LICENSE) for more information.

## Other PrestaShop Versions

Find the module for different PrestaShop versions [here](../../../prestashop).
