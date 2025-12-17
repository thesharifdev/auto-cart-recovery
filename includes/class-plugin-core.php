<?php

namespace Auto_Cart_Recovery;

use Auto_Cart_Recovery\Traits\Singleton;

defined('ABSPATH') || exit;

/**
 * Auto Cart Recovery primary operation.
 */
class Plugin_Core
{

    use Singleton;

    private $table_name;

    public function init()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'acr_abandoned_carts';

        // Admin menu
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

    public function admin_page()
    {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");

        if ($table_exists !== $this->table_name) {
?>
            <div class="wrap">
                <h1>🛒 Auto Cart Recovery Dashboard</h1>
                <div class="notice notice-error">
                    <p><strong>Database table missing!</strong></p>
                    <p>The plugin's database table was not created. Please try:</p>
                    <ol>
                        <li>Deactivate and reactivate the plugin</li>
                        <li>Or click the button below to create the table manually</li>
                    </ol>
                    <form method="post" action="">
                        <?php wp_nonce_field('acr_create_table'); ?>
                        <button type="submit" name="acr_create_table" class="button button-primary">
                            Create Database Table Now
                        </button>
                    </form>
                </div>
            </div>
        <?php

            // Handle manual table creation
            if (isset($_POST['acr_create_table']) && check_admin_referer('acr_create_table')) {
                $this->create_database_table();
                echo '<div class="notice notice-success"><p>Table created! Please refresh this page.</p></div>';
            }
            return;
        }

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_abandoned,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN recovered = 1 THEN 1 ELSE 0 END) as recovered,
                SUM(CASE WHEN recovery_sent = 1 THEN 1 ELSE 0 END) as emails_sent,
                SUM(CASE WHEN recovered = 1 THEN cart_total ELSE 0 END) as recovered_value
            FROM {$this->table_name}
        ");

        // Handle null values - ensure all properties exist with defaults
        if (!$stats) {
            $stats = (object) array(
                'total_abandoned' => 0,
                'active' => 0,
                'recovered' => 0,
                'emails_sent' => 0,
                'recovered_value' => 0
            );
        } else {
            // SUM() returns NULL when there are no rows, so convert NULL to 0
            $stats->total_abandoned = $stats->total_abandoned ?? 0;
            $stats->active = $stats->active ?? 0;
            $stats->recovered = $stats->recovered ?? 0;
            $stats->emails_sent = $stats->emails_sent ?? 0;
            $stats->recovered_value = $stats->recovered_value ?? 0;
        }

        $recent_carts = $wpdb->get_results("
            SELECT * FROM {$this->table_name}
            ORDER BY created_at DESC
            LIMIT 20
        ");

        if (!$recent_carts) {
            $recent_carts = array();
        }

        ?>
        <div class="wrap">
            <h1>🛒 Auto Cart Recovery Dashboard</h1>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">Total Abandoned</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo number_format((int)$stats->total_abandoned); ?></p>
                </div>

                <div style="background: #fff; padding: 20px; border-left: 4px solid #46b450; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">Active Carts</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold; color: #46b450;"><?php echo number_format((int)$stats->active); ?></p>
                </div>

                <div style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">Recovered</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold; color: #00a32a;"><?php echo number_format((int)$stats->recovered); ?></p>
                </div>

                <div style="background: #fff; padding: 20px; border-left: 4px solid #f0a736; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">Emails Sent</h3>
                    <p style="font-size: 32px; margin: 0; font-weight: bold; color: #f0a736;"><?php echo number_format((int)$stats->emails_sent); ?></p>
                </div>

                <div style="background: #fff; padding: 20px; border-left: 4px solid #9b51e0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666;">Recovered Value</h3>
                    <p style="font-size: 24px; margin: 0; font-weight: bold; color: #9b51e0;"><?php echo wc_price((float)$stats->recovered_value); ?></p>
                </div>
            </div>

            <div style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>✨ Plugin Features</h2>
                <ul style="line-height: 2;">
                    <li>✅ Automatic cart tracking for all visitors</li>
                    <li>✅ Email capture during checkout</li>
                    <li>✅ Automated recovery emails sent after 1 hour</li>
                    <li>✅ One-click cart restoration links</li>
                    <li>✅ Real-time statistics and monitoring</li>
                    <li>✅ Works with guest and registered users</li>
                </ul>
            </div>

            <h2>Recent Abandoned Carts</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Cart Total</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Recovery Sent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_carts)) : ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <p style="font-size: 16px; color: #666;">No abandoned carts yet. Add some products to your cart to test!</p>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($recent_carts as $cart) : ?>
                            <tr>
                                <td><?php echo $cart->id; ?></td>
                                <td><?php echo $cart->email ?: '<em>No email</em>'; ?></td>
                                <td><?php echo wc_price($cart->cart_total); ?></td>
                                <td>
                                    <?php
                                    $status_colors = array(
                                        'active' => '#46b450',
                                        'recovered' => '#00a32a',
                                        'abandoned' => '#dc3232'
                                    );
                                    $color = $status_colors[$cart->status] ?? '#666';
                                    ?>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                        <?php echo strtoupper($cart->status); ?>
                                    </span>
                                    <?php if ($cart->recovered) : ?>
                                        <span style="color: #00a32a;">✓ Recovered</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($cart->created_at)); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($cart->updated_at)); ?></td>
                                <td>
                                    <?php if ($cart->recovery_sent) : ?>
                                        ✉️ Sent<br>
                                        <small><?php echo date('M j, g:i A', strtotime($cart->recovery_sent_at)); ?></small>
                                    <?php else : ?>
                                        <em>Not sent</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cart->recovery_token) : ?>
                                        <a href="<?php echo add_query_arg(array('acr_recover' => $cart->recovery_token, 'session' => $cart->session_id), wc_get_cart_url()); ?>" target="_blank" class="button button-small">
                                            View Recovery Link
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                <h3>🧪 Testing Instructions</h3>
                <ol style="line-height: 2;">
                    <li>Add products to your cart on the frontend</li>
                    <li>Proceed to checkout and enter your email</li>
                    <li>Leave without completing the order</li>
                    <li>Wait 1 hour (or manually trigger: <code>do_action('acr_check_abandoned_carts')</code>)</li>
                    <li>Check your email for recovery notification</li>
                    <li>Click the recovery link to restore your cart</li>
                </ol>
                <p><strong>Note:</strong> Emails are sent via WordPress's wp_mail(). Make sure your server can send emails or use an SMTP plugin for testing.</p>
            </div>
        </div>
<?php
    }

    /**
     * Database table creation for Auto Cart Recovery plugin.
     */
    private function create_database_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            cart_data longtext NOT NULL,
            cart_total decimal(10,2) DEFAULT 0.00,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            status varchar(50) DEFAULT 'active',
            recovery_sent tinyint(1) DEFAULT 0,
            recovery_sent_at datetime DEFAULT NULL,
            recovered tinyint(1) DEFAULT 0,
            recovery_token varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY email (email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
