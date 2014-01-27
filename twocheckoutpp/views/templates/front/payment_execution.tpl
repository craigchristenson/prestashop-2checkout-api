<h1>{l s='We are redirecting you to complete your payment with PayPal.' mod='twocheckoutpp'}</h1>

<form name="checkout_confirmation" id="twocheckout-form" action="{$CheckoutUrl}" method="post" />
    <input type="hidden" name="lang" value="{$lang_iso}">
    <input type="hidden" name="sid" value="{$sid}" />
    <input type="hidden" name="merchant_order_id" value="{$cart_order_id}" />
    <input type="hidden" name="return_url" value="{$link->getPageLink('order', true, NULL, "step=3")}" />
    <input type="hidden" name="purchase_step" value="payment-method" />
    <input type="hidden" name="card_holder_name" value="{$card_holder_name}" />
    <input type="hidden" name="street_address" value="{$street_address}" />
    <input type="hidden" name="street_address2" value="{$street_address2}" />
    <input type="hidden" name="city" value="{$city}" />
    <input type="hidden" name="state" value="{if $state}{$state->name}{else}{$outside_state}{/if}" />
    <input type="hidden" name="zip" value="{$zip}" />
    <input type="hidden" name="country" value="{$country}" />
    <input type="hidden" name="ship_name" value="{$ship_name}" />
    <input type="hidden" name="ship_street_address" value="{$ship_street_address}" />
    <input type="hidden" name="ship_street_address2" value="{$ship_street_address2}" />
    <input type="hidden" name="ship_city" value="{$ship_city}" />
    <input type="hidden" name="ship_state" value="{if $ship_state}{$ship_state->name}{else}{$outside_state}{/if}" />
    <input type="hidden" name="ship_zip" value="{$ship_zip}" />
    <input type="hidden" name="ship_country" value="{$ship_country}" />
    {if sprintf("%01.2f", $check_total) == sprintf("%01.2f", $total) && $override_currency == 0}
        {counter assign=i}
        {foreach from=$products item=product}
        <input type="hidden" name="mode" value="2CO" />
        <input type="hidden" name="li_{$i}_product_id" value="{$product.id_product}" />
        <input type="hidden" name="li_{$i}_quantity" value="{$product.quantity}" />
        <input type="hidden" name="li_{$i}_name" value="{$product.name}" />
        <input type="hidden" name="li_{$i}_description" value="{$product.description_short}" />
        <input type="hidden" name="li_{$i}_price" value="{sprintf("%01.2f", $product.price)}" />
        {counter print=false}
        {/foreach}
        {if isset($shipping_cost)}
            {counter assign=i}
            <input type="hidden" name="li_{$i}_type" value="shipping" />
            <input type="hidden" name="li_{$i}_name" value="{$carrier}" />
            <input type="hidden" name="li_{$i}_price" value="{$shipping_cost}" />
        {/if}
        {if isset($tax)}
            {counter assign=i}
            <input type="hidden" name="li_{$i}_type" value="tax" />
            <input type="hidden" name="li_{$i}_name" value="Tax" />
            <input type="hidden" name="li_{$i}_price" value="{$tax}" />
        {/if}
        {if isset($discount)}
            {counter assign=i}
            <input type="hidden" name="li_{$i}_type" value="coupon" />
            <input type="hidden" name="li_{$i}_name" value="Discounts" />
            <input type="hidden" name="li_{$i}_price" value="{$discount}" />
        {/if}
    {else}
        {counter assign=i}
        {foreach from=$products item=product}
        <input type="hidden" name="id_type" value="1" />
        <input type="hidden" name="c_prod_{$i}" value="{$product.id_product},{$product.quantity}" />
        <input type="hidden" name="c_name_{$i}" value="{$product.name}" />
        <input type="hidden" name="c_description_{$i}" value="{$product.description_short}" />
        <input type="hidden" name="c_price_{$i}" value="{sprintf("%01.2f", $product.price)}" />
        {counter print=false}
        {/foreach}
    <input type="hidden" name="cart_order_id" value="{$cart_order_id}" />
    <input type="hidden" name="total" value="{$total}" />
    {/if}
    <input type="hidden" name="email" value="{$email}" />
    <input type="hidden" name="phone" value="{$phone}" />
    <input type="hidden" name="currency_code" value="{$currency_code}" />
    <input type="hidden" name="paypal_direct" value="Y" />
</form>

<script type="text/javascript">
    var myForm = document.getElementById('twocheckout-form');
    myForm.submit(); 
</script>
