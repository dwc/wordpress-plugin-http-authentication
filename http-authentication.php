<?php
/*
Plugin Name: HTTP Authentication
Version: 3.1
Plugin URI: https://dev.webadmin.ufl.edu/~dwc/2010/07/13/http-authentication-3-0/
Description: Authenticate users using basic HTTP authentication (<code>REMOTE_USER</code>). This plugin assumes users are externally authenticated, as with <a href="http://www.gatorlink.ufl.edu/">GatorLink</a>.
Author: Daniel Westermann-Clark
Author URI: https://dev.webadmin.ufl.edu/~dwc/
*/

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'options-page.php');

class HTTPAuthenticationPlugin {
	function HTTPAuthenticationPlugin() {
		register_activation_hook(__FILE__, array(&$this, 'initialize_options'));
		add_action('init', array(&$this, 'migrate_options'));

		$options_page = new HTTPAuthenticationOptionsPage(&$this, 'http_authentication_options', __FILE__);

		add_filter('login_url', array(&$this, 'bypass_reauth'));
		add_filter('show_password_fields', array(&$this, 'disable'));
		add_filter('allow_password_reset', array(&$this, 'disable'));
		add_action('wp_logout', array(&$this, 'logout'));
	}


	/*************************************************************
	 * Plugin hooks
	 *************************************************************/

	/*
	 * Add the default options to the database.
	 */
	function initialize_options() {
		$options = array(
			'logout_uri' => get_site_option('home'),
			'auto_create_user' => false,
			'auto_create_email_domain' => '',
		);

		// Copy old options
		foreach (array_keys($options) as $key) {
			$old_value = get_site_option("http_authentication_$key");
			if ($old_value !== false) {
				$options[$key] = $old_value;
			}

			delete_site_option("http_authentication_$key");
		}

		update_site_option('http_authentication_options', $options);
	}

	/*
	 * Migrate options for in-place upgrades of the plugin.
	 */
	function migrate_options() {
		// Check if we've migrated options already
		$options = get_site_option('http_authentication_options');
		if ($options !== false) return;

		$this->initialize_options();
	}

	/*
	 * Remove the reauth=1 parameter from the login URL, if applicable. This allows
	 * us to transparently bypass the mucking about with cookies that happens in
	 * wp-login.php immediately after wp_signon when a user e.g. navigates directly
	 * to wp-admin.
	 */
	function bypass_reauth($login_url) {
		$login_url = remove_query_arg('reauth', $login_url);

		return $login_url;
	}

	/*
	 * Used to disable certain display elements, e.g. password
	 * fields on profile screen, or functions, e.g. password reset.
	 */
	function disable($flag) {
		return false;
	}

	/*
	 * Logout the user by redirecting them to the logout URI.
	 */
	function logout() {
		header('Location: ' . $this->get_plugin_option('logout_uri'));
		exit();
	}


	/*************************************************************
	 * Functions
	 *************************************************************/

	/*
	 * Get the value of the specified plugin-specific option.
	 */
	function get_plugin_option($option) {
		$options = get_site_option('http_authentication_options');

		return $options[$option];
	}

	/*
	 * If the REMOTE_USER or REDIRECT_REMOTE_USER evironment
	 * variable is set, use it as the username. This assumes that
	 * you have externally authenticated the user.
	 */
	function check_remote_user() {
		$username = '';

		foreach (array('REMOTE_USER', 'REDIRECT_REMOTE_USER') as $key) {
			if (isset($_SERVER[$key])) {
				$username = $_SERVER[$key];
			}
		}

		if (! $username) {
			return new WP_Error('empty_username', 'No REMOTE_USER or REDIRECT_REMOTE_USER found.');
		}

		// Create new users automatically, if configured
		$user = get_userdatabylogin($username);
		if (! $user) {
			if ((bool) $this->get_plugin_option('auto_create_user')) {
				$user = $this->_create_user($username);
			}
			else {
				// Bail out to avoid showing the login form
				die("User $username does not exist in the WordPress database");
			}
		}

		return $user;
	}

	/*
	 * Create a new WordPress account for the specified username.
	 */
	function _create_user($username) {
		$password = wp_generate_password();
		$email_domain = $this->get_plugin_option('auto_create_email_domain');

		require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
		$user_id = wp_create_user($username, $password, $username . ($email_domain ? '@' . $email_domain : ''));
		$user = get_user_by('id', $user_id);

		return $user;
	}
}

// Load the plugin hooks, etc.
$http_authentication_plugin = new HTTPAuthenticationPlugin();

// Override pluggable function to avoid ordering problem with 'authenticate' filter
if (! function_exists('wp_authenticate')) {
	function wp_authenticate($username, $password) {
		global $http_authentication_plugin;

		$user = $http_authentication_plugin->check_remote_user();
		if (! is_wp_error($user)) {
			$user = new WP_User($user->ID);
		}

		return $user;
	}
}
?>
