{*
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
 * @category	Belvg
 * @package	Belvg_Twocheckout
 * @author    Alexander Simonchik <support@belvg.com>
 * @copyright Copyright (c) 2010 - 2014 BelVG LLC. (http://www.belvg.com)
 * @license   http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
*}
{if $status == 'ok'}
	<div class="alert alert-success">
		{l s='Your order has been completed.' mod='twocheckout'}
		<br/><br/>
		{l s='For any questions or for further information, please contact our' mod='twocheckout'} <a href="{$link->getPageLink('contact')}">{l s='customer support' mod='twocheckout'}</a>.
	</div>
{else}
	<div class="alert alert-warning">
		{l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='twocheckout'} 
		<a href="{$link->getPageLink('contact')|escape:false}">{l s='customer support' mod='twocheckout'}</a>.
	</div>
{/if}
