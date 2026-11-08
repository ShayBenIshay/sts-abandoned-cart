# STS Abandoned Cart

Abandoned-cart recovery for WooCommerce stores in Israel, by email and WhatsApp. Built with Israeli Communications Law Amendment 40 (spam law) in mind: an opt-out link and sender identification are part of the messages.

## Features
- Captures carts and checkout details as shoppers type, including guests.
- A cron job (every 5 minutes) sends a reminder after a configurable delay.
- Channels: email, WhatsApp, or both.
- Unsubscribe link and an unsubscribe list that is honoured on every send.
- Admin screens under **WooCommerce > עגלות נטושות**: dashboard, cart list, resend, test send.
- Own database tables (carts, unsubscribes), removed by `uninstall.php`.

## Requirements
WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+. WhatsApp needs your own HTTP gateway (see below).

## Install
Copy the folder to `wp-content/plugins/`, activate, then open **WooCommerce > עגלות נטושות** and its settings.

## WhatsApp gateway
The plugin does not talk to WhatsApp directly. Set the endpoint URL and an auth header name/value in the settings; it POSTs JSON with `to` (E.164 phone) and the message text to that endpoint. Any small HTTP service that sends a WhatsApp message on that request will work. Email works without it.

## Compliance note
You are responsible for having a lawful basis to message your customers. The plugin helps (sender details, unsubscribe) but is not legal advice.

## Status

Version 1.0.0. Provided as-is, under GPL-2.0-or-later (see `LICENSE`). Published without full functional testing on a live store; try it on staging first.

Setup, customization and support are available from [Shay Tech Solutions](https://shaytechsolutions.com).
