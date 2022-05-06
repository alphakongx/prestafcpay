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
class TaFcfPayCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $db = Db::getInstance();
//Receive the RAW post data.
        $content = trim(file_get_contents("php://input"));
        $t_content = Tools::getAllValues();
        $log_data = array(
            'p_input' => $content,
            't_input' => $t_content,
        );
        $tafcfpay = Module::getInstanceByName('tafcfpay');
        $tafcfpay->log($log_data);

//Attempt to decode the incoming RAW post data from JSON.
        $decoded = json_decode($content, true);
        if(is_array($decoded)) {
            if(isset($decoded['success']) && $decoded['success']) {
                $data = $decoded['data'];
                if($data['deposited'] =='true') {
                    $status = 'paid';
                } else {
                    $status = 'unpaid';
                }
                $order_reference = $data['order_id'];
                $updatedAt = date('Y-m-d H:i:s');
                $id_order = (int)$db->getValue("SELECT `id_order` FROM `"._DB_PREFIX_."orders` WHERE `reference`='".pSQL($order_reference)."'");
                if($id_order && $status == 'paid') {
                    $exist = (int)$db->getValue("SELECT `id` FROM `"._DB_PREFIX_."tafcfpay_orders` WHERE `order_reference`='".pSQL($order_reference)."'");
                    if($exist) {

                        $db->update(
                            "tafcfpay_orders",
                            array(
                                'unique_id' => pSQL($data['unique_id']),
                                'transaction_id' => pSQL($data['txid']),
                                'status' => pSQL($status),
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
                                'status' => pSQL($status),
                                'data' => pSQL(json_encode($data)),
                                'updated_at' => pSQL($updatedAt),
                            )
                        );
                    }
                    $id_order_state = Configuration::get('TAFCFPAY_SUCCESS_STATUS');
                    $tafcfpay->changeStatus($id_order,$id_order_state);
                    $order = new Order($id_order);
                    $op = new OrderPayment();
                    $op->order_reference = $order_reference;
                    $op->id_currency = $order->id_currency;
                    $op->amount = $order->total_paid;
                    $op->payment_method = "FcfPay";
                    $op->transaction_id = $data['txid'];
                    $op->add();
                }
            }
        }
        die('true');
    }
}
