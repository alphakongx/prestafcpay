<!--
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
 -->

<div class="row" id="fcfpay_configurations">
    <table id="payment_grid" class="table">
        <thead>
        <tr class="headings">
            <th><span style="color: red;
                font-size: 14px;
                line-height: 12px;
                position: relative;">*</span>{l s='Payment Method' mod='tafcfpay'}</th>
            <th>{l s='Payment Currency' mod='tafcfpay'}</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
            <tr id="0">
                <td><input type="text" class="input-text" value="{$og_payment}" name="TAFCFPAY_OG_PAYMENT">
                <p class="help-block">Refer to Documentation</p>
                </td>
                <td><input type="text" class="input-text" value="{$og_currency}" name="TAFCFPAY_OG_CURRENCY">
                    <p class="help-block"> Leave this field blank when you use "all" parameters in payment method</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<div class="row" id="custom_payment_configurations">
    <table id="custom_payment_grid" class="table">
        <thead>
        <tr class="headings">
            <th><span style="color: red;
                font-size: 14px;
                line-height: 12px;
                position: relative;">*</span>{l s='Payment Method' mod='tafcfpay'}</th>
            <th><span style="color: red;
                font-size: 14px;
                line-height: 12px;
                position: relative;">*</span>{l s='Channel Code' mod='tafcfpay'}</th>
            <th><span style="color: red;
                font-size: 14px;
                line-height: 12px;
                position: relative;">*</span>{l s='Currency Code' mod='tafcfpay'}</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        {if empty($custom_payments)}
            <tr id="0">
                <td><input type="text" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[1][name]"></td>
                <td><input type="text" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[1][code]"></td>
                <td><input type="text" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[1][currency]"></td>
                <td></td>
            </tr>
        {else}
            {foreach $custom_payments as $key => $value}
                <tr id="{$key|escape:'htmlall':'UTF-8'}">
                    <td>
                        <input type="text" value="{$value['name']|escape:'htmlall':'UTF-8'}" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[{$key|escape:'htmlall':'UTF-8'}][name]">
                    </td>
                <td>
                        <input type="text" value="{$value['code']|escape:'htmlall':'UTF-8'}" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[{$key|escape:'htmlall':'UTF-8'}][code]">
                    </td>
                    <td>
                        <input type="text" value="{$value['currency']|escape:'htmlall':'UTF-8'}" class="input-text" name="TAFCFPAY_CUSTOM_METHODS[{$key|escape:'htmlall':'UTF-8'}][currency]">

                    </td>
                    {if $key > 1}
                        <td><button class='btn btn-danger' onclick='deleteRow(this)'><span><i class='icon-eraser'></i></span></button></td>
                    {/if}
                </tr>
            {/foreach}
        {/if}
        </tbody>
    </table>
    <table id ="addrow" class="table">
        <tr id=""><td colspan="2" class="col-lg-8"></td>
            <td style="text-align: right;">
                <button class="btn btn-primary" type="button" onclick=
                "addNewPaymentConfiguration()">
                    <i class="icon-plus-sign-alt"></i>
                    <span>{l s='' mod='tafcfpay'}</span>
                </button>
            </td></tr>
        <tr><td colspan="4"><span id="errr-msg" style="color: red;"></span></td></tr>
    </table>
</div>
