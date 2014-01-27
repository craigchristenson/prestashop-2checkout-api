<?php

class Twocheckoutpp extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'twocheckoutpp';
        $this->displayName = '2Checkout PayPal Direct';
        $this->tab = 'payments_gateways';
        $this->version = 0.1;

        $config = Configuration::getMultiple(array('TWOCHECKOUTPP_SID', 'TWOCHECKOUTPP_SECRET', 'TWOCHECKOUTPP_CURRENCY','TWOCHECKOUTPP_CURRENCIES'));

        if (isset($config['TWOCHECKOUTPP_SID']))
            $this->SID = $config['TWOCHECKOUTPP_SID'];
        if (isset($config['TWOCHECKOUTPP_SECRET']))
            $this->SECRET = $config['TWOCHECKOUTPP_SECRET'];
        if (isset($config['TWOCHECKOUTPP_CURRENCY']))
            $this->CURRENCY = $config['TWOCHECKOUTPP_CURRENCY'];
        if (isset($config['TWOCHECKOUTPP_CURRENCIES']))
            $this->currencies = $config['TWOCHECKOUTPP_CURRENCIES'];

        parent::__construct();

        /* The parent construct is required for translations */
        $this->page = basename(__FILE__, '.php');
        $this->description = $this->l('Accept payments with 2Checkout');

        if (!isset($this->SID) OR !isset($this->currencies))
            $this->warning = $this->l('your 2Checkout Seller ID must be configured in order to use this module correctly');

        if (!Configuration::get('TWOCHECKOUTPP_CURRENCIES'))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                    $authorized_currencies[] = $currency['id_currency'];
            Configuration::updateValue('TWOCHECKOUTPP_CURRENCIES', implode(',', $authorized_currencies));
        }
    }


    function install()
    {
        //Call PaymentModule default install function
        $install = parent::install() && $this->registerHook('payment') && $this->registerHook('paymentReturn') && $this->registerHook('displayMobileHeader');

        //Create Valid Currencies
        $currencies = Currency::getCurrencies();
        $authorized_currencies = array();
        foreach ($currencies as $currency)
        $authorized_currencies[] = $currency['id_currency'];
        Configuration::updateValue('TWOCHECKOUTPP_CURRENCIES', implode(',', $authorized_currencies));
        return $install;
    }


    function uninstall()
    {
        Configuration::deleteByName('TWOCHECKOUTPP_SID');
        Configuration::deleteByName('TWOCHECKOUTPP_SECRET');
        Configuration::deleteByName('TWOCHECKOUTPP_CURRENCY');
        Configuration::deleteByName('TWOCHECKOUTPP_CURRENCIES');
        return parent::uninstall();
    }


    function getContent()
    {
        $this->_html = '<h2>'.$this->displayName.'</h2>';

        if (!empty($_POST))
        {
            $this->_postValidation();
            if (!sizeof($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors as $err)
                    $this->_html .= "<div class='alert error'>{$err}</div>";
        }
        else
        {
            $this->_html .= "<br />";
        }

        $this->_html .= $this->_displaycheckout();
        $this->_displayForm();

        return $this->_html;
    }

    function checkTotal($cart)
    {
        global $cookie, $smarty;

        $check_total = 0;
        $cart_details = $cart->getSummaryDetails(null, true);
        //products
        $products = $cart->getProducts();
        foreach ($products as $product)
        {
            $check_total += $product['price'] * $product['quantity'];
        }
        //shipping
        if (_PS_VERSION_ < '1.5') {
            $shipping = $cart_details['total_shipping_tax_exc'];
        } else {
            $shipping = $this->context->cart->getTotalShippingCost();
        }
        $check_total += $shipping;
        $check_total += $cart_details['total_tax'];
        $check_total -= $cart_details['total_discounts_tax_exc'];
        return $check_total;
    }

    function execPayment($cart)
    {
        $delivery = new Address(intval($cart->id_address_delivery));
        $invoice = new Address(intval($cart->id_address_invoice));
        $customer = new Customer(intval($cart->id_customer));

        global $cookie, $smarty;

        //Verify currencies and display payment form
        $cart_details = $cart->getSummaryDetails(null, true);
        $currencies = Currency::getCurrencies();
        $authorized_currencies = array_flip(explode(',', $this->currencies));
        $currencies_used = array();
        foreach ($currencies as $key => $currency)
            if (isset($authorized_currencies[$currency['id_currency']]))
                $currencies_used[] = $currencies[$key];

        $order_currency = '';

        foreach ($currencies_used as $key => $currency) {
            if ($currency['id_currency'] == $cart->id_currency) {
                $order_currency = $currency['iso_code'];
            }
        }

        $products = $cart->getProducts();
        foreach ($products as $key => $product)
        {
                $products[$key]['name'] = str_replace('"', '\'', $product['name']);
                $products[$key]['name'] = htmlentities(utf8_decode($product['name']));
        }

        $discounts = $cart_details['discounts'];

        $carrier = $cart_details['carrier'];

        if (_PS_VERSION_ < '1.5') {
	        $shipping_cost = $cart_details['total_shipping_tax_exc'];
        } else {
	        $shipping_cost = $this->context->cart->getTotalShippingCost();
        }

        $CheckoutUrl	    = 'https://www.2checkout.com/checkout/purchase';
        $sid				= Configuration::get('TWOCHECKOUTPP_SID');
        $amount				= number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $cart_order_id		= $cart->id;
        $email				= $customer->email;
        $secure_key			= $customer->secure_key;
        $demo				= "N";	// Change to "Y" for demo mode
        $outside_state	    = "XX"; // This will pre-select Outside USA and Canada, if state does not exist

        // Invoice Parameters
        $card_holder_name		= $invoice->firstname . ' ' . $invoice->lastname;
        $street_address			= $invoice->address1;
        $street_address2		= $invoice->address2;
        $phone		    		= $invoice->phone;
        $city 	    			= $invoice->city;
        $state		    		= (Validate::isLoadedObject($invoice) AND $invoice->id_state) ? new State(intval($invoice->id_state)) : false;
        $zip			    	= $invoice->postcode;
        $country		    	= $invoice->country;

        // Shipping Parameters
        $ship_name	    		= $delivery->firstname . ' ' . $invoice->lastname;
        $ship_street_address	= $delivery->address1;
        $ship_street_address2	= $delivery->address2;
        $ship_city 		    	= $delivery->city;
        $ship_state	    		= (Validate::isLoadedObject($delivery) AND $delivery->id_state) ? new State(intval($delivery->id_state)) : false;
        $ship_zip   			= $delivery->postcode;
        $ship_country			= $delivery->country;

        $check_total = $this->checkTotal($cart);

        if ( Configuration::get('TWOCHECKOUTPP_CURRENCY') > 0 ) {
            $currency_from = Currency::getCurrency($cart->id_currency);
            $currency_to = Currency::getCurrency(Configuration::get('TWOCHECKOUTPP_CURRENCY'));
            $amount = Tools::ps_round($amount / $currency_from['conversion_rate'], 2);
            $total = Tools::ps_round($amount *= $currency_to['conversion_rate'], 2);
            $order_currency = $currency_to['iso_code'];
            $override_currency = $currency_to;
        } else {
            $total = number_format($cart->getOrderTotal(true, 3), 2, '.', '');
            $override_currency = 0;
        }

        $cart=new Cart($cookie->id_cart);
        $address=new Address($cart->id_address_delivery,intval($cookie->id_lang));
        $state=State::getNameById($address->id_state);
        $state=($state?'('.$state.')':'');
        $str_address=($address->company?$address->company.'<br>':'').
        $address->firstname.' '.$address->lastname.'<br>'.
        $address->address1.'<br>'.($address->address2?$address->address2.'<br>':'').
        $address->postcode.' '.$address->city.'<br>'.
        $address->country.$state;
        $carrier=Carrier::getCarriers(intval($cookie->id_lang));

        if($carrier){
            foreach ($carrier as $c){
                if($cart->id_carrier==$c['id_carrier']){
                    $carrier_name = $c['name'];
                    break;
                }
            }
        }

        $params = array();
        $params['sid']                  = $sid;
        $params['paypal_direct']        = 'Y';
        $params['currency_code']        = $order_currency;
        $params['return_url']           = $this->context->link->getPageLink('order', true, NULL, "step=3");
        $params['merchant_order_id']    = $cart_order_id;
        $params['email']                = $email;
        $params['phone']                = $phone;
        $params['card_holder_name']     = $card_holder_name;
        $params['street_address']       = $street_address;
        $params['street_address2']      = $street_address2;
        $params['city']                 = $city;
        $params['state']                = $state;
        $params['zip']                  = $zip;
        $params['country']              = $country;
        $params['ship_name']            = $ship_name;
        $params['ship_street_address']  = $ship_street_address;
        $params['ship_street_address2'] = $ship_street_address2;
        $params['ship_city']            = $ship_city;
        $params['ship_state']           = $ship_state ? $ship_state->name : $outside_state;
        $params['ship_zip']             = $ship_zip;
        $params['ship_country']         = $ship_country;
        if (sprintf("%01.2f", $check_total) == sprintf("%01.2f", $total)) {
            $params['mode']             = '2CO';
            $i = 0;
            foreach ($products as $product) {
                $params['li_'.$i.'_type']           = 'product';
                $params['li_'.$i.'_id']             = $product['id_product'];
                $params['li_'.$i.'_name']           = $product['name'];
                $params['li_'.$i.'_description']    = $product['description_short'];
                $params['li_'.$i.'_quantity']       = $product['quantity'];
                $params['li_'.$i.'_price']          = $product['price'];
                $i++;
            }
            if ($shipping_cost > 0) {
                $params['li_'.$i.'_type']           = 'shipping';
                $params['li_'.$i.'_name']           = $carrier_name;
                $params['li_'.$i.'_price']          = $shipping_cost;
                $i++;
            }
            if ($cart_details['total_tax'] > 0) {
                $params['li_'.$i.'_type']           = 'tax';
                $params['li_'.$i.'_name']           = 'Tax';
                $params['li_'.$i.'_price']          = $cart_details['total_tax'];
                $i++;
            }
            if ($cart_details['total_discounts_tax_exc'] > 0) {
                $params['li_'.$i.'_type']           = 'coupon';
                $params['li_'.$i.'_name']           = 'Discounts';
                $params['li_'.$i.'_price']          = $cart_details['total_discounts_tax_exc'];
                $i++;
            }
        } else {
            $params['cart_order_id']    = $cart_order_id;
            $params['total']            = sprintf("%01.2f", $total);
        }
        $redirect_url = $CheckoutUrl.'?'.http_build_query($params);
        header( 'Location: '.$redirect_url );
    }


    function hookPayment($params)
    {
        global $smarty;
        $smarty->assign(array(
        'this_path' 		=> $this->_path,
        'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

        return $this->display(__FILE__, 'payment.tpl');
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
                $this->_postErrors[] = $this->l('Your Vendor Account Number is required.');
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
        if (isset($_POST['btnSubmit']))
        {
            Configuration::updateValue('TWOCHECKOUTPP_SID', $_POST['SID']);
            Configuration::updateValue('TWOCHECKOUTPP_SECRET', $_POST['SECRET']);
            Configuration::updateValue('TWOCHECKOUTPP_CURRENCY', $_POST['CURRENCY']);
        }
        elseif (isset($_POST['currenciesSubmit']))
        {
            $currencies = Currency::getCurrencies();
            $authorized_currencies = array();
            foreach ($currencies as $currency)
                if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
                    $authorized_currencies[] = $currency['id_currency'];
                Configuration::updateValue('TWOCHECKOUTPP_CURRENCIES', implode(',', $authorized_currencies));
        }
        $ok = $this->l('Ok');
        $updated = $this->l('Settings Updated');
        return $this->_html .= "<div class='conf confirm'><img src='../img/admin/ok.gif' alt='{$ok}' />{$updated}</div>";
    }


    private function _displaycheckout()
    {
        $modDesc 	= $this->l('This module allows you to accept payments using 2Checkout PayPal Direct.');
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
        $modcheckoutDesc	    = $this->l('Please specify the 2Checkout account number and secret word.');
        $modClientLabelSid	    = $this->l('2Checkout Account Number');
        $modClientValueSid	    = Configuration::get('TWOCHECKOUTPP_SID');
        $modClientLabelSecret	    = $this->l('Secret Word');
        $modClientValueSecret	    = Configuration::get('TWOCHECKOUTPP_SECRET');
        $modClientValueCurrency       = Configuration::get('TWOCHECKOUTPP_CURRENCY');
        $modCurrencies		    = $this->l('Currencies');
        $modUpdateSettings 	    = $this->l('Update settings');
        $modCurrenciesDescription   = $this->l('Currencies authorized for 2Checkout payment');
        $modAuthorizedCurrencies    = $this->l('Authorized currencies');
        $modClientLabelCurrency      = $this->l('Force 2Checkout Currency?');
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
                                        <td width='130'>{$modClientLabelSecret}</td>
                                        <td>
                                                <input type='text' name='SECRET' value='{$modClientValueSecret}' style='width: 300px;' />
                                        </td>
                                </tr>
                                <tr>
                                        <td width='130'>{$modClientLabelCurrency}</td>
                                        <td>
                                            <input type='radio' name='CURRENCY' value='0'".(!$modClientValueCurrency ? " checked='checked'" : '')." /> Use customer's currency.
                                            <br />";
                                            $currencies = Currency::getCurrencies();
                                            foreach ($currencies as $currency)
                                                $this->_html .= '<label style="float:none; "><input type="radio" name="CURRENCY" value="'.$currency['id_currency'].'"'.($modClientValueCurrency == $currency['id_currency'] ? ' checked' : '').' />&nbsp;<span style="font-weight:bold;">'.$currency['name'].'</span> ('.$currency['sign'].')</label><br />';
                                            $this->_html .= "
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
                $authorized_currencies = array_flip(explode(',', Configuration::get('TWOCHECKOUTPP_CURRENCIES')));
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
