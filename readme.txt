=== Klawoo Connector ===
Contributors: klawoo, storeapps, niravmehta, Tarun.Parswani
Tags: email marketing, newsletter, subscribe, klawoo, woocommerce, ecommerce, sync, marketing, analysis, campaigns, orders, customers
Requires at least: 3.3
Tested up to: 4.9.1
Stable tag: 1.9
License: GPL 3.0


== Description ==

Sync your WooCommerce store with [Klawoo](http://klawoo.com/) - The next generation customer engagement and marketing platform.

Klawoo Connector can automatically create lists for each product / variation / category and subscribe people to those lists based on their orders. 

All past orders are sent when you first configure this plugin. Future orders will be sent to Klawoo automatically.

You need a WooCommerce based store to use this plugin. You'd also need a Klawoo account, but that's free and can be created from this plugin itself. The connector uses Klawoo API for sync.

= Installation =

1. Ensure you have latest version of [WooCommerce](http://www.woothemes.com/woocommerce/) plugin installed
2. Unzip and upload contents of the plugin to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Click on 'Klawoo Connector ' option within WordPress admin sidebar menu

= Configuration =

Go to Wordpress > Klawoo

This is where you need to Sign Up for a Klawoo Account or Sign In using your Klawoo Email Address and Password to sync your past WooCommerce transactions to Klawoo and start sending mails to your existing WooCommerce customers using Klawoo.

1. If you already have a Klawoo account, sign in simply using your Klawoo email address and password. If you do not have a Klawoo account, you would need to to create one by signing up using your email address and any passworwd and a brand name for your account.
2. Enter your SMTP or Amazon SES settings that would be used for sending out mails using Klawoo.
3. Create customer lists based on your products, variations or categories by simply checking the desired options and then simply click on 'Save & Create Lists' button to create lists.
4. Click on "Sync Lists & Subscriptions" to send and subscribe all the WooCommerce customers to specific lists in Klawoo.

All past orders will be sent to Klawoo. New orders will be automatically synced.

== Frequently Asked Questions ==

= Why do I need this connector? =

Klawoo Connector uses Klawoo APIs to automatically sync customers to Klawoo subscribers. Without the connector you'd need to either place a form on your site and ask people to subscribe or import them manually. This connector automates the entire process.


= Can I use this with any ecommerce plugin? =

No, currently you can use this connector only with WooCommerce ecommerce plugin.

= What does it cost? =

Klawoo itself is free for up to 5000 subscribers. Beyond that, it’s a flat $99 per year.

= How does it create lists automatically? =

You can select how you want to create lists. Klawoo always maintains a "All Customers" master list. And can automatically create a list for each of your products, product variations and categories. When an order is completed, the customer is added to the master list, as well as individual lists for each product / variation / category in the order.

= How does it send emails? =

You'd need your own SMTP service to use Klawoo. There are many cost effective and reliable transactional / newsletter email service providers. We recommend Mandrill.

= What if an order is refunded? = 

On refund, the customer will be unsubscribed from the product / variation / category specific lists - if there was no other order from the same customer for that product. Basically, if customer no longer has access to the product, they are unsubscribed.

= Does this support WooCommerce Subscriptions? =

Yes.


== Screenshots ==

1. Klawoo Connector Settings Page

2. Klawoo Campaigns Reports

== Changelog ==

= 1.9 (09.01.2018) =
* New: Support for Subscription Product type
* New: Support for WooCommerce Chained Products
* Fix: WPDB query error in some cases
* Fix: Minor fixes

= 1.8 =
* New: PHP 7 compatibility
* Fix: Removed debug warnings
* Fix: Some minor fixes

= 1.7 =
* New: Syncing the MyAccount link for the subscribers
* Fix: New orders not getting synced
* Fix: Minor Fixes

= 1.6 =
* Update: Compatibility with new versions of WordPress & WooCommerce (v2.2 or greater)
* Fix: Minor Fixes

= 1.5 =
* Initial release on WordPress

== Upgrade Notice ==

= 1.9 =
Support for subscriptions and Chained Products along with some important updates and fixes, recommended upgrade.

= 1.8 =
PHP 7 compatibility along with some important updates and fixes, recommended upgrade. 

= 1.7 =
Fixed issue of new orders not getting synced along with some important updates and fixes, recommended upgrade. 

= 1.6 =
Compatibility with new versions of WordPress & WooCommerce (v2.2 or greater) along with some important updates and fixes, recommended upgrade. 

= 1.5 =
Welcome!!