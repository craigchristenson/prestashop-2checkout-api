<div class="payment_module" style="border: 1px solid #595A5E; padding: 0.6em; margin-left: 0.7em;">
    <p id="twocheckout-error" style="border: 1px solid red; padding: 0.6em; margin-bottom: 0.7em; color: red; background: #FFF">{l s='Payment Authorization Failed: Please verify your Credit Card details are entered correctly and try again, or try another payment method.' mod='twocheckout'}</p>
    <h3 class="twocheckout_title"><img alt="" src="{$module_dir}assets/secure-icon.png" />{l s='Pay by credit card with our secured payment server' mod='twocheckout'}</h3>
    <div class="error" style="display:none" id="twocheckout_error_creditcard">
    <p>{l s='Payment Authorization Failed: Please verify your Credit Card details are entered correctly and try again, or try another payment method.' mod='twocheckout'}</p>
    </div>    
    <form action="{$module_dir}validation.php" method="POST" id="twocheckoutCCForm" onsubmit="return false">
        <input id="sellerId" type="hidden" value="{$twocheckout_sid}">
        <input id="publishableKey" type="hidden" value="{$twocheckout_public_key}">
        <input id="token" name="token" type="hidden" value="">
        <div class="block-left">
            <label>{l s='Card Number' mod='twocheckout'}</label><br />
            <input class="numeric" type="text" size="20" autocomplete="off" id="ccNo" style="width: 210px; border: #CCCCCC solid 1px; padding: 3px;" required/>
        </div>
        <br />
        <div class="block-left">
            <label>{l s='Expiration (MM/YYYY)' mod='twocheckout'}</label><br />
            <select id="expMonth" name="month" style="border: #CCCCCC solid 1px; padding: 3px;" required>
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
            <select id="expYear" name="year" style="border: #CCCCCC solid 1px; padding: 3px;" required>
                <option value="2019">2019</option>
                <option value="2020">2020</option>
                <option value="2021">2021</option>
                <option value="2022">2022</option>
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026">2026</option>
                <option value="2027">2027</option>
                <option value="2028">2028</option>
                <option value="2029">2029</option>
                <option value="2030">2030</option>
		        <option value="2031">2031</option>
                <option value="2032">2032</option>
                <option value="2033">2033</option>
                <option value="2034">2034</option>
            </select>
        </div>
        <br />
        <div class="block-left">
            <label>{l s='CVC' mod='twocheckout'}</label><br />
            <input class="numeric" id="cvv" type="text" size="4" autocomplete="off"  style="border: #CCCCCC solid 1px; padding: 3px;" required />
        </div>
        <br />
        <input type="submit" class="button" value="{l s='Submit Payment' mod='twocheckout'}" />
        <div class="block-right">
            <img src="{$module_dir}assets/credit-cards.png" />
        </div>
    </form>
</div>