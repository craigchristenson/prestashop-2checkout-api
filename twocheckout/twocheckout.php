<?php

if (!defined('_PS_VERSION_'))
    exit;

class Twocheckout extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'twocheckout';
        $this->displayName = '2Checkout Payment API';
        $this->tab = 'payments_gateways';
        $this->version = 0.1;


        $config = Configuration::getMultiple(array('TWOCHECKOUT_SID', 'TWOCHECKOUT_PUBLIC', 'TWOCHECKOUT_PRIVATE',
            'TWOCHECKOUT_SANDBOX', 'TWOCHECKOUT_CURRENCIES'));

        if (isset($config['TWOCHECKOUT_SID']))
            $this->SID = $config['TWOCHECKOUT_SID'];
        if (isset($config['TWOCHECKOUT_PUBLIC']))
            $this->PUBLIC = $config['TWOCHECKOUT_PUBLIC'];
        if (isset($config['TWOCHECKOUT_PRIVATE']))
            $this->PRIVATE = $config['TWOCHECKOUT_PRIVATE'];
        if (isset($config['TWOCHECKOUT_SANDBOX']))
            $this->SANDBOX = $config['TWOCHECKOUT_SANDBOX'];
        if (isset($config['TWOCHECKOUT_CURRENCIES']))
            $this->currencies = $config['TWOCHECKOUT_CURRENCIES'];

        parent::__construct();

        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->description = $this->l('Accept payments using 2Checkout Payment API');

        if (!isset($this->SID) OR !isset($this->currencies))
            $this->warning = $this->l('your 2Checkout vendor account number must be configured in order to use this module correctly');

        if (!Configuration::get('TWOCHECKOUT_CURRENCIES'))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                    $authorized_currencies[] = $currency['id_currency'];
            Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        }
    }


    function install()
    {
        //Call PaymentModule default install function
        $install = parent::install() && $this->registerHook('payment') && $this->registerHook('header') && $this->registerHook('orderConfirmation');
        //Create Valid Currencies
        $currencies = Currency::getCurrencies();
        $authorized_currencies = array();
        foreach ($currencies as $currency)
        $authorized_currencies[] = $currency['id_currency'];
        Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        $this->registerHook('displayMobileHeader');
        return $install;
    }


    function uninstall()
    {
        Configuration::deleteByName('TWOCHECKOUT_SID');
        Configuration::deleteByName('TWOCHECKOUT_PUBLIC');
        Configuration::deleteByName('TWOCHECKOUT_PRIVATE');
        Configuration::deleteByName('TWOCHECKOUT_SANDBOX');
        Configuration::deleteByName('TWOCHECKOUT_CURRENCIES');
        return $this->unregisterHook('payment') && $this->unregisterHook('paymentReturn') && parent::uninstall();
    }


    public function hookDisplayMobileHeader()
    {
        return $this->hookHeader();
    }


    public function hookHeader()
    {
        if (Tools::getValue('controller') != 'order-opc' && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php' || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php' || Tools::getValue('controller') == 'order' || Tools::getValue('controller') == 'orderopc' || Tools::getValue('step') == 3)))
            return;

        if (Configuration::get('TWOCHECKOUT_SANDBOX')) {
            $output = '<script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'"></script>';
        } else {
            $output = '
            <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'"></script>';
        }

        $this->smarty->assign('twocheckout_sid', Configuration::get('TWOCHECKOUT_SID'));
        $this->smarty->assign('twocheckout_public_key', Configuration::get('TWOCHECKOUT_PUBLIC'));

        return $output;
    }


    function getContent()
    {
        if (isset($_POST['btnSubmit']))
        {
            Configuration::updateValue('TWOCHECKOUT_SID', $_POST['SID']);
            Configuration::updateValue('TWOCHECKOUT_PUBLIC', $_POST['PUBLIC']);
            Configuration::updateValue('TWOCHECKOUT_PRIVATE', $_POST['PRIVATE']);
            Configuration::updateValue('TWOCHECKOUT_SANDBOX', $_POST['SANDBOX']);
        }
        elseif (isset($_POST['currenciesSubmit']))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
                    $authorized_currencies[] = $currency['id_currency'];
            Configuration::updateValue('TWOCHECKOUT_CURRENCIES', implode(',', $authorized_currencies));
        }

        $this->_html = '<h2>'.$this->displayName.'</h2>';

        if (!empty($_POST))
        {
            $this->_postValidation();
            if (!sizeof($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors AS $err)
                    $this->_html .= "<div class='alert error'>{$err}</div>";
        }
        else
        {
            $this->_html .= "<br />";
        }

        $this->_displaycheckout();
        $this->_displayForm();

        return $this->_html;
    }


    function processPayment($token)
    {
        include(dirname(__FILE__).'/lib/Twocheckout/TwocheckoutAPI.php');

        $cart = $this->context->cart;
        $user = $this->context->customer;
        $delivery = new Address(intval($cart->id_address_delivery));
        $invoice = new Address(intval($cart->id_address_invoice));
        $customer = new Customer(intval($cart->id_customer));
        $currencies = Currency::getCurrencies();
        $authorized_currencies = array_flip(explode(',', $this->currencies));
        $currencies_used = array();
        foreach ($currencies as $key => $currency) {
            if (isset($authorized_currencies[$currency['id_currency']])) {
                $currencies_used[] = $currencies[$key];
            }
        }
        foreach ($currencies_used as $currency) {
            if ($currency['id_currency'] == $cart->id_currency) {
                $order_currency = $currency['iso_code'];
            }
        }

        try {

            $params = array(
                "sellerId" => Configuration::get('TWOCHECKOUT_SID'),
                "merchantOrderId" => $cart->id,
                "token"      => $token,
                "currency"   => $order_currency,
                "total"      => number_format($cart->getOrderTotal(true, 3), 2, '.', ''),
                "billingAddr" => array(
                    "name" => $invoice->firstname . ' ' . $invoice->lastname,
                    "addrLine1" => $invoice->address1,
                    "addrLine2" => $invoice->address2,
                    "city" => $invoice->city,
                    "state" => $invoice->state,
                    "zipCode" => $invoice->postcode,
                    "country" => $invoice->country,
                    "email" => $customer->email,
                    "phoneNumber" => $invoice->phone
                )
            );

            if ($delivery) {
                $shippingAddr = array(
                    "name" => $delivery->firstname . ' ' . $delivery->lastname,
                    "addrLine1" => $delivery->address1,
                    "addrLine2" => $delivery->address2,
                    "city" => $delivery->city,
                    "state" => $delivery->state,
                    "zipCode" => $delivery->postcode,
                    "country" => $delivery->country
                );
                array_merge($shippingAddr, $params);
            }

            if (Configuration::get('TWOCHECKOUT_SANDBOX')) {
                TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'), 'sandbox');
            } else {
                TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'));
            }
            $charge = Twocheckout_Charge::auth($params);

        } catch (Twocheckout_Error $e) {
            $message = 'Payment Authorization Failed';
            Tools::redirect('index.php?controller=order&step=3&twocheckouterror='.$message);
        }

        if (isset($charge['response']['responseCode'])) {
            $order_status = (int)Configuration::get('TWOCHECKOUT_ORDER_STATUS');
            $message = $charge['response']['responseMsg'];
            $this->validateOrder((int)$this->context->cart->id, _PS_OS_PAYMENT_, $charge['response']['total'], $this->displayName, $message, array(), null, false, $this->context->customer->secure_key);
            Tools::redirect('index.php?controller=order-confirmation?key=' . $user->secure_key . '&id_cart=' . (int)
                $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $this->module->currentOrder);
        } else {
            $message = 'Payment Authorization Failed';
            Tools::redirect('index.php?controller=order&step=3&twocheckouterror='.$message);
        }
    }


    function hookPayment($params)
    {
        global $smarty;
        $smarty->assign(array(
        'this_path' 		=> $this->_path,
        'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

        return $this->display(__FILE__, 'payment_execution.tpl');
    }


    function hookPaymentReturn($params)
    {
        global $smarty;
        $state = $params['objOrder']->getCurrentState();
        if ($state == _PS_OS_OUTOFSTOCK_ or $state == _PS_OS_PAYMENT_)
            $smarty->assign(array(
                'total_to_pay' 	=> Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false, false),
                'status' 		=> 'ok',
                'id_order' 		=> $params['objOrder']->id
            ));
        else
            $smarty->assign('status', 'failed');

        return $this->display(__FILE__, 'payment_return.tpl');
    }


    private function _postValidation()
    {
        if (isset($_POST['btnSubmit']))
        {
            if (empty($_POST['SID']))
                $this->_postErrors[] = $this->l('Your 2Checkout account number is required.');
        }
        elseif (isset($_POST['currenciesSubmit']))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
                    $authorized_currencies[] = $currency['id_currency'];
                if (!sizeof($authorized_currencies))
                    $this->_postErrors[] = $this->l('at least one currency is required.');
        }
    }


    private function _postProcess()
    {
        $ok = $this->l('Ok');
        $updated = $this->l('Settings Updated');
        $this->_html .= "<div class='conf confirm'><img src='../img/admin/ok.gif' alt='{$ok}' />{$updated}</div>";
    }


    public function hookOrderConfirmation($params)
    {
        if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name))
            return false;
        
        if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid))

            $this->smarty->assign('order', array('reference' => isset($params['objOrder']->reference) ? $params['objOrder']->reference : '#'.sprintf('%06d', $params['objOrder']->id), 'valid' => $params['objOrder']->valid));

            $pendingOrderStatus = (int)Configuration::get('TWOCHECKOUT_PENDING_ORDER_STATUS');
            $currentOrderStatus = (int)$params['objOrder']->getCurrentState();
            if ($pendingOrderStatus==$currentOrderStatus) {
                $this->smarty->assign('order_pending', true);
            } else {
                $this->smarty->assign('order_pending', false);
            }

        return $this->display(__FILE__, 'order-confirmation.tpl');

    }




    private function _displaycheckout()
    {
        $modDesc 	= $this->l('This module allows you to accept payments using 2Checkout\'s Payment API services.');
        $modStatus	= $this->l('2Checkout\'s online payment service could be the right solution for you');
        $modconfirm	= $this->l('');
        $this->_html .= "<img src='../modules/checkout/2Checkout.gif' style='float:left; margin-right:15px;' />
                                        <b>{$modDesc}</b>
                                        <br />
                                        <br />
                                        {$modStatus}
                                        <br />
                                        {$modconfirm}
                                        <br />
                                        <br />
                                        <br />";
    }




    private function _displayForm()
    {
        $modcheckout	            = $this->l('2Checkout Setup');
        $modcheckoutDesc	        = $this->l('Please specify the 2Checkout account number and secret word.');
        $modClientLabelSid	        = $this->l('2Checkout Account Number');
        $modClientValueSid	        = Configuration::get('TWOCHECKOUT_SID');
        $modClientLabelPublic	    = $this->l('Publishable Key');
        $modClientValuePublic	    = Configuration::get('TWOCHECKOUT_PUBLIC');
        $modClientLabelPrivate	    = $this->l('Private Key');
        $modClientValuePrivate	    = Configuration::get('TWOCHECKOUT_PRIVATE');
        $modClientLabelSandbox      = $this->l('Use Sandbox?');
        $modClientValueSandbox      = Configuration::get('TWOCHECKOUT_SANDBOX');
        $modCurrencies		        = $this->l('Currencies');
        $modUpdateSettings 	        = $this->l('Update settings');
        $modCurrenciesDescription   = $this->l('Currencies authorized for 2Checkout payment');
        $modAuthorizedCurrencies    = $this->l('Authorized currencies');
        $this->_html .=
        "
        <br />
        <br />
        <p><form action='{$_SERVER['REQUEST_URI']}' method='post'>
                <fieldset>
                <legend><img src='../img/admin/access.png' />{$modcheckout}</legend>
                        <table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
                                <tr>
                                        <td colspan='2'>
                                                {$modcheckoutDesc}<br /><br />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelSid}</td>
                                        <td>
                                                <input type='text' name='SID' value='{$modClientValueSid}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelPublic}</td>
                                        <td>
                                                <input type='text' name='PUBLIC' value='{$modClientValuePublic}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelPrivate}</td>
                                        <td>
                                                <input type='text' name='PRIVATE' value='{$modClientValuePrivate}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelSandbox}</td>
                                        <td>
                                            <input type='radio' name='SANDBOX' value='0'".(!$modClientValueSandbox ? " checked='checked'" : '')." /> No
                                            <br />
                                            <input type='radio' name='SANDBOX' value='1'".($modClientValueSandbox ? " checked='checked'" : '')." /> Yes
                                            <br />
                                        </td>
                                </tr>
                                <tr>
                                        <td colspan='2' align='center'>
                                                <input class='button' name='btnSubmit' value='{$modUpdateSettings}' type='submit' />
                                        </td>
                                </tr>
                        </table>
                </fieldset>
        </form>
        </p>
        <br />
        <br />
        <form action='{$_SERVER['REQUEST_URI']}' method='post'>
                <fieldset>
                <legend>{$modAuthorizedCurrencies}</legend>
                        <table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
                                <tr>
                                        <td colspan='2'>
                                                {$modCurrenciesDescription}
                                                <br />
                                                <br />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130' style='height: 35px; vertical-align:top'>{$modCurrencies}</td>
                                        <td>";
                $currencies = Currency::getCurrencies();
                $authorized_currencies = array_flip(explode(',', Configuration::get('TWOCHECKOUT_CURRENCIES')));
                foreach ($currencies as $currency)
                    $this->_html .= '<label style="float:none; "><input type="checkbox" value="true" name="currency_'.$currency['id_currency'].'"'.(isset($authorized_currencies[$currency['id_currency']]) ? ' checked="checked"' : '').' />&nbsp;<span style="font-weight:bold;">'.$currency['name'].'</span> ('.$currency['sign'].')</label><br />';
                    $this->_html .="
                                        </td>
                                </tr>
                                <tr>
                                        <td colspan='2' align='center'>
                                                <br />
                                                <input class='button' name='currenciesSubmit' value='{$modUpdateSettings}' type='submit' />
                                        </td>
                                </tr>
                        </table>
                </fieldset>
        </form>";
    }
}

?>
