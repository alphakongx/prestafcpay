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
class TaFcfPayCronModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {

        $result = [];
        $db = Db::getInstance();
        $tafcfpay = Module::getInstanceByName('tafcfpay');
        $unpaid_orders = $db->executeS("SELECT `id_order`,`order_reference` FROM `"._DB_PREFIX_."tafcfpay_orders` WHERE `status` != 'paid'");
        foreach ($unpaid_orders as $unpaid_order) {
            $order_ref = $unpaid_order['order_reference'];
            $order_id = $unpaid_order['id_order'];
            $request = array(
              'order_id' => $order_ref
            );
            $response = $tafcfpay->sendApiRequest(json_encode($request),'check-order');

            $tafcfpay = Module::getInstanceByName('tafcfpay');
            $tafcfpay->log($response);
            $id_order = (int)$db->getValue("SELECT `id_order` FROM `"._DB_PREFIX_."orders` WHERE `reference`='".pSQL($order_ref)."'");
            if($id_order) {
                $order = new Order($id_order);
                if(isset($response['success']) && $response['success']) {
                    $data = $response['data'];
//                    $data['deposited'] = 'true';
                    if($data['deposited'] =='true') {
                        $status = 'paid';
                    } else {
                        $status = 'unpaid';
                        $result['error'][] = "Order id $id_order is not paid yet";
                    }
                    $order_reference = $data['order_id'];
                    $updatedAt = date('Y-m-d H:i:s');
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
                        if(trim($status == 'paid')) {
                            $id_order_state = Configuration::get('TAFCFPAY_SUCCESS_STATUS');
                            $tafcfpay->changeStatus($id_order,$id_order_state);
                            $op = new OrderPayment();
                            $op->order_reference = $order_ref;
                            $op->id_currency = $order->id_currency;
                            $op->amount = $order->total_paid;
                            $op->payment_method = "FcfPay";
                            $op->transaction_id = $data['txid'];
                            $op->add();
                            $result['success'][] = "Order $id_order is updated with paid status";
                        }

                } else {
                    $id_order_state = Configuration::get('TAFCFPAY_FAILED_STATUS');
                    $tafcfpay->changeStatus($id_order,$id_order_state);
                    $result['error'][] = "Order $id_order is  updated with failed status";
                }
            } else {
                $d = array('error' => "$order_ref does not exist in prestashop");
                $tafcfpay->log($d);
            }


        }
        die(json_encode($result));
    }

}
