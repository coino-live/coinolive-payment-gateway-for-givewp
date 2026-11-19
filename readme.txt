=== Coino Live Crypto Donations for GiveWP ===
Contributors: coinolive
Tags: crypto, donations, usdt, bitcoin, givewp
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept donations in USDT, Bitcoin, Ethereum, TRON, TON, Solana and more with GiveWP.

== Description ==

Donors choose "Donate with crypto" on your GiveWP form (visual form builder or classic forms), pick a coin and network on the Coino Live payment page and send the amount shown. The donation updates by itself:

* **Processing** as soon as the payment is seen on the network (or if only part arrived, with a note),
* **Complete** once it is confirmed: GiveWP sends the donation receipt as usual,
* **Failed** if the payment window closes unpaid.

Donations are credited to your Coino Live balance; withdraw to your own wallet from the Coino Live dashboard. Donations > Coino Live shows your balance and every payment with its transactions.

* Amounts in your GiveWP currency (USD, EUR, GBP, TRY, BRL, RUB and more) are converted at the current rate.
* Secure by design: donation status always comes from the Coino Live API, never from the content of a webhook.

== Installation ==

1. Upload the plugin (Plugins > Add New > Upload Plugin) and activate it. GiveWP 3 or newer must be active.
2. In your Coino Live dashboard, open Developers and create an API key for your gateway.
3. Donations > Settings > Payment Gateways: open "Coino Live (crypto)", paste the key and save (the page confirms the connection), then enable the gateway under "Gateways" and on your forms.

Nothing else to configure: the webhook URL is sent with every payment. If webhooks cannot reach your site, open donations are still checked every five minutes and when the donor returns.

== External services ==

This plugin connects to the Coino Live API (https://coino.live/api/v2), the crypto payment service it is built for. It cannot work without it.

* When a donor chooses Coino Live on a donation form, it sends the donation amount and currency, the donation ID and a short description ("Donation #… to your form or site name"), the donor's email, the return URL, the payment window, your site address and the webhook URL.
* To update donations (after a webhook, when the donor returns, and every five minutes for open donations), it reads the payment by its ID.
* It reads the list of supported currencies (at most every 12 hours) to check that your GiveWP currency can be used. The Donations > Coino Live page reads your balances and this site's payments.

Every request is authenticated with the API key you enter. The service is provided by Coino Live: [Terms of Service](https://coino.live/terms-of-service), [Privacy Policy](https://coino.live/privacy-policy).

== Changelog ==

= 1.0.1 =
* Tested with WordPress 7.1.
* Error messages are escaped where they are created.
* The readme lists what the plugin sends to the Coino Live API (External services).
* Plugin URI points to the plugins section of the Coino Live API guide.
* Translations load on init; the WordPress.org package gets them from translate.wordpress.org.
* A website address entered as the API URL now gives a clear error instead of an empty one.

= 1.0.0 =
* First release for Coino Live API v2.
