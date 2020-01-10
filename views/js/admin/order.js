/**
 * PostFinance Checkout Prestashop
 *
 * This Prestashop module enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2020 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {
    
    function movePostFinanceCheckoutDocuments()
    {
        var parentElement = $("#postfinancecheckout_documents_tab").parent();
        $("#postfinancecheckout_documents_tab").detach().appendTo(parentElement);
    }
    
    function movePostFinanceCheckoutActionsAndInfo()
    {
        $("a.postfinancecheckout-management-btn").each(function (key, element) {
            $(element).detach();
            $("div.panel").find("div.well.hidden-print").find("i.icon-print").closest("div.well").append(element);
        });
        $("span.postfinancecheckout-management-info").each(function (key, element) {
            $(element).detach();
            $("div.panel").find("div.well.hidden-print").find("i.icon-print").closest("div.well").append(element);
        });
    //to get the styling of prestashop we have to add this
        $("a.postfinancecheckout-management-btn").after("&nbsp;\n");
        $("span.postfinancecheckout-management-info").after("&nbsp;\n");
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
            'closeBtn': false,
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
                if ( response.success == 'true' ) {
                    location.reload();
                    return;
                } else if ( response.success == 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformation(response.message, msg_postfinancecheckout_confirm_txt);
                    }
                    return;
                }
                showPostFinanceCheckoutInformation(postfinancecheckout_msg_general_error, msg_postfinancecheckout_confirm_txt);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformation(postfinancecheckout_msg_general_error, msg_postfinancecheckout_confirm_txt);
            },
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
                'theme': 'black',
            },
            {
                'text': postfinancecheckout_void_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick':  executePostFinanceCheckoutVoid

            }
            ],
            'theme':'blue',
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
                if ( response.success == 'true' ) {
                    showPostFinanceCheckoutInformationSuccess(response.message);
                    return;
                } else if ( response.success == 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformationFailures(response.message);
                        return;
                    }
                }
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
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
                'theme': 'black',
            },
            {
                'text': postfinancecheckout_completion_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': executePostFinanceCheckoutCompletion
            }
            ],
            'theme':'blue',
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
                if ( response.success == 'true' ) {
                    showPostFinanceCheckoutInformationSuccess(response.message);
                    return;
                } else if ( response.success == 'false' ) {
                    if (response.message) {
                        showPostFinanceCheckoutInformationFailures(response.message);
                        return;
                    }
                }
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showPostFinanceCheckoutInformationFailures(postfinancecheckout_msg_general_error);
            },
        });
        return false;
    }
    
    function postFinanceCheckoutTotalRefundChanges()
    {
        var generateDiscount =  $('.standard_refund_fields').find('#generateDiscount').attr("checked") == 'checked';
        var sendOffline = $('#postfinancecheckout_refund_offline_cb_total').attr("checked") == 'checked';
        postFinanceCheckoutRefundChanges('total', generateDiscount, sendOffline);
    }
    
    function postFinanceCheckoutPartialRefundChanges()
    {
    
        var generateDiscount = $('.partial_refund_fields').find('#generateDiscountRefund').attr("checked") == 'checked';
        var sendOffline = $('#postfinancecheckout_refund_offline_cb_partial').attr("checked")  == 'checked';
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
        if ($('#postfinancecheckout_is_transaction').length > 0) {
            $('#add_voucher').remove();
        }
        if ($('#postfinancecheckout_remove_edit').length > 0) {
            $('#add_product').remove();
            $('#add_voucher').remove();
            $('.edit_product_change_link').closest('div').remove();
            $('.panel-vouchers').find('i.icon-minus-sign').closest('a').remove();
        }
        if ($('#postfinancecheckout_remove_cancel').length > 0) {
            $('#desc-order-standard_refund').remove();
        }
        if ($('#postfinancecheckout_changes_refund').length > 0) {
            $('#refund_total_3').closest('div').remove();
            $('.standard_refund_fields').find('div.form-group').after($('#postfinancecheckout_refund_online_text_total'));
            $('.standard_refund_fields').find('div.form-group').after($('#postfinancecheckout_refund_offline_text_total'));
            $('.standard_refund_fields').find('div.form-group').after($('#postfinancecheckout_refund_no_text_total'));
            $('.standard_refund_fields').find('#spanShippingBack').after($('#postfinancecheckout_refund_offline_span_total'));
            $('.standard_refund_fields').find('#generateDiscount').off('click.postfinancecheckout').on('click.postfinancecheckout', postFinanceCheckoutTotalRefundChanges);
            $('#postfinancecheckout_refund_offline_cb_total').on('click.postfinancecheckout', postFinanceCheckoutTotalRefundChanges);
        
            $('#refund_3').closest('div').remove();
            $('.partial_refund_fields').find('button').before($('#postfinancecheckout_refund_online_text_partial'));
            $('.partial_refund_fields').find('button').before($('#postfinancecheckout_refund_offline_text_partial'));
            $('.partial_refund_fields').find('button').before($('#postfinancecheckout_refund_no_text_partial'));
            $('.partial_refund_fields').find('#generateDiscountRefund').closest('p').after($('#postfinancecheckout_refund_offline_span_partial'));
            $('.partial_refund_fields').find('#generateDiscountRefund').off('click.postfinancecheckout').on('click.postfinancecheckout', postFinanceCheckoutPartialRefundChanges);
            $('#postfinancecheckout_refund_offline_cb_partial').on('click.postfinancecheckout', postFinanceCheckoutPartialRefundChanges);
        }
        if ($('#postfinancecheckout_completion_pending').length > 0) {
            $('#add_product').remove();
            $('#add_voucher').remove();
            $(".edit_product_change_link").closest('div').remove();
            $('#desc-order-partial_refund').remove();
            $('#desc-order-standard_refund').remove();
        }
        if ($('#postfinancecheckout_void_pending').length > 0) {
            $('#add_product').remove();
            $('#add_voucher').remove();
            $(".edit_product_change_link").closest('div').remove();
            $('#desc-order-partial_refund').remove();
            $('#desc-order-standard_refund').remove();
        }
        if ($('#postfinancecheckout_refund_pending').length > 0) {
            $('#desc-order-standard_refund').remove();
            $('#desc-order-partial_refund').remove();
        }
        movePostFinanceCheckoutDocuments();
        movePostFinanceCheckoutActionsAndInfo();
    }
    
    function init()
    {
        handlePostFinanceCheckoutLayoutChanges();
        registerPostFinanceCheckoutActions();
    }
    
    init();
});
