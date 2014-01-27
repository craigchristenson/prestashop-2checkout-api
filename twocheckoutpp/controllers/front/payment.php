<?php

class TwocheckoutppPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;

		$checkout = new twocheckoutpp();
		$checkout->execPayment($cart);

		$this->context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'isoCode' => $this->context->language->iso_code,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('payment_execution.tpl');
	}
}
