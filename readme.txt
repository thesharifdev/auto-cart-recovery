=== Auto Cart Recovery ===
Contributors: wpsharif
Tags: woocommerce, abandoned cart, cart recovery, email, coupons
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically track abandoned WooCommerce carts, send recovery emails with coupons, and restore carts with discounts when customers return.

== Description ==

**Auto Cart Recovery** helps you recover lost WooCommerce sales by automatically detecting abandoned carts and sending recovery emails with discount coupons.

The plugin tracks cart activity, creates personalized coupons, and sends secure recovery links. When a customer clicks the recovery link, their cart is restored and the coupon is automatically applied at checkout.

### Key Features

* Automatically capture abandoned WooCommerce carts
* Dashboard with recovery stats and revenue
* Create coupons automatically (percentage or fixed)
* Configurable email delay and reminder limits
* Manual “Send email” option from admin
* Restore cart + apply coupon via secure recovery link
* Supports logged-in users and guests
* No custom database tables – uses WordPress & WooCommerce APIs
* Secure, lightweight, and developer-friendly

### How It Works

1. Tracks cart updates using WooCommerce hooks
2. Saves cart data in a custom post type
3. Automatically creates a WooCommerce coupon per cart
4. Sends recovery emails via WP-Cron
5. Restores cart and applies coupon when the customer returns
6. Marks carts as recovered after successful orders

== Installation ==

1. Upload the `auto-cart-recovery` folder to `/wp-content/plugins/`
2. Go to **Plugins → Installed Plugins**
3. Activate **Auto Cart Recovery**
4. Make sure **WooCommerce** is active

After activation:
* A custom post type `acr_abandoned_cart` is registered
* A cron job runs every 15 minutes
* Default settings are created automatically

== Screenshots ==

1. Auto Cart Recovery dashboard with stats  
2. Abandoned carts list with manual email action  
3. Settings page for emails, coupons, and tracking  

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
Yes. WooCommerce is required and declared in the plugin header.

= Does it work with guest customers? =
Yes, if **Include guests** is enabled and a valid email address is available.

= How many recovery emails are sent? =
Automatic emails are limited by the **Maximum reminders per cart** setting. Manual sends are unlimited.

= Are coupons created automatically? =
Yes. Each abandoned cart gets its own WooCommerce coupon, created based on your settings.

= Where are coupons stored? =
Coupons are standard WooCommerce coupons, tagged with `_acr_coupon = yes`.

= Does it create custom database tables? =
No. All data is stored using custom post types, post meta, and WooCommerce coupons.

== Changelog ==

= 1.0.0 =
* Initial release
* Abandoned cart tracking
* Automatic coupon generation
* Recovery emails with secure links
* Dashboard stats and reporting
* Manual email sending
* Cart restoration and auto-apply coupons

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developer Notes ==

* Custom post type: `acr_abandoned_cart`
* Uses WP-Cron (`acr_process_abandoned_carts`)
* Uses Transients API for dashboard caching
* Fully secured with nonces and capability checks
* Developer hooks and filters available:
  * `acr_cart_captured`
  * `acr_cart_recovered`
  * `acr_recovery_email_sent`
  * `acr_coupon_created`
  * `acr_recovery_url`
  * `acr_manage_capability`

== Support ==

If recovery emails are not sending:
* Ensure **Enable Recovery** is on
* Check your site’s email configuration
* Confirm WooCommerce is active

If recovery links don’t work:
* Go to **Settings → Permalinks** and click **Save**
* Check for theme or plugin conflicts
