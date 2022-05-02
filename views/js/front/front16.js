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

function redirectToFcfPay(code)
{
    var ajax_url = $("#fcfpay_ajax_url").val();
    var id_shop = $("#id_shop").val();
    var id_cart = $("#id_cart").val();
    var id_customer = $("#id_customer").val();
    $.ajax({
        method: "POST",
        url: ajax_url,
        dataType: 'json',
        data: {
            orderButtonClick: true,
            id_shop: id_shop,
            payment_code: code,
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
}