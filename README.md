### _[Signup free with 2Checkout and start selling!](https://www.2checkout.com/referral?r=git2co)_

### Integrate PrestaShop with 2Checkout Payment API (Supports PayPal Direct)
----------------------------------------

### 2Checkout Payment API Setup

#### PrestaShop Settings

1. Download the 2Checkout payment module from https://github.com/craigchristenson/prestashop-2checkout-api
2. Upload the 'twocheckout' directory to your PrestaShop 'modules' directory.
3. Sign in to your PrestaShop admin.
4. Under **Modules** click **Modules**.
5. Under **2Checkout Payment API** click **Install**.
6. Enter your **2Checkout Account Number**. _(2Checkout Seller ID)_
7. Enter your **Publishable Key**. _(2Checkout Publishable Key)_
8. Enter your **Private Key**. _(2Checkout Private Key)_
9. Select **No** under **Sandbox Mode**. _(Unless you are testing in the 2Checkout Sandbox)_
10. Click **Update Settings**.



### 2Checkout PayPal Direct Setup

#### PrestaShop Settings

1. Upload the 'twocheckoutpp' directory to your PrestSshop 'modules' directory.
2. Sign in to your PrestaShop admin.
3. Click **Extensions** tab and **Payments subtab**.
4. Under **Modules** click **Modules**.
5. Enter your **2Checkout Account Number**. _(2Checkout Seller ID)_
6. Enter your **Secret Word** _(Must be the same value entered on your 2Checkout Site Management page.)_
7. Select whether you wan to use the customer's chosen currency or force another currency.
8. Click **Update Settings**.


#### 2Checkout Settings

1. Sign in to your 2Checkout account.
2. Click the **Account** tab and **Site Management** subcategory.
3. Under **Direct Return** select **Header Redirect**.
4. Enter your **Secret Word**. _(Must be the same value entered in your PrestaShop admin.)_
5. Set the **Approved URL** to https://www.yourstore.com/index.php?fc=module&module=twocheckoutpp&controller=validation _(Replace https://www.yourstore.com with the actual URL to your store.)_
6. Click **Save Changes**.

Please feel free to contact 2Checkout directly with any integration questions.
