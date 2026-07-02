<?php

namespace OOPSPAM\RateLimiting;

class OOPSpam_RateLimiter {
    private $db_table = 'oopspam_rate_limits';
    private $config;
    private $cron_hook = 'oopspam_cleanup_ratelimit_entries_cron';
    
    public function __construct() {
        $rtOptions = get_option('oopspamantispam_ratelimit_settings', []);

        // Safely extract values with int casting; empty strings
        // and missing keys fall back to sensible defaults.
        $ipLimit = isset($rtOptions['oopspamantispam_ratelimit_ip_limit'])
            ? intval($rtOptions['oopspamantispam_ratelimit_ip_limit']) : 2;
        $emailLimit = isset($rtOptions['oopspamantispam_ratelimit_email_limit'])
            ? intval($rtOptions['oopspamantispam_ratelimit_email_limit']) : 2;
        $blockDuration = isset($rtOptions['oopspamantispam_ratelimit_block_duration'])
            ? intval($rtOptions['oopspamantispam_ratelimit_block_duration']) : 24;
        $cleanupDuration = isset($rtOptions['oopspamantispam_ratelimit_cleanup_duration'])
            ? intval($rtOptions['oopspamantispam_ratelimit_cleanup_duration']) : 48;

        // Use 0 as floor for block/cleanup so they are never negative.
        $this->config = [
            'ip_limit_per_hour'   => max(0, $ipLimit),
            'email_limit_per_hour' => max(0, $emailLimit),
            'block_duration'      => max(1, $blockDuration),
            'cleanup_older_than'  => max(1, $cleanupDuration),
        ];

        add_filter('cron_schedules', [$this, 'oopspam_register_cron_schedule']);
    }
    

    /**
     * Get current datetime in MySQL format
     */
    private function getCurrentDateTime() {
        return current_time('mysql');
    }

    /**
     * Get datetime with offset in MySQL format
     */
    private function getDateTimeWithOffset($hours) {
        return date('Y-m-d H:i:s', strtotime($this->getCurrentDateTime() . " {$hours} hours"));
    }  

    /**
     * Check and record a rate limit attempt atomically.
     *
     * @param string $identifier  IP address or email.
     * @param string $type        'ip' or 'email'.
     * @return bool               True if allowed, false if blocked.
     */
    public function checkLimit($identifier, $type = 'ip') {
        static $cron_ensured = false;

        // Schedule clean up if not set (only once per request)
        if (!$cron_ensured && !wp_next_scheduled($this->cron_hook)) {
            $cron_ensured = true;
            $cleanup_hours = isset($this->config['cleanup_older_than']) ? intval($this->config['cleanup_older_than']) : 48;
            $this->reschedule_cleanup(0, $cleanup_hours);
        }

        if ($this->isBlocked($identifier, $type)) {
            return false;
        }

        global $wpdb;
        $now = $this->getCurrentDateTime();
        $one_hour_ago = $this->getDateTimeWithOffset(-1);
        $limit = $type === 'ip' ? $this->config['ip_limit_per_hour'] : $this->config['email_limit_per_hour'];
        $table_name = $wpdb->prefix . $this->db_table;

        // A limit of 0 or less means this type of rate limiting is disabled.
        // Allow the request without recording any attempt.
        if ($limit <= 0) {
            return true;
        }

        // Atomic upsert: insert a new row or increment the counter.
        // The CASE resets attempts to 1 when the last_attempt is older than 1 hour.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$table_name}` (identifier, type, first_attempt, last_attempt, attempts)
            VALUES (%s, %s, %s, %s, 1)
            ON DUPLICATE KEY UPDATE
                attempts = CASE WHEN last_attempt > %s THEN attempts + 1 ELSE 1 END,
                last_attempt = CASE WHEN last_attempt > %s THEN %s ELSE last_attempt END,
                first_attempt = CASE WHEN last_attempt > %s THEN first_attempt ELSE %s END",
            $identifier,
            $type,
            $now,
            $now,
            $one_hour_ago,
            $one_hour_ago,
            $now,
            $one_hour_ago,
            $now
        ));

        // Read back the new attempt count from the now-committed row.
        $attempts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN last_attempt > %s THEN attempts
                    ELSE 0
                END as current_attempts
            FROM `{$table_name}`
            WHERE identifier = %s 
            AND type = %s",
            $one_hour_ago,
            $identifier,
            $type
        ));

        if ($attempts > $limit) {
            $this->blockIdentifier($identifier, $type);
            return false;
        }

        return true;
    }    

    private function blockIdentifier($identifier, $type) {
        global $wpdb;
        
        $blocked_until = $this->getDateTimeWithOffset($this->config['block_duration']);
        
        $wpdb->update(
            $wpdb->prefix . $this->db_table,
            [
                'is_blocked' => 1,
                'blocked_until' => $blocked_until
            ],
            [
                'identifier' => $identifier,
                'type' => $type
            ],
            ['%d', '%s'],
            ['%s', '%s']
        );
    }

    private function isBlocked($identifier, $type) {
        
        global $wpdb;
        
        $now = $this->getCurrentDateTime();
        
        $table_name = esc_sql($wpdb->prefix . $this->db_table);
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table_name}
            WHERE identifier = %s 
            AND type = %s 
            AND is_blocked = 1 
            AND blocked_until > %s 
            LIMIT 1",
            $identifier,
            $type,
            $now
        ));
        
        return (bool)$is_blocked;
    }

    public function oopspam_ratelimit_cleanup() {
        try {
            global $wpdb;
        

            $cleanup_hours = isset($this->config['cleanup_older_than']) ? intval($this->config['cleanup_older_than']) : 48; // 48 as default
            $cleanup_date = $this->getDateTimeWithOffset(-$cleanup_hours);
            
            $table_name = esc_sql($wpdb->prefix . $this->db_table);
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name}
                WHERE last_attempt < %s",
                $cleanup_date
            ));
                        
            // Schedule next run if needed
            $next_run = wp_next_scheduled($this->cron_hook);
            if (!$next_run) {
                $this->schedule_cleanup(null);
            }
        } catch (\Exception $e) {
            error_log('OOPSpam_RateLimiter cleanup error: ' . $e->getMessage());
        }
    }

    public function oopspam_truncate_ratelimit() {
       
        try {
            global $wpdb;
                        
            $table_name = $wpdb->prefix . $this->db_table;
            $deleted = $wpdb->query(
                "TRUNCATE TABLE " . esc_sql($table_name)
            );
            
        } catch (\Exception $e) {
            error_log('OOPSpam_RateLimiter cleanup error: ' . $e->getMessage());
        }
    }

    public function reschedule_cleanup($old_value, $new_value) {
        try {
            // Update config with new duration
            $this->config['cleanup_older_than'] = intval($new_value);

            $next_run = wp_next_scheduled($this->cron_hook);
            $desired_timestamp = time() + $this->config['cleanup_older_than'] * HOUR_IN_SECONDS;

            // If a schedule already exists and the duration hasn't changed
            // meaningfully (within 1 hour), leave it alone to avoid
            // unnecessary clear+reschedule races under concurrent requests.
            if ($next_run) {
                $existing_interval = abs($next_run - $desired_timestamp);
                if ($existing_interval < HOUR_IN_SECONDS) {
                    return;
                }
                wp_clear_scheduled_hook($this->cron_hook);
            }

            $scheduled = wp_schedule_event($desired_timestamp, 'oopspam_ratelimit_cleanup', $this->cron_hook);

            if ($scheduled === false) {
                error_log("Failed to schedule new cleanup event");
            }

        } catch (\Exception $e) {
            error_log('OOPSpam_RateLimiter reschedule_cleanup error: ' . $e->getMessage());
        }
    }
    

    public function schedule_cleanup($duration) {
        try {
            $next_run = wp_next_scheduled($this->cron_hook);
            
            if (!$next_run) {
                
                if ($duration) {
                    $this->config['cleanup_older_than'] = intval($duration);
                }
                // Schedule the new event
                $cleanup_hours = isset($this->config['cleanup_older_than']) ? intval($this->config['cleanup_older_than']) : 48; // 48 as default
                $timestamp = time() + $cleanup_hours * HOUR_IN_SECONDS;
                $scheduled = wp_schedule_event($timestamp, 'oopspam_ratelimit_cleanup', $this->cron_hook);

                if ($scheduled === false) {
                    error_log('Failed to schedule initial rate limiter cron event');
                }
            }
        } catch (\Exception $e) {
            error_log('OOPSpam_RateLimiter schedule_cleanup error: ' . $e->getMessage());
        }
    }    
    
    public function oopspam_register_cron_schedule($schedules) {
        $cleanup_hours = $this->config['cleanup_older_than'];
        
        if (!isset($schedules['oopspam_ratelimit_cleanup'])) {
                // Calculate interval in seconds, minimum 1 hour
            $interval = max(HOUR_IN_SECONDS, intval($cleanup_hours) * 1800);
            
            $schedules['oopspam_ratelimit_cleanup'] = [
                'interval' => $interval,
                'display' => sprintf(__('Every %d hours'), ceil($interval / HOUR_IN_SECONDS))
            ];
            
        }
       
        return $schedules;
    }
}