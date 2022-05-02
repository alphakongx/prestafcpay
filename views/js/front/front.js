/**
 *  Copyright (C) TA - All Rights Reserved
 *
 *  Unauthorized copying and editing of this file is strictly prohibited
 *  Proprietary and confidential
 *
 *  @author    TA
 *  @copyright 2020-2022 TA
 *  @license   Commercial
 */

$(document).ready(function () {
    var el = $('#checkout-payment-step').children('div.content').children('div.payment-options').find('span.custom-radio');
    var parent_div = el.parent();

    if ($(parent_div).hasClass('payment-option')) {
        var label_val = $(parent_div).children('label').children('span').text();
        var n1 = label_val.toLowerCase().search('fcfpay');

        if (n1 > 0) {
            $(parent_div).children('label').children('img').attr('height',"30px;");
        }
    }





    if ($('body#checkout') && $('#fcfpay_form').length > 0) {
        var listener = function (event) {
            event.stopPropagation();
            event.preventDefault();
            var ajax_url = $("#fcfpay_ajax_url").val();

            $('#payment-confirmation').find('button')[0].disabled=true;
            $.ajax({
                method: "POST",
                url: ajax_url,
                dataType: 'json',
                data: {
                    orderButtonClick: true,
                    id_shop: id_shop,
                    id_cart: id_cart,
                    id_customer: id_customer
                }
            })
                .success(function ( res ) {
                    if (res.success == true) {
                        document.location.href = res.message;
                    } else {
                        alert(res.message);
                    }
                });
        };

        $('#checkout-payment-step').children('div.content').children('div.payment-options').find('span.custom-radio').children('input[name="payment-option"]').on('click', function () {
            var parent_div = $(this).parent().parent();

            if ($(parent_div).hasClass('payment-option')) {
                var label_val = $(parent_div).children('label').children('span').text();
                var n1 = label_val.toLowerCase().search('fcfpay');
                var module_name = $(this).attr('data-module-name');
                console.log(n1)

                if (n1 > 0) {
                    $('#payment-confirmation').find('button')[0].addEventListener('click', listener);
                } else {
                    $('#payment-confirmation').find('button')[0].removeEventListener('click', listener);
                }
            }
        });
    }
})

