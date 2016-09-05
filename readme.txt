=== PayIQ WooCommerce Gateway ===
Contributors: pekz0r, payiq, angrycreative, kylegard
Tags: PayIQ, WooCommerce, Gateway, Payments, WooCommmerce Gateway, PSP
Requires at least: 4.0
Tested up to: 4.5
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugins integrates you WooCommerce store with PayIQs payment service

== Description ==

This plugins integrates you WooCommerce store with PayIQs payment service.
The customer will be sent to an external payment window where they will asked to fill in their card information. The available payment methods in the payment window is card and bank transfer(BACS).

Read more on [PayIQ.se](http://payiq.se/ "PayIQ.se - Maximera din försäljning på nätet").

== Installation ==

The easiest way to install the plugin is searching for `payiq` in WordPress admin and click install. Click activate when the plugin is installed.

When the plugin is installed and activated go to `WooCommerce > Settings > Checkout > PayIQ`. Fill in `Service name`, `Shared secret` and any other fields you want to configure.

== Frequently Asked Questions ==

= Will I need SSL/HTTP to make my shop PCI compliant? =

No. All the card information is handled in a hosted payment window and will not touch your servers. Therefore you do not need PCI compliance.

== Screenshots ==

1. Settings in WP Admin
2. Payment selector in checkout

== Changelog ==

= 2.0 =
* Added functionallty to have subscription payments. 

= 1.0.2 =
* Fix issues where order total is rounded in error when calculating the checksum.

= 1.0.1 =
* Bugfixes and PHP version 5.3 compatibility

= 1.0.0 =
* First release

== Upgrade Notice ==

= 1.0.2 =
* Bugfixes. Please update.

= 1.0.1 =
* Bugfixes. Please update.

= 1.0 =
First release