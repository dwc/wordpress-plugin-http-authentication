<?php
/*
Plugin Name: HTTP Authentication
Version: 4.0
Plugin URI: https://dev.webadmin.ufl.edu/~dwc/2010/07/13/http-authentication-3-0/
Description: Authenticate users using basic HTTP authentication (<code>REMOTE_USER</code>). This plugin assumes users are externally authenticated, as with <a href="http://www.gatorlink.ufl.edu/">GatorLink</a>.
Author: Daniel Westermann-Clark
Author URI: https://dev.webadmin.ufl.edu/~dwc/
*/

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'options-page.php');

class HTTPAuthenticationPlugin {
	function HTTPAuthenticationPlugin() {
		register_activation_hook(__FILE__, array(&$this, 'initialize_options'));

		$options_page = new HTTPAuthenticationOptionsPage(&$this, 'http_authentication_options', __FILE__);

		add_filter('login_url', array(&$this, 'bypass_reauth'));
		add_filter('show_password_fields', array(&$this, 'allow_wp_auth'));
		add_filter('allow_password_reset', array(&$this, 'allow_wp_auth'));
		add_filter('authenticate', array(&$this, 'authenticate'), 10, 3);
		add_action('login_form', array(&$this, 'add_login_link'));
		add_action('check_passwords', array(&$this, 'generate_password'), 10, 3);
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
			'allow_wp_auth' => false,
			'auth_label' => 'HTTP authentication',
			'login_uri' => wp_login_url(),
			'logout_uri' => wp_logout_url(),
			'auto_create_user' => false,
			'auto_create_email_domain' => '',
		);

		update_option('http_authentication_options', $options);
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
	 * Authenticate the user, first using the external authentication source.
	 * If allowed, fall back to WordPress password authentication.
	 */
	function authenticate($user, $username, $password) {
		$user = $this->check_remote_user();

		if (! is_wp_error($user)) {
			// User was authenticated via REMOTE_USER
			$user = new WP_User($user->ID);
		}
		else {
			// REMOTE_USER is invalid; now what?

			if (! $this->allow_wp_auth()) {
				// Bail with the WP_Error when not falling back to WordPress authentication
				wp_die($user);
			}

			// Fallback to built-in hooks (see wp-includes/user.php)
		}

		return $user;
	}

	/*
	 * Add a link to the login form to initiate external authentication.
	 */
	function add_login_link() {
		global $redirect_to;

		$login_uri = sprintf($this->get_plugin_option('login_uri'), urlencode($redirect_to));
		$auth_label = $this->get_plugin_option('auth_label');

		echo "\t" . '<p><a href="' . htmlspecialchars($login_uri) . '">Login with ' . htmlspecialchars($auth_label) . '</a></p>' . "\n";
	}

	/*
	 * Generate a password for the user. This plugin does not require the
	 * administrator to enter this value, but we need to set it so that user
	 * creation and editing works.
	 */
	function generate_password($username, $password1, $password2) {
		if (! $this->allow_wp_auth()) {
			$password1 = $password2 = wp_generate_password();
		}
	}

	/*
	 * Logout the user by redirecting them to the logout URI.
	 */
	function logout() {
		$logout_uri = sprintf($this->get_plugin_option('logout_uri'), urlencode(home_url()));

		wp_redirect($logout_uri);
		exit();
	}


	/*************************************************************
	 * Functions
	 *************************************************************/

	/*
	 * Can we fallback to built-in WordPress authentication?
	 */
	function allow_wp_auth() {
		return (bool) $this->get_plugin_option('allow_wp_auth');
	}

	/*
	 * Get the value of the specified plugin-specific option.
	 */
	function get_plugin_option($option) {
		$options = get_option('http_authentication_options');

		return $options[$option];
	}

	/*
	 * If the REMOTE_USER or REDIRECT_REMOTE_USER evironment variable is set, use it
	 * as the username. This assumes that you have externally authenticated the user.
	 */
	function check_remote_user() {
		$username = '';

		foreach (array('REMOTE_USER', 'REDIRECT_REMOTE_USER') as $key) {
			if (isset($_SERVER[$key])) {
				$username = $_SERVER[$key];
			}
		}

		if (! $username) {
			return new WP_Error('empty_username', '<strong>ERROR</strong>: No REMOTE_USER or REDIRECT_REMOTE_USER found.');
		}

		// Create new users automatically, if configured
		$user = get_userdatabylogin($username);
		if (! $user)  {
			if ((bool) $this->get_plugin_option('auto_create_user')) {
				$user = $this->_create_user($username);
			}
			else {
				// Bail out to avoid showing the login form
				$user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.'));
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
?>
