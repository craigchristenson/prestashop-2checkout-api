<?php
/**
 * 2007-2014 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *         DISCLAIMER   *
 * ***************************************
 * Do not edit or add to this file if you wish to upgrade Prestashop to newer
 * versions in the future.
 * ****************************************************
 *
 * @category    Belvg
 * @package    Belvg_Twocheckout
 * @author    Alexander Simonchik <support@belvg.com>
 * @copyright Copyright (c) 2010 - 2014 BelVG LLC. (http://www.belvg.com)
 * @license   http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */

if (!defined('_PS_VERSION_'))
	exit;

class Twocheckout extends PaymentModule
{
	protected $hooks = array(
		'payment',
		'header',
		'orderConfirmation',
		'displayMobileHeader'
	);
	private $html = '';
	private $post_errors = array();

	public function __construct()
	{
		$this->name = 'twocheckout';
		$this->tab = 'payments_gateways';
		$this->version = '1.6.1';
		$this->author = 'belvg';
		$this->bootstrap = true;
		$this->module_key = '';

		$this->initConfigVars();

		parent::__construct();

		$this->ps_versions_compliancy = array('min' => '1.6.0', 'max' => '1.6.9');
		$this->displayName = '2Checkout Payment API';
		$this->description = $this->l('Accept payments using 2Checkout Payment API');

		if (!isset($this->sid) || !isset($this->currencies))
			$this->warning = $this->l('your 2Checkout vendor account number must be configured in order to use this module correctly');
	}

	public function initConfigVars()
	{
		$config = Configuration::getMultiple(array('TWOCHECKOUT_SID', 'TWOCHECKOUT_PUBLIC', 'TWOCHECKOUT_PRIVATE',
			'TWOCHECKOUT_SANDBOX'));

		if (isset($config['TWOCHECKOUT_SID']))
			$this->sid = $config['TWOCHECKOUT_SID'];
		if (isset($config['TWOCHECKOUT_PUBLIC']))
			$this->public = $config['TWOCHECKOUT_PUBLIC'];
		if (isset($config['TWOCHECKOUT_PRIVATE']))
			$this->private = $config['TWOCHECKOUT_PRIVATE'];
		if (isset($config['TWOCHECKOUT_SANDBOX']))
			$this->sandbox = $config['TWOCHECKOUT_SANDBOX'];
	}

	public function install()
	{
		//Call PaymentModule default install function
		$install = parent::install();
		foreach ($this->hooks as $hook)
		{
			if (!$this->registerHook($hook))
				return false;
		}

		return $install;
	}

	public function uninstall()
	{
		Configuration::deleteByName('TWOCHECKOUT_SID');
		Configuration::deleteByName('TWOCHECKOUT_PUBLIC');
		Configuration::deleteByName('TWOCHECKOUT_PRIVATE');
		Configuration::deleteByName('TWOCHECKOUT_SANDBOX');
		foreach ($this->hooks as $hook)
		{
			if (!$this->unregisterHook($hook))
				return false;
		}
		return parent::uninstall();
	}

	public function setSessionMessage($key, $value)
	{
		$this->context->cookie->{$key} = $value;
	}

	public function getSessionMessage($key)
	{
		if (isset($this->context->cookie->$key))
			return $this->context->cookie->$key;

		return '';
	}

	public function getAndCleanSessionMessage($key)
	{
		$message = $this->getSessionMessage($key);
		unset($this->context->cookie->$key);
		return $message;
	}

	public function getContent()
	{
		$helper = $this->initForm();
		$this->postProcess();
		foreach ($this->fields_form as $field_form)
		{
			if (isset($field_form['form']['input']))
			{
				foreach ($field_form['form']['input'] as $input)
					$helper->fields_value[$input['name']] = Configuration::get(Tools::strtoupper($input['name']));
			}
		}

		$this->html .= $helper->generateForm($this->fields_form);
		return $this->html;
	}

	/**
	 * helper with configuration
	 *
	 * @return HelperForm
	 */
	private function initForm()
	{
		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->identifier = $this->identifier;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->toolbar_scroll = true;
		$helper->toolbar_btn = $this->initToolbar();
		$helper->title = $this->displayName;
		$helper->submit_action = 'submitUpdate';

		$this->fields_form[0]['form'] = array(
			'tinymce' => true,
			'legend' => array('title' => $this->l('2Checkout Setup'), 'image' => $this->_path.'logo.png'),
			'submit' => array(
				'name' => 'submitUpdate',
				'title' => $this->l('   Save   ')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('2Checkout Account Number'),
					'name' => 'TWOCHECKOUT_SID',
					'size' => 64,
				),
				array(
					'type' => 'text',
					'label' => $this->l('Publishable Key'),
					'name' => 'TWOCHECKOUT_PUBLIC',
					'size' => 64,
				),
				array(
					'type' => 'text',
					'label' => $this->l('Private Key'),
					'name' => 'TWOCHECKOUT_PRIVATE',
					'size' => 64,
				),
				array(
					'type' => 'switch',
					'values' => array(
						array('label' => $this->l('Yes'), 'value' => 1, 'id' => 'sandbox_on'),
						array('label' => $this->l('No'), 'value' => 0, 'id' => 'sandbox_off'),
					),
					'is_bool' => true,
					'class' => 't',
					'label' => $this->l('Sandbox mode'),
					'name' => 'TWOCHECKOUT_SANDBOX',
				),
			),
		);

		$this->fields_form[1]['form'] = array(
			'description' => $this->l('This module allows you to accept payments using 2Checkout\'s
				Payment API services.').'<br>'.$this->l('2Checkout\'s online payment service could be the right solution for you'),
		);

		return $helper;
	}

	/**
	 * PrestaShop way save button
	 *
	 * @return mixed
	 */
	private function initToolbar()
	{
		$toolbar_btn = array();
		$toolbar_btn['save'] = array('href' => '#', 'desc' => $this->l('Save'));
		return $toolbar_btn;
	}

	/**
	 * save configuration values
	 */
	protected function postProcess()
	{
		if (Tools::isSubmit('submitUpdate'))
		{
			foreach ($this->fields_form as $field_form)
			{
				foreach ($field_form['form']['input'] as $input)
					Configuration::updateValue(Tools::strtoupper($input['name']), Tools::getValue(Tools::strtoupper($input['name'])));
			}

			Tools::redirectAdmin('index.php?tab=AdminModules&conf=4&configure='.$this->name.'&token='.Tools::getAdminToken('AdminModules'.
				(int)Tab::getIdFromClassName('AdminModules').(int)$this->context->employee->id));
		}
	}

	public function hookDisplayMobileHeader()
	{
		return $this->hookHeader();
	}

	public function hookHeader()
	{
		if (!in_array($this->context->controller->php_self, array('order-opc', 'order')))
			return;

		$this->context->controller->addCSS($this->_path.'css/2checkout.css', 'all');

		if (Configuration::get('TWOCHECKOUT_SANDBOX'))
			$this->context->controller->addJS('https://sandbox.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'');
		else
			$this->context->controller->addJS('https://www.2checkout.com/checkout/api/script/publickey/'.Configuration::get('TWOCHECKOUT_SID').'');

		return $this->display(__FILE__, 'header.tpl');
	}

	public function hookPayment()
	{
		$this->context->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/",
			'twocheckout_sid' => Configuration::get('TWOCHECKOUT_SID'),
			'twocheckout_public_key' => Configuration::get('TWOCHECKOUT_PUBLIC'),
			'err_message' => $this->getAndCleanSessionMessage('2co_message'),
		));

		return $this->display(__FILE__, 'payment_execution.tpl');
	}

	public function processPayment($token)
	{
		include(dirname(__FILE__).'/lib/Twocheckout/TwocheckoutApi.php');

		$order_process = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
		$cart = $this->context->cart;
		$user = $this->context->customer;
		$delivery = new Address((int)$cart->id_address_delivery);
		$invoice = new Address((int)$cart->id_address_invoice);
		$customer = new Customer((int)$cart->id_customer);
		$currencies = Currency::getCurrencies();
		$authorized_currencies = array_flip(explode(',', $this->currencies));
		$currencies_used = array();
		foreach ($currencies as $key => $currency)
		{
			if (isset($authorized_currencies[$currency['id_currency']]))
				$currencies_used[] = $currencies[$key];
		}
		foreach ($currencies_used as $currency)
		{
			if ($currency['id_currency'] == $cart->id_currency)
				$order_currency = $currency['iso_code'];
		}

		try {

			$params = array(
				'sellerId' => Configuration::get('TWOCHECKOUT_SID'),
				'merchantOrderId' => 'prestashop--'.$cart->id,
				'token' => $token,
				'currency' => $order_currency,
				'total' => number_format($cart->getOrderTotal(true, 3), 2, '.', ''),
				'billingAddr' => array(
					'name' => $invoice->firstname.' '.$invoice->lastname,
					'addrLine1' => $invoice->address1,
					'addrLine2' => $invoice->address2,
					'city' => $invoice->city,
					'state' => ($invoice->country == 'United States' || $invoice->country == 'Canada') ? State::getNameById($invoice->id_state) : 'XX',
					'zipCode' => $invoice->postcode,
					'country' => $invoice->country,
					'email' => $customer->email,
					'phoneNumber' => $invoice->phone
				)
			);

			if ($delivery)
			{
				$shipping_addr = array(
					'name' => $delivery->firstname.' '.$delivery->lastname,
					'addrLine1' => $delivery->address1,
					'addrLine2' => $delivery->address2,
					'city' => $delivery->city,
					'state' => (Validate::isLoadedObject($delivery) && $delivery->id_state) ? new State((int)$delivery->id_state) : 'XX',
					'zipCode' => $delivery->postcode,
					'country' => $delivery->country
				);
				array_merge($shipping_addr, $params);
			}

			if (Configuration::get('TWOCHECKOUT_SANDBOX'))
				TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'), 'sandbox');
			else
				TwocheckoutApi::setCredentials(Configuration::get('TWOCHECKOUT_SID'), Configuration::get('TWOCHECKOUT_PRIVATE'));
			$charge = Twocheckout_Charge::auth($params);

		} catch (Twocheckout_Error $e) {
			$this->setSessionMessage('2co_message', $this->l('Payment Authorization Failed: Please verify your Credit Card details 
				are entered correctly and try again, or try another payment method. Original error message: ').$e);

			Tools::redirect($this->context->link->getPageLink($order_process));
		}

		if (isset($charge['response']['responseCode']))
		{
			//$order_status = (int)Configuration::get('TWOCHECKOUT_ORDER_STATUS');
			$message = $charge['response']['responseMsg'];
			$this->validateOrder((int)$this->context->cart->id, _PS_OS_PAYMENT_, $charge['response']['total'],
				$this->displayName, $message, array(), null, false, $this->context->customer->secure_key);

			$link_params = array(
				'key' => $user->secure_key,
				'id_cart' => (int)$cart->id,
				'id_module' => (int)$this->id,
				'id_order' => (int)$this->currentOrder,
			);
			Tools::redirect($this->context->link->getPageLink('order-confirmation', null, null, $link_params));
		}
		else
		{
			$this->setSessionMessage('2co_message', $this->l('Payment Authorization Failed: Please verify your Credit Card details 
				are entered correctly and try again, or try another payment method.'));

			Tools::redirect($this->context->link->getPageLink($order_process));
		}
	}

	public function hookOrderConfirmation($params)
	{
		return $this->hookPaymentReturn($params);
	}

	public function hookPaymentReturn($params)
	{
		$state = $params['objOrder']->getCurrentState();
		if ($state == _PS_OS_OUTOFSTOCK_ || $state == _PS_OS_PAYMENT_)
			$this->context->smarty->assign(array(
				'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj']),
				'status' => 'ok',
				'id_order' => $params['objOrder']->id
			));
		else
			$this->context->smarty->assign('status', 'failed');

		return $this->display(__FILE__, 'payment_return.tpl');
	}


}

?>