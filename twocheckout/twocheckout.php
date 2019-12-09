<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
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
        $this->version = 0.2;


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
        $install = parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('header');
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
        return $this->unregisterHook('paymentOptions') && $this->unregisterHook('header') && parent::uninstall();
    }

    public function hookDisplayMobileHeader()
    {
        return $this->hookHeader();
    }

    public function hookHeader()
    {
        if (Tools::getValue('controller') != 'order-opc' && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php' || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php' || Tools::getValue('controller') == 'order' || Tools::getValue('controller') == 'orderopc' || Tools::getValue('step') == 3)))
            return;
		
		$this->context->controller->registerJavascript(
		'remote-2checkout',
		'https://www.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID'),
		['position' => 'head', 'server' => 'remote']
		);
		
		$this->context->controller->registerJavascript(
		'remote-2checkout-min',
		'https://www.2checkout.com/checkout/api/2co.min.js',
		['position' => 'head', 'server' => 'remote']
		);
		
		$this->context->controller->registerJavascript(
        '2checkout-module-block-ui-script',
        'modules/'.$this->name.'/assets/jquery.blockUI.js',
        ['position' => 'bottom']
		);
		
		$this->context->controller->registerJavascript(
        '2checkout-module-script',
        'modules/'.$this->name.'/assets/script.js',
        ['position' => 'bottom']
		);
        
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
        include(dirname(__FILE__).'/lib/Twocheckout/TwocheckoutApi.php');

        $twocheckout = Module::getInstanceByName('twocheckout');
        $context = Context::getContext();
        $cart = $context->cart;
        $user = $context->customer;
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
                    "state" => ($invoice->country == "United States" || $invoice->country == "Canada") ? State::getNameById($invoice->id_state) : 'XX',
                    "zipCode" => $invoice->postcode,
                    "country" => $invoice->country,
                    "email" => $customer->email,
                    "phoneNumber" => $invoice->phone
                )
            );

            // this is for demo sale transaction
            if (Configuration::get('TWOCHECKOUT_SANDBOX')) {
                $params["demo"] = true;
            }

            if ($delivery) {
                $shippingAddr = array(
                    "name" => $delivery->firstname . ' ' . $delivery->lastname,
                    "addrLine1" => $delivery->address1,
                    "addrLine2" => $delivery->address2,
                    "city" => $delivery->city,
                    "state" => (Validate::isLoadedObject($delivery) AND $delivery->id_state) ? new State(intval($delivery->id_state)) : 'XX',
                    "zipCode" => $delivery->postcode,
                    "country" => $delivery->country
                );
                array_merge($shippingAddr, $params);
            }

            TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'));
            $charge = Twocheckout_Charge::auth($params);

        } catch (Twocheckout_Error $e) {
            $message = 'Payment Authorization Failed';
            Tools::redirect('index.php?controller=order&step=3&twocheckouterror='.$message);
        }

        if (isset($charge['response']['responseCode'])) {
            $order_status = (int)Configuration::get('TWOCHECKOUT_ORDER_STATUS');
            $message = $charge['response']['responseMsg'];
            $twocheckout->validateOrder((int)$cart->id, _PS_OS_PAYMENT_, $charge['response']['total'], $twocheckout->displayName, $message, array(), null, false, $user->secure_key);
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='
                .$twocheckout->id.'&id_order='.$twocheckout->currentOrder.'&key='.$user->secure_key);
        } else {
            $message = 'Payment Authorization Failed';
            Tools::redirect('index.php?controller=order&step=3&twocheckouterror='.$message);
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!Currency::getCurrencies()) {
            return;
        }
        $payment_options = [
            $this->get2CheckoutPaymentOption(),
        ];
        return $payment_options;
    }
	
    public function get2CheckoutPaymentOption()
    {
        $embeddedOption = new PaymentOption();
		$embeddedOption->setBinary(true);
        $embeddedOption->setCallToActionText($this->l('2Checkout'))
			->setModuleName($this->l('2Checkout'))
            ->setForm($this->generateForm());
        return $embeddedOption;
    }
	
	protected function generateForm()
    {
        $this->context->smarty->assign([
			'twocheckout_sid' => Configuration::get('TWOCHECKOUT_SID'),
			'twocheckout_public_key' => Configuration::get('TWOCHECKOUT_PUBLIC'),
            'module_dir' => _MODULE_DIR_.$this->name.'/',
        ]);
       return $this->context->smarty->fetch('module:twocheckout/payment_execution.tpl');
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
        $this->_html .= "<div class='conf confirm'><img src='../img/admin/enabled.gif' alt='{$ok}' />{$updated}</div>";
    }

    private function _displaycheckout()
    {
        $modDesc 	= $this->l('This module allows you to accept payments using 2Checkout\'s Payment API services.');
        $modStatus	= $this->l('2Checkout\'s online payment service could be the right solution for you');
        $modconfirm	= $this->l('');
        $this->_html .= "<img src='../modules/twocheckout/2Checkout.gif' style='float:left; margin-right:15px;' />
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
        $modClientLabelSandbox      = $this->l('Demo Sale?');
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
                <legend><img src='../img/admin/cart.gif' />{$modcheckout}</legend>
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
