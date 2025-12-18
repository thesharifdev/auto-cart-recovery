<?php

namespace Auto_Cart_Recovery;

defined('ABSPATH') || exit;

/**
 * Admin Interface - Renders admin pages and handles admin actions.
 */
class Admin_Interface
{
    private $database_manager;
    private $plugin_version;

    public function __construct(Database_Manager $database_manager, $plugin_version = ACR_VERSION)
    {
        $this->database_manager = $database_manager;
        $this->plugin_version = $plugin_version;
    }

    public function register_hooks()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Abandoned Carts',
            'Cart Recovery',
            'manage_woocommerce',
            'auto-cart-recovery',
            array($this, 'admin_page'),
            'dashicons-cart',
            56
        );
    }

    public function enqueue_admin_styles($hook_suffix)
    {
        if ($hook_suffix !== 'toplevel_page_auto-cart-recovery') {
            return;
        }

        wp_enqueue_style(
            'acr-admin-style',
            plugins_url('assets/css/style.css', dirname(__FILE__)),
            array(),
            $this->plugin_version
        );
    }

    public function admin_page()
    {
        global $wpdb;

        // Handle reset all data
        if (isset($_POST['acr_reset_all_data']) && check_admin_referer('acr_reset_all_data_nonce')) {
            $truncated = $this->database_manager->truncate_all();

            if ($truncated !== false) {
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>All abandoned cart data has been reset successfully!</p></div>');
                });
            } else {
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-error is-dismissible"><p>Error resetting data. Please try again.</p></div>');
                });
            }
        }

        // Handle cart deletion
        if (isset($_POST['acr_delete_cart']) && check_admin_referer('acr_delete_cart_nonce')) {
            $cart_id = intval($_POST['cart_id']);
            $deleted = $this->database_manager->delete_cart($cart_id);

            if ($deleted) {
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>Abandoned cart deleted successfully!</p></div>');
                });
            } else {
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-error is-dismissible"><p>Error deleting cart. Please try again.</p></div>');
                });
            }
        }

        // Handle manual email sending
        if (isset($_POST['acr_send_recovery_email']) && check_admin_referer('acr_send_recovery_email_nonce')) {
            $cart_id = intval($_POST['cart_id']);
            $cart_record = $this->database_manager->get_cart_by_id($cart_id);

            if ($cart_record && $cart_record->email) {
                $email_manager = new Email_Manager($this->database_manager);
                $email_manager->send_recovery_email($cart_record);
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-success is-dismissible"><p>Recovery email sent successfully!</p></div>');
                });
            } else {
                add_action('admin_notices', function () {
                    echo wp_kses_post('<div class="notice notice-error is-dismissible"><p>Error: Cart not found or no email available.</p></div>');
                });
            }
        }

        // Check if table exists
        $table_exists = $this->database_manager->table_exists();

        if (!$table_exists) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html('🛒 Auto Cart Recovery Dashboard'); ?></h1>
                <div class="notice notice-error">
                    <p><strong><?php echo esc_html('Database table missing!'); ?></strong></p>
                    <p><?php echo esc_html("The plugin's database table was not created. Please try:"); ?></p>
                    <ol>
                        <li><?php echo esc_html('Deactivate and reactivate the plugin'); ?></li>
                        <li><?php echo esc_html('Or click the button below to create the table manually'); ?></li>
                    </ol>
                    <form method="post" action="">
                        <?php wp_nonce_field('acr_create_table'); ?>
                        <button type="submit" name="acr_create_table" class="button button-primary">
                            <?php echo esc_html('Create Database Table Now'); ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php

            if (isset($_POST['acr_create_table']) && check_admin_referer('acr_create_table')) {
                $this->database_manager->create_table();
                echo wp_kses_post('<div class="notice notice-success"><p>' . esc_html('Table created! Please refresh this page.') . '</p></div>');
            }

            return;
        }

        $abandoned_time = apply_filters('acr_abandoned_time_threshold', '-2 minutes');
        $time_threshold = date('Y-m-d H:i:s', strtotime($abandoned_time));

        $stats = $this->database_manager->get_stats($time_threshold);
        $recent_carts = $this->database_manager->get_recent_carts($time_threshold);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html('🛒 Auto Cart Recovery Dashboard'); ?></h1>
            
            <div style="margin-bottom: 12px; margin-top: 20px">
                <form method="post" action="" class="acr-reset-form">
                    <?php wp_nonce_field('acr_reset_all_data_nonce'); ?>
                    <input type="hidden" name="acr_reset_all_data" value="1">
                    <button type="submit" class="button button-link-delete" onclick="return confirm('WARNING: This will permanently delete ALL abandoned cart data. This action cannot be undone. Are you sure?');">
                        <?php echo esc_html('🗑️ Reset All Data'); ?>
                    </button>
                </form>
            </div>

            <div class="acr-stats-grid">
                <div class="acr-stat-card">
                    <h3><?php echo esc_html('Total Abandoned'); ?></h3>
                    <p class="acr-stat-value"><?php echo esc_html(number_format((int)$stats->total_abandoned)); ?></p>
                </div>

                <div class="acr-stat-card recovered">
                    <h3><?php echo esc_html('Recovered'); ?></h3>
                    <p class="acr-stat-value"><?php echo esc_html(number_format((int)$stats->recovered)); ?></p>
                </div>

                <div class="acr-stat-card emails-sent">
                    <h3><?php echo esc_html('Emails Sent'); ?></h3>
                    <p class="acr-stat-value"><?php echo esc_html(number_format((int)$stats->emails_sent)); ?></p>
                </div>

                <div class="acr-stat-card recovered-value">
                    <h3><?php echo esc_html('Recovered Value'); ?></h3>
                    <p class="acr-stat-value"><?php echo wp_kses_post(wc_price((float)$stats->recovered_value)); ?></p>
                </div>
            </div>

            <h2><?php echo esc_html('Recent Abandoned Carts'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html('ID'); ?></th>
                        <th><?php echo esc_html('Email'); ?></th>
                        <th><?php echo esc_html('Cart Total'); ?></th>
                        <th><?php echo esc_html('Status'); ?></th>
                        <th><?php echo esc_html('Created'); ?></th>
                        <th><?php echo esc_html('Updated'); ?></th>
                        <th><?php echo esc_html('Recovery Sent'); ?></th>
                        <th><?php echo esc_html('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_carts)) : ?>
                        <tr>
                            <td colspan="8" class="acr-empty-state">
                                <p><?php echo esc_html('No abandoned carts yet. Add some products to your cart to test!'); ?></p>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($recent_carts as $cart) : ?>
                            <tr>
                                <td><?php echo esc_html($cart->id); ?></td>
                                <td><?php echo $cart->email ? esc_html($cart->email) : wp_kses_post('<em>No email</em>'); ?></td>
                                <td><?php echo wp_kses_post(wc_price($cart->cart_total)); ?></td>
                                <td>
                                    <?php
                                    $status_colors = array(
                                        'active' => '#46b450',
                                        'recovered' => '#00a32a',
                                        'abandoned' => '#dc3232'
                                    );
                                    $color = $status_colors[$cart->status] ?? '#666';
                                    $status_class = 'acr-status-badge ' . sanitize_html_class($cart->status);
                                    ?>
                                    <span class="<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(strtoupper($cart->status)); ?>
                                    </span>
                                    <?php if ($cart->recovered) : ?>
                                        <span class="acr-recovered-indicator"><?php echo esc_html('✓ Recovered'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('M j, Y g:i A', strtotime($cart->created_at))); ?></td>
                                <td><?php echo esc_html(date('M j, Y g:i A', strtotime($cart->updated_at))); ?></td>
                                <td>
                                    <?php if ($cart->recovery_sent) : ?>
                                        <div class="acr-recovery-sent">
                                            <?php echo esc_html('✉️ Sent'); ?><br>
                                            <small><?php echo esc_html(date('M j, g:i A', strtotime($cart->recovery_sent_at))); ?></small>
                                        </div>
                                    <?php else : ?>
                                        <em><?php echo esc_html('Not sent'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cart->recovery_token) : ?>
                                        <a href="<?php echo esc_url(add_query_arg(array('acr_recover' => $cart->recovery_token, 'session' => $cart->session_id), wc_get_cart_url())); ?>" target="_blank" class="button button-small">
                                            <?php echo esc_html('View Recovery Link'); ?>
                                        </a>
                                        <form method="post" action="" style="display: inline;">
                                            <?php wp_nonce_field('acr_send_recovery_email_nonce'); ?>
                                            <input type="hidden" name="acr_send_recovery_email" value="1">
                                            <input type="hidden" name="cart_id" value="<?php echo esc_attr($cart->id); ?>">
                                            <button type="submit" class="button button-small" style="margin-left: 5px;">
                                                <?php echo esc_html('Send Email'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('acr_delete_cart_nonce'); ?>
                                        <input type="hidden" name="acr_delete_cart" value="1">
                                        <input type="hidden" name="cart_id" value="<?php echo esc_attr($cart->id); ?>">
                                        <button type="submit" class="button button-small button-link-delete" style="margin-left: 5px;" onclick="return confirm('Are you sure you want to delete this abandoned cart?');">
                                            <?php echo esc_html('Delete'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
