=== Y2KWebs Gateway for LAFISE ===
Contributors: y2kwebs
Tags: woocommerce, payments, costa-rica, lafise
Requires at least: 6.9
Requires PHP: 8.2
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Preliminary independent REST payment gateway for LAFISE Costa Rica. Free and open source.

== Description ==
Version 0.2.1 is a preliminary update prepared for review, not yet published. The current public release remains 0.2.0. Specific payment cases still need verification. Automated tests of this package use a simulated bank; see docs/validation.md for operational background, test results and remaining cases. The Stable tag identifies the package version.

A real USD 1 payment was verified with version 0.2.1: the cardholder completed a manual bank 3DS challenge, and a subsequent read-only check confirmed a paid WooCommerce order, approved attempt and stored bank references. Automated tests use a simulated bank. Coverage of specific 3DS branches, currencies, taxes and browsers continues to expand.

Card encryption in the browser, classic and block checkout, 3DS and payment reconciliation. No tracking, mandatory registration or checkout advertising.

Enter the credentials in the same order and with the same labels as the LAFISE approval email: Username, Password, TerminalID, MerchantID and API KEY. Password and API KEY are stored encrypted. Existing settings are preserved. No private configuration files or certification flags are required. Normal mode supports guest and registered buyers. Optional restricted testing mode uses real transactions.

Automatic bank refunds are not yet implemented. Direct financial Sale outcomes remain pending review; no automatic retry. Process3DS recognizes decline 59 only after signature and financial validation, with no authorization. Contradictory or unrecognized results remain uncertain. See docs/validation.md for the exact test coverage and limitations.

This is not an official LAFISE plugin. Professional installation and support: https://y2kwebs.com/

External service: when deliberately configured and enabled, sends encrypted card data and required order/payer details to https://lafise-cr.portalpos.com/Interfaces/ to process payments, and presents the issuing bank's 3DS verification. Server-to-server credentials never reach the browser. No developer-operated intermediary is used.

== Installation ==
Install WooCommerce, upload the ZIP, activate the plugin and enter your merchant credentials in WooCommerce > Settings > Payments > LAFISE.

== Changelog ==
= 0.2.1 =
Prepared for review, not yet published. Direct Sale challenge 3DS-PRC-002 uses one explicit code-bound signature rule, preserving minor amount units and the existing fingerprint formula. Unsafe signature variants prevent HTTP requests. Verified Process3DS decline 59 permits a new user-requested attempt; conflicting authorization remains uncertain with a fixed safe diagnostic. No changes to automatic refund support or unclassified direct financial Sale outcomes. New fixtures are synthetic. A cardholder-operated real USD 1 payment with a manual 3DS challenge was completed and its paid order and approved attempt were checked through the authenticated API.

= 0.2.0 =
Preliminary public release. Admin-managed encrypted credentials, normal checkout, optional restricted test mode, authenticated 3DS destinations and safe authentication retries. Direct Sale approvals and definitive decline codes need further verification; automatic bank refunds are not implemented.

Checkout availability fix: partially saved settings use the same Production request-type default as the admin form, instead of hiding an otherwise configured gateway. Explicit merchant-specific values are preserved.

Minimal encrypted diagnostics distinguish transport, HTTP, decoding and validation failures. An administrator-only read endpoint returns safe attempt summaries. Unknown outcomes remain pending; no automatic financial retry is introduced.
