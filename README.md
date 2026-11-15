# Coino Live crypto donations for GiveWP

Accept donations in USDT, Bitcoin, Ethereum, TRON, TON, Solana and more with GiveWP.

**Version 1.0.0** · requires GiveWP 3+, WordPress 6.6+, PHP 7.4+

- Download the ready-to-install zip: [coinolive-give-1.0.0.zip](https://coino.live/downloads/coinolive-give-1.0.0.zip)
- API reference: [coino.live/api-guide](https://coino.live/api-guide) · Support: [support@coino.live](mailto:support@coino.live)

## Description

Donors choose "Donate with crypto" on your GiveWP form (visual form builder or classic forms), pick a coin and network on the Coino Live payment page and send the amount shown. The donation updates by itself:

- **Processing** as soon as the payment is seen on the network (or if only part arrived, with a note),
- **Complete** once it is confirmed: GiveWP sends the donation receipt as usual,
- **Failed** if the payment window closes unpaid.

Donations are credited to your Coino Live balance; withdraw to your own wallet from the Coino Live dashboard. Donations > Coino Live shows your balance and every payment with its transactions.

- Amounts in your GiveWP currency (USD, EUR, GBP, TRY, BRL, RUB and more) are converted at the current rate.
- Secure by design: donation status always comes from the Coino Live API, never from the content of a webhook.

## Installation

1. Upload the plugin (Plugins > Add New > Upload Plugin) and activate it. GiveWP 3 or newer must be active.
2. In your Coino Live dashboard, open Developers and create an API key for your gateway.
3. Donations > Settings > Payment Gateways: open "Coino Live (crypto)", paste the key and save (the page confirms the connection), then enable the gateway under "Gateways" and on your forms.

Nothing else to configure: the webhook URL is sent with every payment. If webhooks cannot reach your site, open donations are still checked every five minutes and when the donor returns.

## Changelog

### 1.0.0
- First release for Coino Live API v2.

## License

GPL-2.0-or-later. See https://www.gnu.org/licenses/gpl-2.0.html
