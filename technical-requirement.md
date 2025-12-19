### 11. Installation & Activation Using WordPress Hooks
```php
/**
 * On activation - register post type and flush rewrite rules
 */
register_activation_hook(__FILE__, 'acr_activation');

function acr_activation() {
    // Register post type
    acr_register_post_type();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Schedule cron
    if (!wp_next_scheduled('acr_process_abandoned_carts')) {
        wp_schedule_event(time(), 'fifteen_minutes', 'acr_process_abandoned_carts');
    }
    
    // Set activation time
    update_option('acr_activation_time', current_time('timestamp'));
    update_option('acr_plugin_version', ACR_VERSION);
}

/**
 * On deactivation - clear cron
 */
register_deactivation_hook(__FILE__, 'acr_deactivation');

function acr_deactivation() {
    // Clear cron
    $timestamp = wp_next_scheduled('acr_process_abandoned_carts');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'acr_process_abandoned_carts');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * On uninstall - clean up data
 */
register_uninstall_hook(__FILE__, 'acr_uninstall');

function acr_uninstall() {
    // Delete all abandoned cart posts
    $carts = get_posts([
        'post_type' => 'acr_abandoned_cart',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ]);
    
    foreach ($carts as $cart_id) {
        wp_delete_post($cart_id, true);
    }
    
    // Delete all generated coupons (optional)
    // Delete options
    delete_option('acr_activation_time');
    delete_option('acr_plugin_version');
    delete_option('acr_settings');
    
    // Delete transients
    delete_transient('acr_dashboard_stats');
}
```

### 12. WordPress Native System Benefits

✅ **No Custom Database Tables** - Use WordPress CPT and post meta
✅ **Built-in Querying** - Use WP_Query for all data retrieval
✅ **Native Caching** - Use transients API
✅ **Standard Admin UI** - Use WP_List_Table and admin styles
✅ **WordPress Cron** - Use wp_cron system
✅ **Native Email** - Use wp_mail()
✅ **WooCommerce Integration** - Use WC native coupon system
✅ **Security** - Use WordPress nonces and capabilities
✅ **Sanitization** - Use WordPress sanitization functions
✅ **Theme Compatibility** - Follow WordPress standards
✅ **Easy Backup** - All data in standard WP database
✅ **Plugin Conflicts** - Reduced due to standard systems
✅ **Performance** - Leverage WordPress caching
✅ **Maintenance** - Easier updates and debugging

## Implementation Instructions

Generate a complete, production-ready WordPress plugin following all requirements above. The plugin MUST:

1. **Use ONLY WordPress native systems** - NO custom database tables
2. Use **Custom Post Type** for storing abandoned carts
3. Use **post meta** for all cart data
4. Use **WP_Query** for all data queries
5. Use **WordPress transients** for caching
6. Use **wp_cron** for scheduled tasks
7. Use **wp_mail()** for emails
8. Use **WooCommerce native coupon** system
9. Use **WP_List_Table** for admin tables
10. Use **WordPress admin notices** for notifications
11. Use **WordPress capabilities** for permissions
12. Use **WordPress nonces** for security
13. Use **WordPress sanitization/escaping** functions
14. Main plugin file MUST be a **final class**
15. Implement **singleton pattern** correctly
16. Include complete **recovery link system**
17. Follow **WordPress Coding Standards**
18. Be **translation-ready**
19. Work **automatically** once activated

**Critical Priority:**
- NO custom database tables (use CPT)
- Use WordPress native functions everywhere possible
- Main file = final class
- Recovery link = automatic cart + coupon restore
- Developer-friendly with hooks

This approach makes the plugin more maintainable, compatible, and follows WordPress best practices.