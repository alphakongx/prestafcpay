<?php
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

/**
 * @since 1.5.0
 */
class TaFcfPayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'tafcfpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('This payment method is not available.', [], 'Modules.Checkpayment.Shop'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $storeCurrency = $this->context->currency;
        $currency_code = $storeCurrency->iso_code;
        $currency_code_numeric = $storeCurrency->iso_code_num;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $os = Configuration::get('TAFCFPAY_INITIAL_STATUS');
        $displayName = 'FcfPay';
        $update_date = date('Y-m-d');
        $mailVars =    array();


        try {
            $this->module->validateOrder(
                (int) $cart->id,
                (int) $os,
                $total,
                $displayName,
                null,
                $mailVars,
                (int) $storeCurrency->id,
                false,
                $customer->secure_key
            );
            $order = new Order($this->module->currentOrder);
            if(!empty($order->reference)) {
                $domain = $this->context->shop->getBaseURL(true);
                $order_reference = $order->reference;
                $id_order = $order->id;
                $callback = $this->context->shop->getBaseURL() . 'modules/tafcfpay/callback.php';
                $redirect_url = $this->context->shop->getBaseURL().'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key;

                $data = array(
                    'domain' => $domain,
                    'order_id' => $order_reference,
                    'user_id' => $customer->id,
                    'amount' => $total,
                    'currency_name' => $currency_code,
                    'currency_code' => $currency_code_numeric,
                    'order_date' => $update_date,
                    'redirect_url' => $redirect_url,
                );

                $request = json_encode($data, true);

                $mode = Configuration::get('TAFCFPAY_MODE');
                $api_key = Configuration::get('TAFCFPAY_API_KEY');
                if ($mode == 'live') {
                    $endpoint = 'https://merchant.fcfpay.com/api/v1/create-order';
                } else {
                    $endpoint = 'https://sandbox.fcfpay.com/api/v1/create-order';
                }
                $curl = curl_init($endpoint);

                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER,
                    array("Authorization: Bearer $api_key",
                        'Content-Type:application/json'
                    )
                );
                $ch = curl_exec($curl);
                curl_close($curl);

                $response = json_decode($ch, true);
                if (isset($response['success']) && $response['success']) {
                    $this->updateFcfOrder($id_order,$order_reference);
                    Tools::redirect($response['data']['checkout_page_url']);

                } else {
                    $error = "Failed to create order, Please choose other payment option";
                    if (isset($response['message'])) {
                        $error = "FcfPay Error : ".$response['message'];
                    }
                    die($error);
                }
            }
        } catch (\Exception $e) {
            if($order->id) {
                $order->delete();
            }
            die("Failed to create order ".$e->getMessage());
        }

    }
    public function updateFcfOrder($id_order, $order_reference = '')
    {
        $updatedAt = date('Y-m-d H:i:s');
        $db = Db::getInstance();
        $exist = (int)$db->getValue("SELECT `id` FROM `"._DB_PREFIX_."tafcfpay_orders` WHERE `order_reference`='".pSQL($order_reference)."'");
        if($exist) {
            $db->update(
                "tafcfpay_orders",
                array(
                    'id_order' => (int)$id_order,
                    'updated_at' => pSQL($updatedAt),
                ),
                'order_reference="'.pSQL($order_reference).'"'
            );
        } else {
            $db->insert(
                'tafcfpay_orders',
                array(
                    'id_order' => (int)$id_order,
                    'order_reference' => pSQL($order_reference),
                    'updated_at' => pSQL($updatedAt),
                )
            );
        }
    }

}
