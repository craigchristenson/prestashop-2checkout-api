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
<script type="text/javascript" src="{$module_dir|escape:false}js/jquery.blockUI.js"></script>
<script type="text/javascript"src="{$module_dir|escape:false}js/2co.min.js"/></script>

<div class="row">
	<div class="col-xs-12 col-md-6">
		<div id="twocheckout_payment_module" class="payment_module">
			<h3 class="twocheckout_title">
				<img id="secure-icon" alt="{l s='secure payment' mod='twocheckout'}" src="{$module_dir|escape:false}img/secure-icon.png" />{l s='Pay by credit card with our secured payment server' mod='twocheckout'}
			</h3>
			<div class="alert alert-danger" style="display:none" id="twocheckout_error_creditcard">
				<p>{l s='Payment Authorization Failed: Please verify your Credit Card details are entered correctly and try again, or try another payment method.' mod='twocheckout'}</p>
			</div>
			{if isset($err_message) && !empty($err_message)}
				<div class="alert alert-danger" id="twocheckout_error_creditcard_custom">
					<p>{$err_message|escape:false}</p>
				</div>
			{/if}
			<form action="{$link->getModuleLink('twocheckout', 'validation')|escape:false}" method="POST" id="twocheckoutCCForm" onsubmit="return false">
				<input id="sellerId" type="hidden" value="{$twocheckout_sid|escape:false}">
				<input id="publishableKey" type="hidden" value="{$twocheckout_public_key|escape:false}">
				<input id="token" name="token" type="hidden" value="">
				<div class="block-left">
					<label>{l s='Card Number' mod='twocheckout'}</label><br />
					<input class="numeric" type="text" size="20" autocomplete="off" id="ccNo" required/>
				</div>
				<br />
				<div class="block-left">
					<label>{l s='Expiration (MM/YYYY)' mod='twocheckout'}</label><br />
					<select id="expMonth" name="month" required>
						<option value="01">{l s='January' mod='twocheckout'}</option>
						<option value="02">{l s='February' mod='twocheckout'}</option>
						<option value="03">{l s='March' mod='twocheckout'}</option>
						<option value="04">{l s='April' mod='twocheckout'}</option>
						<option value="05">{l s='May' mod='twocheckout'}</option>
						<option value="06">{l s='June' mod='twocheckout'}</option>
						<option value="07">{l s='July' mod='twocheckout'}</option>
						<option value="08">{l s='August' mod='twocheckout'}</option>
						<option value="09">{l s='September' mod='twocheckout'}</option>
						<option value="10">{l s='October' mod='twocheckout'}</option>
						<option value="11">{l s='November' mod='twocheckout'}</option>
						<option value="12">{l s='December' mod='twocheckout'}</option>
					</select>
					<span> / </span>
					<select id="expYear" name="year" required>
						{for $i=1 to 8}
							{$tmp_year = {$smarty.now|date_format:"%Y"} - 1 + $i}
							<option value="{$tmp_year|escape:false}">{$tmp_year|escape:false}</option>
						{/for}
					</select>
				</div>
				<br />
				<div class="block-left">
					<label>{l s='CVC' mod='twocheckout'}</label><br />
					<input class="numeric" id="cvv" type="text" size="4" autocomplete="off" required />
				</div>
				<br />
				<input type="submit" class="button" id="submit_payment" {*onclick="retrieveToken()"*} value="{l s='Submit Payment' mod='twocheckout'}" />
				<div class="block-right">
					<img src="{$module_dir|escape:false}img/credit-cards.png" />
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
  function successCallback(data) {
    $.blockUI({ message: '<p><h1>' + twoco_loading_msg + '</h1></p>' });
    var myForm = document.getElementById('twocheckoutCCForm');
    myForm.token.value = data.response.token.token;
    myForm.submit();        
  }

  function errorCallback(data) {
    clearFields(); 
    if (data.errorCode === 200) {
      TCO.requestToken(successCallback, errorCallback, 'tcoCCForm');
    } else if(data.errorCode == 401) {
      $("#twocheckout_error_creditcard").show();
    } else {
      alert(data.errorMsg);
    } 
  }

  $("#twocheckoutCCForm").submit(function (e) {
    e.preventDefault();
    $("#twocheckout_error_creditcard, #twocheckout_error_creditcard_custom").hide();
    TCO.requestToken(successCallback, errorCallback, 'twocheckoutCCForm');
  });

  (function($) {
    $.QueryString = (function(a) {
      if (a == "") return {};
      var b = {};
      for (var i = 0; i < a.length; ++i)
        {
          var p=a[i].split('=');
          if (p.length != 2) continue;
          b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
        }
        return b;
    })(window.location.search.substr(1).split('&'))
  })(jQuery);
  if ($.QueryString["twocheckouterror"]) {
    $( "#twocheckout_error_creditcard" ).show();
  } else {
      $( "#twocheckout_error_creditcard" ).hide();
  }

  $('.numeric').on('blur', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
  });

  function clearFields () {
    $('#ccNo').val('');
    $('#expMonth').val('');
    $('#expYear').val('');
    $('#cvv').val('');
  }

</script>