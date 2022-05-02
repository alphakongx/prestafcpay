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

class TaFcfPayCalculateModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function __construct()
    {

        parent::__construct();
        global $kernel;
        if(!$kernel){
            require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
        try{
            if (Tools::getIsset('orderButtonClick') && Tools::getValue('orderButtonClick')) {

                $id_cart = (int)Tools::getValue('id_cart');
                $id_customer = (int)Tools::getValue('id_customer');
                $customer = new Customer($id_customer);
                $cart = new Cart($id_cart);
                $this->context->cart = $cart;
                $storeCurrency = new Currency($cart->id_currency);
                Context::getContext()->currency = $storeCurrency;
                $currency_code = $storeCurrency->iso_code;
                $currency_code_numeric = $storeCurrency->iso_code_num;
                $os = Configuration::get('TAFCFPAY_INITIAL_STATUS');
                $displayName = 'FcfPay';
                $update_date = date('Y-m-d');
                $mailVars =    array();
                $total = (int)$cart->getOrderTotal();
                $this->module->validateOrder((int)$this->context->cart->id, $os, $total, $displayName, null, $mailVars, (int)$storeCurrency->id, false, $this->context->customer->secure_key);
                $order = new Order($this->module->currentOrder);
                $total = $order->getTotalPaid();
                if(!empty($order->reference)) {
                    $domain = $this->context->shop->getBaseURL(true);
                    $order_reference = $order->reference;
                    $id_order = $order->id;
                    $callback = $this->context->shop->getBaseURL() . 'modules/tafcfpay/callback.php';
                    $redirect_url = $this->context->shop->getBaseURL().'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key;

                    $data = array(
                        'domain' => $domain,
                        'order_id' => $order_reference,
                        'user_id' => $id_customer,
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
                        die(json_encode(array('success' => true, 'message' => $response['data']['checkout_page_url'])));
                    } else {
                        $error = "";
                        if (isset($response['errorMessgae'])) {
                            $error = $response['errorMessgae'] . ". Please choose other payment method";
                        }
                        die(json_encode(array('success' => false, 'message' => $error)));
                    }
                }
            }
        } catch (\Exception $e) {
            die(json_encode(array('success' => false, 'message' => $e->getMessage())));
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
