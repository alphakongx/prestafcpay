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

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__) . '/tafcfpay.php');

$db = Db::getInstance();
$id_order_state = 0;
//Receive the RAW post data.
$content = trim(file_get_contents("php://input"));

//Attempt to decode the incoming RAW post data from JSON.
$decoded = json_decode($content, true);
if(is_array($decoded)) {
    if(isset($decoded['success']) && $decoded['success']) {
        $data = $decoded['data'];
        $order_reference = $data['order_id'];
        $updatedAt = date('Y-m-d H:i:s');
        $id_order = (int)$db->getValue("SELECT `id_order` FROM `"._DB_PREFIX_."orders` WHERE `reference`='".pSQL($order_reference)."'");
        if($id_order) {
            $exist = (int)$db->getValue("SELECT `id` FROM `"._DB_PREFIX_."tafcfpay_orders` WHERE `order_reference`='".pSQL($order_reference)."'");
            if($exist) {
                $db->update(
                    "tafcfpay_orders",
                    array(
                        'unique_id' => pSQL($data['unique_id']),
                        'transaction_id' => pSQL($data['txid']),
                        'status' => pSQL($data['deposited']),
                        'data' => pSQL(json_encode($data)),
                        'updated_at' => pSQL($updatedAt),
                    ),
                    'order_reference="'.pSQL($order_reference).'"'
                );
            } else {
                $db->insert(
                   'tafcfpay_orders',
                    array(
                        'unique_id' => pSQL($data['unique_id']),
                        'transaction_id' => pSQL($data['txid']),
                        'id_order' => (int)$id_order,
                        'order_reference' => pSQL($order_reference),
                        'status' => pSQL($data['deposited']),
                        'data' => pSQL(json_encode($data)),
                        'updated_at' => pSQL($updatedAt),
                    )
                );
            }
            if(trim($data['deposited'] == 'true')) {
                $id_order_state = Configuration::get('TAFCFPAY_SUCCESS_STATUS');
            } else {
                $id_order_state = Configuration::get('TAFCFPAY_FAILED_STATUS');
            }
            try {
                $order = new Order($id_order);
                $order_state = new OrderState($id_order_state);
                $history = new OrderHistory();
                $history->id_order = $id_order;
                $use_existings_payment = !$order->hasInvoice();
                $history->changeIdOrderState((int)$order_state->id, (int)$id_order, $use_existings_payment);
                $history->add(true);
            } catch (\Exception $e) {
                $error[] = $e->getMessage();
            }

        }


    }
}




Db::getInstance()->insert(
    "tafcfpay_orders",
    array(
        'data' => json_encode(array(
            't_data' =>$v,
            'p_data' => $p
        ))
    )
);
die('dff');

$context = Context::getContext();

if (Tools::getIsset('trackid') && Tools::getIsset('Hash')) {
    $result = Tools::getValue('result');
    $tafcfpay = new tafcfpay();
    $track_id = Tools::getValue('trackid');
    $cart_id = $tafcfpay->getCartIdByTrackId($track_id);


    if ($cart_id) {
        try {
            $db = Db::getInstance();
            $cart = new Cart($cart_id);
            $secretkey = trim(Configuration::get('TAFCFPAY_SECRET_KEY'));
            $hash = Tools::getValue('Hash');
            $refid = Tools::getValue('refid');
            $outParams = "trackid=".$track_id."&result=".$result."&refid=".$refid;
            $outHash = Tools::strtoupper(hash_hmac("sha256", $outParams, $secretkey));
            if ($hash == $outHash) {
                if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                    $id_order = (int)Order::getIdByCartId($cart->id);
                } else {
                    $id_order = (int)Order::getOrderByCartId($cart->id);
                }

                if ($result == 'CAPTURED') {
                    $status = Configuration::get('TAFCFPAY_SUCCESS_STATUS');
                } else {
                    $status = Configuration::get('TAFCFPAY_FAILED_STATUS');
                }


                $customer = new Customer((int)$cart->id_customer);
                if (!$id_order) {
                    $currency = $context->currency;

                    $tafcfpay->validateOrder($cart_id, (int) $status, $cart->getOrderTotal(true, Cart::BOTH), $tafcfpay->displayName.'-'.$track_id, null, array(), (int)$currency->id, false, $customer->secure_key);
                    $order = new Order($tafcfpay->currentOrder);
           

                    $data = array(
                       'track_id' => pSQL($track_id),
                       'transaction_id' => pSQL($refid),
                       'id_order' => $tafcfpay->currentOrder,
                       'updated_at' => pSQL(date('Y-m-d h:m:s')),
                    );
                    $db->insert('tafcfpay_orders', $data);
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $tafcfpay->id . '&id_order=' . $tafcfpay->currentOrder . '&key=' . $customer->secure_key);
                } else {
                    $order_obj = new OrderCore($id_order);
                    $os = $order_obj->current_state;
                    if ($os != $status) {
                        $history = new OrderHistory();
                        $history->id_order = $id_order;
                        $history->changeIdOrderState($status, $id_order);
                        $history->save();
                    }
                    $db->delete(
                        'tafcfpay_cart',
                        'id_customer = ' . $cart->id_customer . ' AND id_shop = ' . $context->shop->id
                    );
                    $data = array(
                       'track_id' => pSQL($track_id),
                       'transaction_id' => pSQL($refid),
                       'id_order' => $id_order,
                       'updated_at' => pSQL(date('Y-m-d h:m:s')),
                    );
                    $exist = $db->getValue("SELECT `track_id` FROM `"._DB_PREFIX_."tafcfpay_orders` 
                    WHERE `id_order`='".$id_order."'");
                    if (!empty($exist)) {
                        $db->update('tafcfpay_orders', $data, 'id_order="'.(int)$id_order.'"');
                    } else {
                        $db->insert('tafcfpay_orders', $data);
                    }

                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $tafcfpay->id . '&id_order=' . $id_order . '&key=' . $customer->secure_key);
                }
            } else {
                echo "Hash Not Matched for -".$track_id."<br/>";
                echo $hash."<br/>";
                echo $outHash;
                exit;
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
            print_r($e->getTraceAsString());
            die;
        }
    } else {
        die("Failed to get cart data");
    }
} else {
    die("Invalid url");
}
