<?php

class RateLimiter {
    private $db_table = 'oopspam_rate_limits';
    private $config;
    private $cron_hook = 'oopspam_cleanup_ratelimit_entries_cron';
    
    public function __construct() {
        $rtOptions = get_option('oopspamantispam_ratelimit_settings', []);
        
        // Check if $rtOptions is an array and has the necessary keys
        if (!is_array($rtOptions) || 
        !isset($rtOptions['oopspamantispam_ratelimit_ip_limit']) || 
        !isset($rtOptions['oopspamantispam_ratelimit_email_limit']) ||
        !isset($rtOptions['oopspamantispam_ratelimit_block_duration']) ||
        !isset($rtOptions['oopspamantispam_ratelimit_cleanup_duration'])) {
        // Handle the case where rtOptions is not an array or missing keys
        $this->config = [
            'ip_limit_per_hour' => 2,
            'email_limit_per_hour' => 2,
            'block_duration' => 24,
            'cleanup_older_than' => 48
        ];
    } else {
        // Load configuration
        $this->config = [
            'ip_limit_per_hour' => $rtOptions['oopspamantispam_ratelimit_ip_limit'],
            'email_limit_per_hour' => $rtOptions['oopspamantispam_ratelimit_email_limit'],
            'block_duration' => $rtOptions['oopspamantispam_ratelimit_block_duration'],
            'cleanup_older_than' => $rtOptions['oopspamantispam_ratelimit_cleanup_duration']
        ];
    }
    
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

    public function checkLimit($identifier, $type = 'ip') {
        // Schedule clean up if not set
        if (!wp_next_scheduled($this->cron_hook)) {
            $cleanup_hours = isset($this->config['cleanup_older_than']) ? intval($this->config['cleanup_older_than']) : 48; // 48 as default
            $this->reschedule_cleanup(0, $cleanup_hours);
         }
       
        if ($this->isBlocked($identifier, $type)) {
            return false;
        }

        $attempts = $this->getAttempts($identifier, $type);
        $limit = $type === 'ip' ? $this->config['ip_limit_per_hour'] : $this->config['email_limit_per_hour'];
        
        if ($attempts >= $limit) {
            $this->blockIdentifier($identifier, $type);
            return false;
        }

        $this->recordAttempt($identifier, $type);
        return true;
    }

    private function getAttempts($identifier, $type) {
        global $wpdb;
        
        $one_hour_ago = $this->getDateTimeWithOffset(-1);
    
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN last_attempt > %s THEN attempts
                    ELSE 0
                END as current_attempts
            FROM {$wpdb->prefix}{$this->db_table} 
            WHERE identifier = %s 
            AND type = %s",
            $one_hour_ago,
            $identifier,
            $type
        ));
        
        return (int)$result;
    }

    private function recordAttempt($identifier, $type) {
        global $wpdb;
        
        $now = $this->getCurrentDateTime();
        $one_hour_ago = $this->getDateTimeWithOffset(-1);
    
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, last_attempt, 
            CASE 
                WHEN last_attempt > %s THEN attempts
                ELSE 0
            END as current_attempts
            FROM {$wpdb->prefix}{$this->db_table} 
            WHERE identifier = %s AND type = %s",
            $one_hour_ago,
            $identifier,
            $type
        ));
        
        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}{$this->db_table} 
                SET 
                    attempts = CASE 
                        WHEN last_attempt > %s THEN attempts + 1
                        ELSE 1
                    END,
                    last_attempt = %s 
                WHERE id = %d",
                $one_hour_ago,
                $now,
                $existing->id
            ));
        } else {
            $wpdb->insert(
                $wpdb->prefix . $this->db_table,
                [
                    'identifier' => $identifier,
                    'type' => $type,
                    'first_attempt' => $now,
                    'last_attempt' => $now,
                    'attempts' => 1
                ],
                ['%s', '%s', '%s', '%s', '%d']
            );
        }
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
        
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}{$this->db_table} 
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
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}{$this->db_table} 
                WHERE last_attempt < %s",
                $cleanup_date
            ));
                        
            // Schedule next run if needed
            $next_run = wp_next_scheduled($this->cron_hook);
            if (!$next_run) {
                $this->schedule_cleanup(null);
            }
        } catch (Exception $e) {
            error_log('RateLimiter cleanup error: ' . $e->getMessage());
        }
    }

    public function oopspam_truncate_ratelimit() {
       
        try {
            global $wpdb;
                        
            $deleted = $wpdb->query(
                "TRUNCATE {$wpdb->prefix}{$this->db_table}"
            );
            
        } catch (Exception $e) {
            error_log('RateLimiter cleanup error: ' . $e->getMessage());
        }
    }

    public function reschedule_cleanup($old_value, $new_value) {
        try {            
            // Clear existing schedules to prevent duplicates
            wp_clear_scheduled_hook($this->cron_hook);
            
            // Update config with new duration
            $this->config['cleanup_older_than'] = intval($new_value);
            
            // Schedule the new cleanup event
            $timestamp = time() + $this->config['cleanup_older_than'] * HOUR_IN_SECONDS;
            $scheduled = wp_schedule_event($timestamp, 'oopspam_ratelimit_cleanup', $this->cron_hook);

            if ($scheduled === false) {
                error_log("Failed to schedule new cleanup event");
            }
            
        } catch (Exception $e) {
            error_log('RateLimiter reschedule_cleanup error: ' . $e->getMessage());
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
        } catch (Exception $e) {
            error_log('RateLimiter schedule_cleanup error: ' . $e->getMessage());
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