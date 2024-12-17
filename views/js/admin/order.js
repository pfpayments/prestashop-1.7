/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2024 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

    function getOrderIdFromUrl(string)
    {
        let urlSegment = string.split('postfinancecheckout')[1];
        return urlSegment.split('/')[1]
    }
    
    function initialiseDocumentButtons()
    {
        if ($('[data-original-title="Download PostFinanceCheckout Invoice"]').length) {
            $('[data-original-title="Download Packing Slip"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(postfinancecheckout_admin_token + "&action=postFinanceCheckoutPackingSlip&id_order=" + id_order, "_blank");
            });
        
            $('[data-original-title="Download PostFinanceCheckout Invoice"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(postfinancecheckout_admin_token + "&action=postFinanceCheckoutInvoice&id_order=" + id_order, "_blank");
            });
        
            $('#order_grid_table tr').each(function () {
                let $this = $(this);
                let $row = $this.closest('tr');
                let isWPayment = "0";
                let $paymentStatusCol = $row.find('.column-osname');
                let isWPaymentCol = $row.find('.column-is_w_payment').html();
                if (isWPaymentCol) {
                    isWPayment = isWPaymentCol.trim();
                }
                let paymentStatusText = $paymentStatusCol.find('.btn').text();
                if (!paymentStatusText.includes("Payment accepted") || isWPayment.includes("0")) {
                    $row.find('[data-original-title="Download PostFinanceCheckout Invoice"]').hide();
                    $row.find('[data-original-title="Download Packing Slip"]').hide();
                }
            });
        }
    }

    function hideIsWPaymentColumn()
    {
        $('th').each(function () {
            let $this = $(this);
            if ($this.html().includes("is_w_payment")) {
                $('table tr').find('td:eq(' + $this.index() + '),th:eq(' + $this.index() + ')').remove();
                return false;
            }
        });
    }
    
    function movePostFinanceCheckoutDocuments()
    {
        var documentsTab = $('#postfinancecheckout_documents_tab');
        documentsTab.children('a').addClass('nav-link');
    }
    
    function movePostFinanceCheckoutActionsAndInfo()
    {
        var managementBtn = $('a.postfinancecheckout-management-btn');
        var managementInfo = $('span.postfinancecheckout-management-info');
        var orderActions = $('div.order-actions');
        var panel = $('div.panel');
        
        managementBtn.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        managementInfo.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        //to get the styling of prestashop we have to add this
        managementBtn.after("&nbsp;\n");
        managementInfo.after("&nbsp;\n");
    }
    
    function registerPostFinanceCheckoutActions()
    {
        $('#postfinancecheckout_update').off('click.postfinancecheckout').on(
            'click.postfinancecheckout',
            updatePostFinanceCheckout
        );
        $('#postfinancecheckout_void').off('click.postfinancecheckout').on(
            'click.postfinancecheckout',
            showPostFinanceCheckoutVoid
        );
        $("#postfinancecheckout_completion").off('click.postfinancecheckout').on(
            'click.postfinancecheckout',
            showPostFinanceCheckoutCompletion
        );
        $('#postfinancecheckout_completion_submit').off('click.postfinancecheckout').on(
            'click.postfinancecheckout',
            executePostFinanceCheckoutCompletion
        );
    }
    
    function showPostFinanceCheckoutInformationSuccess(msg)
    {
        showPostFinanceCheckoutInformation(msg, postfinancecheckout_msg_general_title_succes, postfinancecheckout_btn_info_confirm_txt, 'dark_green', function () {
            window.location.replace(window.location.href);});
    }
    
    function showPostFinanceCheckoutInformationFailures(msg)
    {
        showPostFinanceCheckoutInformation(msg, postfinancecheckout_msg_general_title_error, postfinancecheckout_btn_info_confirm_txt, 'dark_red', function () {
            window.location.replace(window.location.href);});
    }
    
    function showPostFinanceCheckoutInformation(msg, title, btnText, theme, callback)
    {
        $.jAlert({
            'type': 'modal',
            'title': title,
            'content': msg,
            'theme': theme,
            'replaceOtherAlerts': true,
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': btnText,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': callback
            }
            ],
            'onClose': callback
        });
    }
    
    function updatePostFinanceCheckout()
    {
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    postFinanceCheckoutUpdateUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    location.reload();
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformation(response.message, msg_postfinancecheckout_confirm_txt);
                    }
                    return;
                }
                showPostFinanceCheckoutInformation(postfinancecheckout_msg_general_error, msg_postfinancecheckout_confirm_txt);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformation(postfinancecheckout_msg_general_error, msg_postfinancecheckout_confirm_txt);
            }
        });
    }
    
        
    function showPostFinanceCheckoutVoid(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': postfinancecheckout_void_title,
            'content': $('#postfinancecheckout_void_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': postfinancecheckout_void_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': postfinancecheckout_void_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick':  executePostFinanceCheckoutVoid

            }
            ],
            'theme':'blue'
        });
        return false;
    }

    function executePostFinanceCheckoutVoid()
    {
        showPostFinanceCheckoutSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    postFinanceCheckoutVoidUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showPostFinanceCheckoutInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformationFailures(response.message);
                        return;
                    }
                }
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            }
        });
        return false;
    }
    
    
    function showPostFinanceCheckoutSpinner()
    {
        $.jAlert({
            'type': 'modal',
            'title': false,
            'content': '<div class="postfinancecheckout-loader"></div>',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'class': 'unnoticeable',
            'replaceOtherAlerts': true
        });
    
    }
    
    function showPostFinanceCheckoutCompletion(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': postfinancecheckout_completion_title,
            'content': $('#postfinancecheckout_completion_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': postfinancecheckout_completion_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': postfinancecheckout_completion_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': executePostFinanceCheckoutCompletion
            }
            ],
            'theme':'blue'
        });

        return false;
    }
    
    
    function executePostFinanceCheckoutCompletion()
    {
        showPostFinanceCheckoutSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    postFinanceCheckoutCompletionUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showPostFinanceCheckoutInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformationFailures(response.message);
                        return;
                    }
                }
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            }
        });
        return false;
    }
    
    function postFinanceCheckoutTotalRefundChanges()
    {
        var generateDiscount =  $('.standard_refund_fields').find('#generateDiscount').attr("checked") === 'checked';
        var sendOffline = $('#postfinancecheckout_refund_offline_cb_total').attr("checked") === 'checked';
        postFinanceCheckoutRefundChanges('total', generateDiscount, sendOffline);
    }
    
    function postFinanceCheckoutPartialRefundChanges()
    {
    
        var generateDiscount = $('.partial_refund_fields').find('#generateDiscountRefund').attr("checked") === 'checked';
        var sendOffline = $('#postfinancecheckout_refund_offline_cb_partial').attr("checked")  === 'checked';
        postFinanceCheckoutRefundChanges('partial', generateDiscount, sendOffline);
    }
    
    function postFinanceCheckoutRefundChanges(type, generateDiscount, sendOffline)
    {
        if (generateDiscount) {
            $('#postfinancecheckout_refund_online_text_'+type).css('display','none');
            $('#postfinancecheckout_refund_offline_span_'+type).css('display','block');
            if (sendOffline) {
                $('#postfinancecheckout_refund_offline_text_'+type).css('display','block');
                $('#postfinancecheckout_refund_no_text_'+type).css('display','none');
            } else {
                $('#postfinancecheckout_refund_no_text_'+type).css('display','block');
                $('#postfinancecheckout_refund_offline_text_'+type).css('display','none');
            }
        } else {
            $('#postfinancecheckout_refund_online_text_'+type).css('display','block');
            $('#postfinancecheckout_refund_no_text_'+type).css('display','none');
            $('#postfinancecheckout_refund_offline_text_'+type).css('display','none');
            $('#postfinancecheckout_refund_offline_span_'+type).css('display','none');
            $('#postfinancecheckout_refund_offline_cb_'+type).attr('checked', false);
        }
    }
    
    function handlePostFinanceCheckoutLayoutChanges()
    {
        var addVoucher = $('#add_voucher');
        var addProduct = $('#add_product');
        var editProductChangeLink = $('.edit_product_change_link');
        var descOrderStandardRefund = $('#desc-order-standard_refund');
        var standardRefundFields = $('.standard_refund_fields');
        var partialRefundFields = $('.partial_refund_fields');
        var descOrderPartialRefund = $('#desc-order-partial_refund');

        if ($('#postfinancecheckout_is_transaction').length > 0) {
            addVoucher.remove();
        }
        if ($('#postfinancecheckout_remove_edit').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            $('.panel-vouchers').find('i.icon-minus-sign').closest('a').remove();
        }
        if ($('#postfinancecheckout_remove_cancel').length > 0) {
            descOrderStandardRefund.remove();
        }
        if ($('#postfinancecheckout_changes_refund').length > 0) {
            $('#refund_total_3').closest('div').remove();
            standardRefundFields.find('div.form-group').after($('#postfinancecheckout_refund_online_text_total'));
            standardRefundFields.find('div.form-group').after($('#postfinancecheckout_refund_offline_text_total'));
            standardRefundFields.find('div.form-group').after($('#postfinancecheckout_refund_no_text_total'));
            standardRefundFields.find('#spanShippingBack').after($('#postfinancecheckout_refund_offline_span_total'));
            standardRefundFields.find('#generateDiscount').off('click.postfinancecheckout').on('click.postfinancecheckout', postFinanceCheckoutTotalRefundChanges);
            $('#postfinancecheckout_refund_offline_cb_total').on('click.postfinancecheckout', postFinanceCheckoutTotalRefundChanges);
        
            $('#refund_3').closest('div').remove();
            partialRefundFields.find('button').before($('#postfinancecheckout_refund_online_text_partial'));
            partialRefundFields.find('button').before($('#postfinancecheckout_refund_offline_text_partial'));
            partialRefundFields.find('button').before($('#postfinancecheckout_refund_no_text_partial'));
            partialRefundFields.find('#generateDiscountRefund').closest('p').after($('#postfinancecheckout_refund_offline_span_partial'));
            partialRefundFields.find('#generateDiscountRefund').off('click.postfinancecheckout').on('click.postfinancecheckout', postFinanceCheckoutPartialRefundChanges);
            $('#postfinancecheckout_refund_offline_cb_partial').on('click.postfinancecheckout', postFinanceCheckoutPartialRefundChanges);
        }
        if ($('#postfinancecheckout_completion_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#postfinancecheckout_void_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#postfinancecheckout_refund_pending').length > 0) {
            descOrderStandardRefund.remove();
            descOrderPartialRefund.remove();
        }
        movePostFinanceCheckoutDocuments();
        movePostFinanceCheckoutActionsAndInfo();
    }
    
    function init()
    {
        handlePostFinanceCheckoutLayoutChanges();
        registerPostFinanceCheckoutActions();
        initialiseDocumentButtons();
        hideIsWPaymentColumn();
    }
    
    init();
});
