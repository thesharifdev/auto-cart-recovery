Auto Cart Recovery
==================

Recover abandoned WooCommerce carts using native WordPress systems (CPT, cron, transients, wp_mail, and WooCommerce coupons).  
This plugin automatically tracks abandoned carts, sends recovery emails with coupons, and restores the cart + applies the coupon when the customer comes back.

Screenshots
-----------

- `screenshots/dashboard.png` – Auto Cart Recovery dashboard cards (total carts, recovered carts, recovered revenue, emails sent).
- `screenshots/abandoned-carts.png` – Abandoned Carts table with manual “Send email” action.
- `screenshots/settings.png` – Plugin settings page (email delay, discount type/amount, coupon expiry, etc.).

Requirements
------------

- WordPress (latest recommended).
- WooCommerce (required – declared via `Requires Plugins: woocommerce` in the plugin header).

Installation
------------

1. Copy the `auto-cart-recovery` folder to `wp-content/plugins/` on your site.
2. In WordPress admin, go to **Plugins → Installed Plugins**.
3. Activate **Auto Cart Recovery**.
4. Make sure WooCommerce is also active.

After activation:

- A custom post type `acr_abandoned_cart` is registered.
- A cron event `acr_process_abandoned_carts` is scheduled every 15 minutes.
- Basic options and transients are created.

Where to Find It in Admin
-------------------------

After activation you get a new **Cart Recovery** menu in the left WordPress admin sidebar:

- **Cart Recovery → Cart Recovery** – dashboard with high‑level stats.
- **Cart Recovery → Abandoned Carts** – list of all captured carts + manual send buttons.
- **Cart Recovery → Settings** – all configuration for emails, coupons, and tracking.

How It Works
------------

### 1. Capturing abandoned carts

The plugin listens to WooCommerce cart activity:

- On every cart update (`woocommerce_cart_updated`) it:
  - Reads the current cart contents from `WC()->cart`.
  - Stores a cart snapshot into the custom post type `acr_abandoned_cart` using **post meta**:
    - Items (product IDs, variations, quantities, extra cart data).
    - Cart total.
    - Customer identifiers (user ID, Woo session ID, email if available).
    - Timestamps and status.
  - If this is the first time for this cart, status is set to **new**.
  - If no existing abandoned cart is found for that customer, a new one is created; otherwise, the latest record is updated.

### 2. Automatic coupon creation

When an abandoned cart record is created/updated, the plugin ensures a **WooCommerce coupon** exists for that cart:

- A `shop_coupon` post is created with:
  - **Discount type** from settings:
    - **Percentage discount** (`percent`).
    - **Fixed cart discount** (`fixed_cart`).
  - **Discount amount** from settings (e.g. `15` for 15% or 15 of your currency).
  - **Coupon expiry (days)** from settings – converted to a date in the future.
  - Individual use only.
  - Usage limit: 1 (per coupon and per user).
  - Meta `_acr_coupon = yes` to tag it as created by this plugin.
- The coupon ID is stored on the cart record in `_acr_coupon_id`.

If your settings are misconfigured (e.g. amount `0`), the plugin falls back to a safe default discount so a coupon is always usable.

### 3. Scheduling and automatic emails

- A WordPress cron job (`acr_process_abandoned_carts`) runs every 15 minutes.
- For each **new** cart whose last update time is older than the configured delay (in minutes), it:
  1. Generates a secure recovery token.
  2. Builds a recovery URL that includes the token (e.g. `/acr/recover/{token}/`).
  3. Makes sure there is a coupon for this cart.
  4. Sends a recovery email via `wp_mail()` using the email template and settings.
  5. Tracks how many recovery emails have been sent for that cart and when.
- The **Maximum reminders per cart** setting limits how many automatic (cron) emails are sent for any single cart.

### 4. Manual “Send email” from Abandoned Carts table

In **Cart Recovery → Abandoned Carts**:

- You see a table with columns for Email, Status, Cart Total, Last Updated, Emails Sent, and an **Actions** column.
- Each row has a **“Send email”** button.
- Clicking this button:
  - Triggers a secure admin action (`admin-post.php?action=acr_send_email&cart_id=...` with a nonce).
  - Immediately sends a recovery email for that cart, **even if previous emails were already sent**.
  - Shows a success notice after the email is sent.

Manual sends ignore the “Maximum reminders per cart” limit (it only applies to automatic cron sends), so you can resend as many times as needed.

### 5. Recovery email contents

Each recovery email includes:

- **Subject** – from settings (default: *You left something in your cart*).
- **Heading + body text** – from settings; defaults explain that the cart was saved and a discount is available.
- **Cart summary**:
  - Product names and quantities.
  - Cart total, formatted with WooCommerce currency.
- **Coupon details** (if a coupon exists for that cart):
  - The **coupon code**.
  - The **discount type and amount** (e.g. “15% off” or “15.00 off”).
- **Call‑to‑action button**:
  - Label: *Go to checkout*.
  - URL: the special **recovery URL** that restores the cart and applies the coupon.

Emails are sent using standard `wp_mail()` with HTML content type and from‑name/from‑email taken from settings or sensible defaults.

### 6. Recovery link and automatic coupon/apply

The recovery URL is handled by a dedicated controller:

- A rewrite rule maps `/acr/recover/{token}` to a query var.
- When the customer clicks the email button:
  1. The plugin validates the token and finds the corresponding abandoned cart.
  2. Optionally checks token age vs. configured **Coupon expiry (days)**.
  3. Restores all stored cart items into `WC()->cart`.
  4. Reads the coupon attached to the cart (`_acr_coupon_id`) and calls `WC()->cart->apply_coupon( $coupon_code )`.
  5. Redirects the customer directly to the **WooCommerce checkout page** with their cart and coupon already applied.

If no valid cart or coupon is found, the customer is safely redirected to the standard cart page or checkout without errors.

### 7. Marking carts as recovered

When an order is placed:

- On `woocommerce_thankyou`, the plugin:
  - Finds the most recent abandoned cart associated with the order’s billing email.
  - Marks that cart as **recovered**.
  - Stores the WooCommerce order ID for dashboard reporting.

Dashboard & Reporting
---------------------

The **Cart Recovery** dashboard shows:

- **Total Abandoned Carts** – number of `acr_abandoned_cart` posts.
- **Recovered Carts** – number of carts with recovered status.
- **Recovered Revenue** – sum of order totals linked to recovered carts.
- **Emails Sent** – total count of all recovery emails sent.

The values are generated using `WP_Query` and cached in a transient for performance.

Settings Reference
------------------

All settings are under **Cart Recovery → Settings**:

- **Enable Recovery**  
  Turn tracking and emails on/off.

- **Delay before email (minutes)**  
  How long after the last cart activity the system should wait before sending the first automatic email.

- **Maximum reminders per cart**  
  Maximum number of automatic recovery emails per cart. Manual sends from the table are not limited.

- **Discount type**  
  - *Percentage discount* (`percent`).  
  - *Fixed cart discount* (`fixed_cart`).

- **Discount amount**  
  Numeric value for the coupon amount. Works with the selected discount type.

- **Coupon expiry (days)**  
  Number of days before the coupon expires. `0` means no explicit expiry.

- **Minimum cart total**  
  Only track/send for carts with a total at or above this value.

- **Include guests**  
  If enabled, tracks and emails guest customers (requires a valid billing email in WooCommerce).

- **From name / From email**  
  Values used in email headers. Defaults to the site name and admin email if left empty.

- **Email subject, heading, body**  
  Customizable text for the recovery emails. Basic HTML is supported in the body.

Developer Notes
---------------

- No custom database tables are used; everything is stored as:
  - CPT: `acr_abandoned_cart`.
  - Post meta for cart details and status.
  - `shop_coupon` CPT for coupons with `_acr_coupon = yes`.
- All querying is done with **WP_Query** and standard WordPress APIs.
- Caching uses the **Transients API** (e.g. `acr_dashboard_stats`).
- Security:
  - All admin actions are protected with capability checks and nonces.
  - Recovery URLs use secure random tokens stored in post meta.
- Hooks/filters available (examples):
  - `acr_loaded`, `acr_activated`, `acr_deactivated`, `acr_uninstalled`.
  - `acr_cart_captured`, `acr_cart_recovered`, `acr_recovery_email_sent`, `acr_recovery_link_used`.
  - `acr_coupon_created`, `acr_delete_coupons_on_uninstall`.
  - `acr_recovery_email_args`, `acr_recovery_url`, `acr_coupon_code`, `acr_get_settings`, `acr_manage_capability`.

Support & Troubleshooting
-------------------------

- If emails are not sending, check:
  - That **Enable Recovery** is active.
  - Your site can send email (e.g. test with another plugin or WP Mail logging).
  - WooCommerce is active and the cart actually has products and a valid email.
- If coupons are missing:
  - Confirm **Discount amount** is greater than 0.
  - Look in **Marketing → Coupons** (or **WooCommerce → Coupons** depending on WooCommerce version) for coupons tagged with meta `_acr_coupon = yes`.
- If recovery links do not restore carts:
  - Visit **Settings → Permalinks** and click **Save** to flush rewrite rules.
  - Ensure your theme or other plugins are not redirecting away from the recovery URL.

