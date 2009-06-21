<?php
if (!class_exists('InvalidateLoggedOutCookies')) {
	class InvalidateLoggedOutCookies {
		// class constants
		var $db_ver = '1.0';
		var $db_ver_option = 'invalidated_cookies_db_ver';
		var $db_table = 'invalidated_cookies';
		var $cron_key = 'invalidated_cookies_cleanup';
		var $text_domain = 'invalidate-logged-out-cookies';

		var $logged_in_array = false;
		var $auth_array = false;
		var $text_domain_loaded = false;

		/**
		 * Constructor
		 */
		function InvalidateLoggedOutCookies() {
			$plugin_file = dirname(__FILE__).'/plugin.php';

			if ( function_exists('register_activation_hook') )
				register_activation_hook($plugin_file, array(&$this, 'register_activation_hook'));
			if ( function_exists('register_deactivation_hook') )
				register_deactivation_hook($plugin_file, array(&$this, 'register_deactivation_hook'));
			if ( function_exists('add_action') ) {
				add_action('clear_auth_cookie', array(&$this, 'clear_auth_cookie'));
				add_action($this->cron_key, array(&$this, 'do_cron'));
				add_action('set_auth_cookie', array(&$this, 'set_cookie'), 10, 5);
				add_action('set_logged_in_cookie', array(&$this, 'set_cookie'), 10, 5);

				$pbn = plugin_basename($plugin_file);
				add_action("after_plugin_row_$pbn", array(&$this, 'after_plugin_row'), 10, 3);
			}
		}

		/**
		 * Load the WP textdomain for this plugin (for translations)
		 */
		function load_textdomain() {
			if ($this->text_domain_loaded) return;

			load_plugin_textdomain($this->text_domain, PLUGINDIR.'/'.plugin_basename(dirname(__FILE__)).'/languages', plugin_basename(dirname(__FILE__)).'/languages');
			$this->text_domain_loaded = true;
		}

		/**
		 * Plugin hook (after_plugin_row for this plugin specifically)
		 * Print a message indicating if the 'wp_validate_auth_cookie' function is being overridden by this plugin
 		 *
		 * @param string $plugin_file
		 * @param array $plugin_data
		 * @param string $context
		 */
		function after_plugin_row($plugin_file, $plugin_data, $context) {
			$this->load_textdomain();

			echo '<tr><td colspan="3">';
			if ( method_exists('ReflectionFunction', 'getFileName') ) { // if >= PHP 5
				$func = new ReflectionFunction('wp_validate_auth_cookie');
				$filename = $func->getFileName();
				if ($filename == dirname(__FILE__).'/pluggable_overrides.php')
					_e('<strong>Success:</strong> This plugin is properly overriding the <code>wp_validate_auth_cookie</code> function.', $this->text_domain);
				else
					printf(__('<strong>Error:</strong> The following requirement is not being met! This plugin is NOT overriding the <code>wp_validate_auth_cookie</code> function. This function can only be overridden by one plugin at a time. Currently, this function is being overridden in the following file <code>%s</code>. Please disable this plugin or the other one that is causing the conflict.', $this->text_domain), htmlspecialchars($filename));
			}
			else {
				printf(__('<strong>Warning:</strong> It is currently unknown if this plugin is overriding the <code>wp_validate_auth_cookie</code> function. This can occur if you haven\'t upgraded to PHP 5. You appear to be using PHP %s. Overriding this function is a requirement of this plugin.', $this->text_domain), PHP_VERSION);
			}
			echo '</td></tr>';
		}

		/**
		 * Plugin hook (set_auth_cookie and set_logged_in_cookie)
		 * 'set_auth_cookie' happens before 'set_logged_in_cookie'
		 *
		 * @param string $cookie String stored in the cookie (username|expiration|hmac)
		 * @param int $expire When the cookie expires
		 * @param int $expiration When the data within the cookie expires
		 * @param int $user_id 
		 * @param string $scheme Which type of cookie is it?
		 */
		function set_cookie($cookie, $expire, $expiration, $user_id, $scheme) {
			if ($scheme == 'auth' OR $scheme == 'secure_auth') {
				$this->auth_array = $this->parse_cookie($cookie, $scheme);
			}
			else if ($scheme == 'logged_in') {
				$this->logged_in_array = $this->parse_cookie($cookie, $scheme);

				$logged_in_id = null;
				if ($this->logged_in_array !== FALSE)
					$logged_in_id = $this->insert_into_db($this->logged_in_array, $logged_in_id);
				if ($this->auth_array !== FALSE)
					$this->insert_into_db($this->auth_array, $logged_in_id);
			}
		}

		/**
		 * Plugin hook (register_activation_hook)
		 */
		function register_activation_hook() {
			global $wpdb;
			$table_name = $wpdb->prefix.$this->db_table;

			$installed_db_ver = get_option( $this->db_ver_option );

			// If the table doesn't already exist... create it!
			// If the table schema is out of date... update it!
			if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name OR $installed_db_ver != $this->db_ver) {
	
				// copied from WP2.8 schema.php file
				if ( $wpdb->has_cap( 'collation' ) ) {
					if ( ! empty($wpdb->charset) )
						$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
					if ( ! empty($wpdb->collate) )
						$charset_collate .= " COLLATE $wpdb->collate";
				}
	
				// DB schema v1.0
				// A note on varchar lengths...
				// username: stored as VARCHAR(60) in the DB
				// hash: the default hash is a 32 char MD5(HMAC)
				//       technically, WP also supports 40 char SHA1(HMAC)
				// scheme: currently, this is one of the following: 'logged_in', 'auth', or 'secure_auth'
				//         hopefully future-proofing the length with 30
				$sql = $wpdb->prepare( "
					CREATE TABLE $table_name (
					ID BIGINT(20) UNSIGNED NOT NULL auto_increment,
					reference_id BIGINT(20) UNSIGNED DEFAULT NULL,
					username VARCHAR(60) NOT NULL DEFAULT '',
					expiration INT(11) NOT NULL DEFAULT 0,
					hash VARCHAR(40) NOT NULL DEFAULT '',
					scheme VARCHAR(30) NOT NULL DEFAULT '',
					logged_out tinyint(1) NOT NULL DEFAULT '0',
					PRIMARY KEY  (ID)
					) $charset_collate;"
					);
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta( $sql );
	
				update_option($this->db_ver_option, $this->db_ver);
			}
	
			// just to be safe, clear this if it's already running first
			wp_clear_scheduled_hook($this->cron_key);
			wp_schedule_event(time()+86400, 'daily', $this->cron_key);
		}

		/**
		 * Plugin hook (register_deactivation_hook)
		 */
		function register_deactivation_hook() {
			wp_clear_scheduled_hook($this->cron_key);
		}

		/**
		 * WP-Cron hook (invalidated_cookies_cleanup)
		 */
		function do_cron() {
			global $wpdb;
			$table_name = $wpdb->prefix.$this->db_table;
	
			// WP adds 3600 (grace period) for POST or AJAX requests, we'll do that here as well
			$sql = $wpdb->prepare( "
				DELETE FROM $table_name
				WHERE expiration < %d",
				time()+3600);
			$wpdb->query( $sql );
		}

		/**
		 * Plugin hook (clear_auth_cookie)
		 *
		 * This action happens on the / path
		 * The logged_in cookie is always available here so we'll key off of that
		 * The auth and secure_auth cookies are only available on the /wp-admin path
		 */
		function clear_auth_cookie() {
			if ( defined('LOGGED_IN_COOKIE') && !empty($_COOKIE[LOGGED_IN_COOKIE]) ) {
				if ( ($a = $this->parse_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in')) !== FALSE ) {
					$this->update_logged_out_db_value($a);
				}
			}
		}

		/**
		 * Parses out a WP cookie
		 *
		 * @param string $cookie WP cookie value
		 * @param string $scheme Which cookie type is it
		 * @return bool|array FALSE if failure, array with cookie bits if successful
		 */
		function parse_cookie($cookie, $scheme) {
			$cookie_elements = explode('|', $cookie);
			if ( count($cookie_elements) != 3 OR !preg_match('/^[0-9]+$/', $cookie_elements[1]) )
				return FALSE;

			list($username, $expiration, $hmac) = $cookie_elements;
			return compact('username', 'expiration', 'hmac', 'scheme');
		}

		/**
		 * @param array $a Array with the cookie components
		 * @param int|null $logged_in_id ID of the logged_in cookie that's already been inserted in the DB
		 * @return int|null Returns the logged_in cookie ID or NULL
		 */
		function insert_into_db($a, $logged_in_id) {
			global $wpdb;
			$table_name = $wpdb->prefix.$this->db_table;

			$sql = $wpdb->prepare( "
				SELECT ID
				FROM $table_name
				WHERE username = %s
				AND expiration = %d
				AND hash = %s
				AND scheme = %s
				AND logged_out = 0
				LIMIT 1",
				$a['username'], $a['expiration'], $a['hmac'], $a['scheme']);
			$row = $wpdb->get_row( $sql );
			if ( is_object($row) ) {
				if ($a['scheme'] == 'logged_in')
					return $row->ID;
			}
			else {
				if ( is_null($logged_in_id) ) {
					$sql = $wpdb->prepare( "
						INSERT INTO $table_name
						( username, expiration, hash, scheme )
						VALUES
						( %s, %d, %s, %s )",
						$a['username'], $a['expiration'], $a['hmac'], $a['scheme']);
				}
				else {
					$sql = $wpdb->prepare( "
						INSERT INTO $table_name
						( reference_id, username, expiration, hash, scheme )
						VALUES
						( %d, %s, %d, %s, %s )",
						$logged_in_id, $a['username'], $a['expiration'], $a['hmac'], $a['scheme']);
				}
				$wpdb->query( $sql );
				if ($a['scheme'] == 'logged_in') {
					$row = $wpdb->get_row( 'SELECT LAST_INSERT_ID() AS ID' );
					if ( is_object($row) )
						return $row->ID;
				}
			}
	
			return NULL;
		}

		/**
		 * @param array $a Array with the cookie components
		 */
		function update_logged_out_db_value($a) {
			global $wpdb;
			$table_name = $wpdb->prefix.$this->db_table;

			$sql = $wpdb->prepare( "
				SELECT ID
				FROM $table_name
				WHERE username = %s
				AND expiration = %d
				AND hash = %s
				AND scheme = %s
				AND logged_out = 0
				LIMIT 1",
				$a['username'], $a['expiration'], $a['hmac'], $a['scheme']);
			$row = $wpdb->get_row( $sql );
			if ( is_object($row) ) {
				$sql = $wpdb->prepare( "
					UPDATE $table_name
					SET logged_out = 1
					WHERE ID = %d
					OR reference_id = %d",
					$row->ID, $row->ID);
				$wpdb->query( $sql );
			}
		}

		/**
		 * Static function to be included in 'wp_validate_auth_cookie'
		 *
		 * @param string $username
		 * @param int $expiration
		 * @param string $hmac
		 * @param string $scheme
		 * @return bool
		 */
		function is_cookie_invalid($username, $expiration, $hmac, $scheme) {
			global $wpdb;
			$table_name = $wpdb->prefix.InvalidateLoggedOutCookies::db_table();

			$sql = $wpdb->prepare( "
				SELECT ID
				FROM $table_name
				WHERE username = %s
				AND expiration = %d
				AND hash = %s
				AND scheme = %s
				AND logged_out = 1
				LIMIT 1",
				$username, $expiration, $hmac, $scheme);
			$row = $wpdb->get_row( $sql );
			if ( is_object($row) )
				return true;
			return false;
		}

		/**
		 * This lets us access $this->db_table statically in PHP4+5
		 */
		function db_table() {
			$vars = get_class_vars(__CLASS__);
			return $vars[__FUNCTION__];
		}
	}
}