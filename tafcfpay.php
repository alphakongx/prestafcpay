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

if (!defined('_PS_VERSION_')) {
    exit;
}

class TaFcfPay extends PaymentModule
{
    private $adminTabs = array();

    public function __construct()
    {
        $this->name = 'tafcfpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'FcfPay';
        $this->secure_key = Tools::encrypt($this->name);
        $this->controllers = array('payment', 'validation');

        $this->bootstrap = true;
        parent::__construct();

        $this->adminTabs = array(
            array(
                'class' => 'AdminTaFcfPay',
                'label' => $this->l('FcfPay'),
                'is_parent' => true
            )
        );

        $this->displayName = $this->l('Pay with FcfPay');
        $this->description = $this->l('Pay with FcfPay');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall ?');
        $this->_path = _PS_MODULE_DIR_ . 'tafcfpay/';
    }

    public function install()
    {
        if (parent::install()
            && $this->installDBTables()
            && $this->installTabs()
            && $this->registerHook('payment')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('header')
            && $this->registerHook('displayBackOfficeHeader')
        ) {
            return true;
        }
        return false;
    }

    public function uninstall()
    {
        if (parent::uninstall()
            && $this->uninstallTabs()
            && $this->uninstallDBTables()
            && $this->unregisterHook('payment')
            && $this->unregisterHook('paymentReturn')
            && $this->unregisterHook('paymentOptions')
            && $this->unregisterHook('header')
            && $this->unregisterHook('displayBackOfficeHeader')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Add tabs in backoffice
     *
     * @return bool
     */
    public function installTabs()
    {
        $res = true;
        foreach ($this->adminTabs as $adminTab) {
            $tab = new Tab();
            $tab->name = array();
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $adminTab['label'];
            }
            $tab->class_name = $adminTab['class'];
            $tab->module = $this->name;
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentPayment');
            } else {
                $tab->id_parent = 0;
            }

            $tab->icon = 'payment';
            $tab->add();
        }
        return $res;
    }

    /**
     * Remove tabs from backoffice
     *
     * @return bool
     */
    public function uninstallTabs()
    {
        $res = true;
        foreach ($this->adminTabs as $adminTab) {
            $id_tab = (int)Tab::getIdFromClassName($adminTab['class']);
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        return $res;
    }

    /**
     * getContent()
     *
     * @return mixed
     */
    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('savetafcfpaysetting')) {
            $fields = $this->getFormValues();

            foreach (array_keys($fields) as $key) {
                if (Tools::getIsset($key)) {
                    $value = Tools::getValue($key);
                    Configuration::updateValue($key, $value);
                }
            }

            $output .= $this->displayConfirmation($this->l("Saved successfully"));
        }
        $output .= $this->renderForm();
        return $output;
    }

    protected function renderForm()
    {
        $def_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $order_states = Db::getInstance()->ExecuteS("SELECT `id_order_state`,`name` 
       FROM `" . _DB_PREFIX_ . "order_state_lang` where `id_lang` = '" . (int)$def_lang . "'");
        $custom_payments =  array();
        $custom_p = json_decode(Configuration::get('TAFCFPAY_CUSTOM_METHODS'), true);
        if (is_array($custom_p)) {
            $custom_payments = $custom_p;
        }

        $modes = array(
          array(
              'value' => 'staging',
              'label' => 'Test Mode',
          ),
          array(
              'value' => 'live',
              'label' => 'Live Mode',
          ),
        );

        $fields_form = array(
            'tinymce' => true,
            'legend' => array(
                'title' => $this->l('Setting block'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'col' => 6,
                    'label' => $this->l('Mode'),
                    'hint' => $this->l('Api Mode'),
                    'desc' => $this->l('Api Mode'),
                    'name' => 'TAFCFPAY_MODE',
                    'required' => false,
                    'default_value' => '',
                    'options' => array(
                        'query' => $modes,
                        'id' => 'value',
                        'name' => 'label',
                    )
                ),
                array(
                    'col' => 6,
                    'type' => 'text',
                    'required' => true,
                    'label' => $this->l('Api Key'),
                    'hint' => $this->l('Api Key'),
                    'name' => 'TAFCFPAY_API_KEY',
                    'desc' => $this->l('Api Key'),
                ),
                array(
                    'type' => 'select',
                    'col' => 8,
                    'label' => $this->l('Initial Order status'),
                    'hint' => $this->l('Order status for order when payment is initiated'),
                    'desc' => $this->l('Order status for order when payment is initiated'),
                    'name' => 'TAFCFPAY_INITIAL_STATUS',
                    'required' => true,
                    'default_value' => '',
                    'options' => array(
                        'query' => $order_states,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    )
                ),
                array(
                    'type' => 'select',
                    'col' => 8,
                    'label' => $this->l('Success Order status'),
                    'hint' => $this->l('Order status for order when payment is successfull'),
                    'desc' => $this->l('Order status for order when payment is successfull'),
                    'name' => 'TAFCFPAY_SUCCESS_STATUS',
                    'required' => true,
                    'default_value' => '',
                    'options' => array(
                        'query' => $order_states,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    )
                ),
                array(
                    'type' => 'select',
                    'col' => 8,
                    'label' => $this->l('Failed Order status'),
                    'hint' => $this->l('Order status for order when payment is failed.'),
                    'desc' => $this->l('Order status for order when payment is failed.'),
                    'name' => 'TAFCFPAY_FAILED_STATUS',
                    'required' => true,
                    'default_value' => '',
                    'options' => array(
                        'query' => $order_states,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    )
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
            'buttons' => array(
                array(
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.
                        Tools::getAdminTokenLite('AdminModules'),
                    'title' => $this->l('Back'),
                    'icon' => 'process-icon-back'
                )
            )
        );


        $helper = new HelperForm();
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        foreach (Language::getLanguages(false) as $lang) {
            $helper->languages[] = array(
                'id_lang' => $lang['id_lang'],
                'iso_code' => $lang['iso_code'],
                'name' => $lang['name'],
                'is_default' => ($def_lang == $lang['id_lang'] ? 1 : 0)
            );
        }

        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $def_lang;
        $helper->allow_employee_form_lang = $def_lang;
        $helper->toolbar_scroll = true;
        $helper->title = $this->displayName;
        $helper->submit_action = 'savetafcfpaysetting';

        $helper->fields_value = $this->getFormValues();

        return $helper->generateForm(array(array('form' => $fields_form)));
    }


    public function getFormValues()
    {
        return array(
            'TAFCFPAY_MODE' => Configuration::get('TAFCFPAY_MODE') ?
                Configuration::get('TAFCFPAY_MODE') : 'staging',
            'TAFCFPAY_API_KEY' => Configuration::get('TAFCFPAY_API_KEY') ?
                Configuration::get('TAFCFPAY_API_KEY') : '',
            'TAFCFPAY_INITIAL_STATUS' => Configuration::get('TAFCFPAY_INITIAL_STATUS') ?
                Configuration::get('TAFCFPAY_INITIAL_STATUS') : '',
            'TAFCFPAY_SUCCESS_STATUS' => Configuration::get('TAFCFPAY_SUCCESS_STATUS') ?
                Configuration::get('TAFCFPAY_SUCCESS_STATUS') : '',
            'TAFCFPAY_FAILED_STATUS' => Configuration::get('TAFCFPAY_FAILED_STATUS') ?
                Configuration::get('TAFCFPAY_FAILED_STATUS') : '',

        );
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJquery();
        if ($this->context->controller->controller_name == 'AdminModules') {
            $this->context->controller->addJS($this->_path . 'views/js/admin/tafcfpay.js');
        }
    }



    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return '';
        }
        $active = true;

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            if ($active) {
                $payment_options = array();
                $type = 'all';
                if ($type == 'all') {
                    $call_to_action_text = 'Pay by FcfPay';

                    $payOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                    $payOption->setCallToActionText($call_to_action_text)
                        ->setModuleName('tafcfpay')
                        ->setLogo('https://checkout-sandbox.fcfpay.com/img/logo.svg')
//                        ->setAction($this->context->link->getModuleLink($this->name,
// 'validation', array('id_plan' => $plan['id_plan']), true))
//                        ->setAdditionalInformation($this->context->smarty->fetch($this->_path .
// 'views/templates/front/additional_information.tpl'))
                        ->setForm($this->generatePaymentForm())
                    ;

                    $payment_options[] = $payOption;
                }
                return $payment_options;
            } else {
                return '';
            }
        } else {
            if ($active) {
                $methods = array();
                $type = 'all';
                if ($type == 'all') {
                    $method = array(
                      'name' => 'Pay by FcfPay',
                      'logo' => 'https://checkoutadmin.oneglobal.com/pgimages/fcfpay.png',
                      'payment_code' => 'all',
                    );
                    $methods[] = $method;
                }


                $cart = $this->context->cart;

                $this->smarty->assign(array(
                    'methods' => $methods,
                    'id_cart' => (int)$cart->id,
                    'fcfpay_ajax_url' => $this->context->link->getModuleLink('tafcfpay', 'calculate'),
                    'id_shop' => (int)$this->context->shop->id,
                    'id_customer' => (int)$this->context->customer->id,
                ));

                return $this->display($this->name, 'payment_16.tpl');
            }
        }
    }

    private function generatePaymentForm()
    {
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal();


        $this->context->smarty->assign(array(
            'cart_id' => (int)$cart->id,
            'price' => $total,
            'email' => $this->context->customer->email,
            'fcfpay_ajax_url' => $this->context->link->getModuleLink('tafcfpay', 'calculate'),
            'shop_id' => (int)$this->context->shop->id,
            'customer_id' => (int)$this->context->customer->id
        ));

        $toReturn = $this->context->smarty->fetch('module:tafcfpay/views/templates/front/payment_form.tpl');

        return $toReturn;
    }

    public function hookPayment($params)
    {
        return $this->hookPaymentOptions($params);
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        return '';

//        return $this->display($this->name, 'confirmation.tpl');
    }

    public function hookHeader($params)
    {

        if ($this->context->controller->php_self == 'order') {
            $this->clearTableCart();
        }

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->context->controller->addJS(($this->_path) . 'views/js/front/front.js');
        } else {
            $this->context->controller->addJS(($this->_path) . 'views/js/front/front16.js');
        }
    }

    private function clearTableCart()
    {

        $id_shop = (int) $this->context->shop->id;
        $id_customer = (int) $this->context->customer->id;

        Db::getInstance()->delete('tafcfpay_cart', 'id_shop = '.$id_shop.
            ' AND id_customer = '.$id_customer);
    }

    public function installDBTables()
    {
        $db = Db::getInstance();
        return $db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'tafcfpay_orders` (
            `id` int(15) NOT NULL auto_increment,
            `unique_id` varchar(150) NOT NULL,
            `transaction_id` varchar(150) NOT NULL,
            `id_order` int(15) NOT NULL,
            `order_reference` varchar(150),
            `status` varchar(150),
            `data` text,
            `updated_at` datetime,
            PRIMARY KEY (`id`)
              )');
    }

    public function uninstallDBTables()
    {
        return Db::getInstance()->execute("DROP TABLE IF EXISTS `"._DB_PREFIX_."tafcfpay_orders`");
    }
}
