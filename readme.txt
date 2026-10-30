=== Y2KWebs Gateway for LAFISE ===
Contributors: y2kwebs
Tags: woocommerce, payments, costa-rica, lafise
Requires at least: 6.9
Requires PHP: 8.2
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Preliminary independent REST payment gateway for LAFISE Costa Rica. Free and open source.

== Description ==
Version 0.2.0 is the first preliminary public release of a functional gateway based on an integration used for real payments. Specific payment cases still need verification. Automated tests of this package use a simulated bank; see docs/validation.md for operational background, test results and remaining cases. The Stable tag identifies the package version.

A maintainer-operated real USD 1 payment was verified with this version: paid and completed WooCommerce order, approved attempt and stored bank references. This verifies that individual flow, not every payment scenario.

Card encryption in the browser, classic and block checkout, 3DS and payment reconciliation. No tracking, mandatory registration or checkout advertising.

Enter the credentials in the same order and with the same labels as the LAFISE approval email: Username, Password, TerminalID, MerchantID and API KEY. Password and API KEY are stored encrypted. Existing settings are preserved. No private configuration files or certification flags are required. Normal mode supports guest and registered buyers. Optional restricted testing mode uses real transactions.

Automatic bank refunds are not yet implemented. Unrecognized direct Sale responses remain pending review; no automatic retry. See docs/validation.md for the exact test coverage and limitations.

This is not an official LAFISE plugin. Professional installation and support: https://y2kwebs.com/

External service: when deliberately configured and enabled, sends encrypted card data and required order/payer details to https://lafise-cr.portalpos.com/Interfaces/ to process payments, and presents the issuing bank's 3DS verification. Server-to-server credentials never reach the browser. No developer-operated intermediary is used.

== Installation ==
Install WooCommerce, upload the ZIP, activate the plugin and enter your merchant credentials in WooCommerce > Settings > Payments > LAFISE.

== Changelog ==
= 0.2.0 =
Preliminary public release. Admin-managed encrypted credentials, normal checkout, optional restricted test mode, authenticated 3DS destinations and safe authentication retries. Direct Sale approvals and definitive decline codes need further verification; automatic bank refunds are not implemented.

Checkout availability fix: partially saved settings use the same Production request-type default as the admin form, instead of hiding an otherwise configured gateway. Explicit merchant-specific values are preserved.

Minimal encrypted diagnostics distinguish transport, HTTP, decoding and validation failures. An administrator-only read endpoint returns safe attempt summaries. Unknown outcomes remain pending; no automatic financial retry is introduced.
