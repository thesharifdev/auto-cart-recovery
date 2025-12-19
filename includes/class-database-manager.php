<?php

namespace Auto_Cart_Recovery;

defined('ABSPATH') || exit;

/**
 * Database Manager - Handles all database operations for abandoned carts.
 */
class Database_Manager
{
    private $table_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'acr_abandoned_carts';
    }

    /**
     * Create database table for abandoned carts.
     * 
     * @return void
     */
    public function create_table()
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

    /**
     * Get cart by session ID (any status).
     * Add this method to the Database_Manager class
     * 
     * @param string $session_id Session ID
     * @return object|null
     */
    public function get_cart_by_session_any_status($session_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s ORDER BY id DESC LIMIT 1",
            $session_id
        ));
    }

    /**
     * Check if table exists.
     * 
     * @return bool
     */
    public function table_exists()
    {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
    }

    /**
     * Get table name.
     * 
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Insert or update cart record.
     * 
     * @param array $data Cart data
     * @param array $where Where conditions
     * @param bool $is_update Whether this is an update or insert
     * @return int|false
     */
    public function save_cart($data, $where = null, $is_update = false)
    {
        global $wpdb;

        if ($is_update && $where) {
            // Build where formats to match each where value type
            $where_formats = array();
            foreach ($where as $w_val) {
                if (is_int($w_val) || ctype_digit((string) $w_val)) {
                    $where_formats[] = '%d';
                } else {
                    $where_formats[] = '%s';
                }
            }

            return $wpdb->update(
                $this->table_name,
                $data,
                $where,
                array_fill(0, count($data), '%s'),
                $where_formats
            );
        } else {
            return $wpdb->insert(
                $this->table_name,
                $data,
                array_fill(0, count($data), '%s')
            );
        }
    }

    /**
     * Get cart by session ID.
     * 
     * @param string $session_id Session ID
     * @return object|null
     */
    public function get_cart_by_session($session_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE session_id = %s AND status = 'active'",
            $session_id
        ));
    }

    /**
     * Get cart by recovery token.
     * 
     * @param string $token Recovery token
     * @param string $session Session ID
     * @return object|null
     */
    public function get_cart_by_token($token, $session)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE recovery_token = %s AND session_id = %s",
            $token,
            $session
        ));
    }

    /**
     * Get cart by ID.
     * 
     * @param int $id Cart ID
     * @return object|null
     */
    public function get_cart_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get abandoned carts statistics.
     * 
     * @param string $time_threshold Time threshold for abandoned carts
     * @return object
     */
    public function get_stats($time_threshold)
    {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN status = 'active' AND updated_at < %s THEN 1 ELSE 0 END) as total_abandoned,
                SUM(CASE WHEN status = 'active' AND updated_at >= %s THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN recovered = 1 THEN 1 ELSE 0 END) as recovered,
                SUM(CASE WHEN recovery_sent = 1 THEN 1 ELSE 0 END) as emails_sent,
                SUM(CASE WHEN recovered = 1 THEN cart_total ELSE 0 END) as recovered_value
            FROM {$this->table_name}
        ", $time_threshold, $time_threshold));

        // Handle null values
        if (!$stats) {
            $stats = (object) array(
                'total_abandoned' => 0,
                'active' => 0,
                'recovered' => 0,
                'emails_sent' => 0,
                'recovered_value' => 0
            );
        } else {
            $stats->total_abandoned = $stats->total_abandoned ?? 0;
            $stats->active = $stats->active ?? 0;
            $stats->recovered = $stats->recovered ?? 0;
            $stats->emails_sent = $stats->emails_sent ?? 0;
            $stats->recovered_value = $stats->recovered_value ?? 0;
        }

        return $stats;
    }

    /**
     * Get recent abandoned carts.
     * 
     * @param string $time_threshold Time threshold
     * @param int $limit Number of results
     * @return array
     */
    public function get_recent_carts($time_threshold, $limit = 20)
    {
        global $wpdb;

        $carts = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->table_name}
            WHERE status = 'active' AND updated_at < %s
            ORDER BY created_at DESC
            LIMIT %d
        ", $time_threshold, $limit));

        return $carts ?: array();
    }

    /**
     * Get carts for recovery email.
     * 
     * @param string $time_threshold Time threshold
     * @param int $limit Number of results
     * @return array
     */
    public function get_pending_recovery_carts($time_threshold, $limit = 10)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'active' 
            AND recovery_sent = 0 
            AND email IS NOT NULL 
            AND email != '' 
            AND updated_at < %s
            LIMIT %d",
            $time_threshold,
            $limit
        ));
    }

    /**
     * Update cart status.
     * 
     * @param int $cart_id Cart ID
     * @param string $status New status
     * @return int|false
     */
    public function update_status($cart_id, $status)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array(
                'status' => $status,
                'recovered' => 1,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $cart_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    }

    /**
     * Mark recovery email as sent.
     * 
     * @param int $cart_id Cart ID
     * @return int|false
     */
    public function mark_recovery_email_sent($cart_id)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array(
                'recovery_sent' => 1,
                'recovery_sent_at' => current_time('mysql')
            ),
            array('id' => $cart_id),
            array('%d', '%s'),
            array('%d')
        );
    }

    /**
     * Update cart recovery status by session.
     * 
     * @param string $session_id Session ID
     * @return int|false
     */
    public function mark_cart_recovered_by_session($session_id)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array(
                'status' => 'recovered',
                'recovered' => 1,
                'updated_at' => current_time('mysql')
            ),
            array('session_id' => $session_id),
            array('%s', '%d', '%s'),
            array('%s')
        );
    }

    /**
     * Delete cart record.
     * 
     * @param int $cart_id Cart ID
     * @return int|false
     */
    public function delete_cart($cart_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('id' => $cart_id),
            array('%d')
        );
    }

    /**
     * Truncate all carts.
     * 
     * @return false|int
     */
    public function truncate_all()
    {
        global $wpdb;

        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
}
