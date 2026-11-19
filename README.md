# Coino Live Crypto Donation Plugin for GiveWP (WordPress)

Accept crypto donations in USDT, USDC, Bitcoin, Ethereum, Litecoin, TRON, TON, Solana and more on your GiveWP donation forms. This free, open-source WordPress plugin connects GiveWP to [Coino Live](https://coino.live), a crypto payment gateway. Donations are credited to your Coino Live balance, and you withdraw them to your own wallet.

**Version 1.0.1** · Requires GiveWP 3+, WordPress 6.6+, PHP 7.4+ · License: GPL-2.0-or-later

- **Download** the ready-to-install zip: [coinolive-give-1.0.1.zip](https://coino.live/downloads/coinolive-give-1.0.1.zip)
- **API reference:** [coino.live/api-guide](https://coino.live/api-guide)
- **Support:** [support@coino.live](mailto:support@coino.live)

## Features

- **117 assets on 11 networks:** Bitcoin, Litecoin, Ethereum, BNB Smart Chain, opBNB, Polygon, Base, Arbitrum, TRON, TON and Solana. USDT and USDC are available on most of them. Dogecoin and Bitcoin Cash are not accepted.
- **Fees:** the plugin is free. Coino Live charges a 0.25% platform fee per payment. Blockchain network fees are separate.
- **No KYC:** sign up at [coino.live](https://coino.live) with an email and password.
- **Withdraw to your own wallet:** donations are credited to your Coino Live balance, and you withdraw them from the Coino Live dashboard.
- **Currency conversion:** amounts in your GiveWP currency (USD, EUR, GBP, TRY, BRL, RUB and more) are converted at the current rate.
- **Works with both form types:** the GiveWP visual form builder and classic forms.
- **API-verified status:** donation status always comes from the Coino Live API, never from the content of a webhook.

## How crypto donations work

1. The donor chooses **"Donate with crypto"** on your GiveWP form.
2. On the Coino Live payment page, the donor picks a coin and network and sends the amount shown.
3. The donation status updates automatically:
   - **Processing** as soon as the payment is seen on the network. If only part of the amount arrived, the status is also Processing and a note is added.
   - **Complete** once the payment is confirmed. GiveWP sends the donation receipt as usual.
   - **Failed** if the payment window closes unpaid.

Open **Donations > Coino Live** to see your balance and every payment with its transactions.

## Installation

1. Upload the plugin (**Plugins > Add New > Upload Plugin**) and activate it. GiveWP 3 or newer must be active.
2. Sign up at [coino.live](https://coino.live) with an email and password if you do not have an account yet.
3. In your Coino Live dashboard, open **Developers** and create an API key for your gateway.
4. Go to **Donations > Settings > Payment Gateways** and open **"Coino Live (crypto)"**.
5. Paste the API key and save. The page confirms the connection.
6. Enable the gateway under **"Gateways"** and on your forms.

There is nothing else to configure: the webhook URL is sent with every payment. If webhooks cannot reach your site, open donations are still checked every five minutes and when the donor returns.

## FAQ

**Which cryptocurrencies can donors use?**  
117 assets on Bitcoin, Litecoin, Ethereum, BNB Smart Chain, opBNB, Polygon, Base, Arbitrum, TRON, TON and Solana, including USDT and USDC on most of these networks. Dogecoin and Bitcoin Cash are not accepted.

**What does it cost?**  
The plugin is free. Coino Live charges a 0.25% platform fee per payment. Blockchain network fees are separate.

**Do I need to complete KYC?**  
No. You sign up for Coino Live with an email and password.

**Where do the donations go?**  
They are credited to your Coino Live balance. You withdraw them to your own wallet from the Coino Live dashboard.

## Changelog

### 1.0.1
- Tested with WordPress 7.1.
- Error messages are escaped where they are created.
- The readme lists what the plugin sends to the Coino Live API (External services).
- Plugin URI points to the plugins section of the Coino Live API guide.
- Translations load on init; the WordPress.org package gets them from translate.wordpress.org.
- A website address entered as the API URL now gives a clear error instead of an empty one.

### 1.0.0
- First release for Coino Live API v2.

## License

GPL-2.0-or-later. See https://www.gnu.org/licenses/gpl-2.0.html
