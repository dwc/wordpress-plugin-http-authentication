<?php
/*
Plugin Name: HTTP Authentication
Version: 3.0-dev
Plugin URI: http://dev.webadmin.ufl.edu/~dwc/2008/04/16/http-authentication-20/
Description: Authenticate users using basic HTTP authentication (<code>REMOTE_USER</code>). This plugin assumes users are externally authenticated, as with <a href="http://www.gatorlink.ufl.edu/">GatorLink</a>.
Author: Daniel Westermann-Clark
Author URI: http://dev.webadmin.ufl.edu/~dwc/
*/

if (! class_exists('HTTPAuthenticationPlugin')) {
	class HTTPAuthenticationPlugin {
		function HTTPAuthenticationPlugin() {
			register_activation_hook(__FILE__, array(&$this, 'initialize_options'));

			// Administration handlers
			add_action('admin_init', array(&$this, 'register_options'));
			add_action('admin_menu', array(&$this, 'add_options_page'));
			add_filter('show_password_fields', array(&$this, 'disable'));

			// Login handlers
			add_action('allow_password_reset', array(&$this, 'disable'));
			add_action('wp_logout', array(&$this, 'logout'));
		}


		/*************************************************************
		 * Plugin hooks
		 *************************************************************/

		function initialize_options() {
			$options = array(
				'logout_uri' => get_site_option('home'),
				'auto_create_user' => false,
				'auto_create_email_domain' => '',
			);

			update_site_option('http_authentication_options', $options);
		}

		function register_options() {
			register_setting('http_authentication_options', 'http_authentication_options');

			add_settings_section('http_authentication_main', 'Main Options', array(&$this, '_display_options_section'), __FILE__);
			add_settings_field('http_authentication_logout_uri', 'Logout URI', array(&$this, '_display_option_logout_uri'), __FILE__, 'http_authentication_main');
			add_settings_field('http_authentication_auto_create_user', 'Automatically create accounts?', array(&$this, '_display_option_auto_create_user'), __FILE__, 'http_authentication_main');
			add_settings_field('http_authentication_auto_create_email_domain', 'Email address domain', array(&$this, '_display_option_auto_create_email_domain'), __FILE__, 'http_authentication_main');
		}

		/*
		 * Add a options page for this plugin.
		 */
		function add_options_page() {
			if (function_exists('is_site_admin') && is_site_admin()) {
				add_submenu_page('wpmu-admin.php', 'HTTP Authentication', 'HTTP Authentication', 'manage_options', __FILE__, array(&$this, '_display_options_page'));
				add_options_page('HTTP Authentication', 'HTTP Authentication', 'manage_options', __FILE__, array(&$this, '_display_options_page'));
			}
			else {
				add_options_page('HTTP Authentication', 'HTTP Authentication', 'manage_options', __FILE__, array(&$this, '_display_options_page'));
			}
		}

		/*
		 * Logout the user by redirecting them to the logout URI.
		 */
		function logout() {
			header('Location: ' . $this->get_plugin_option('logout_uri'));
			exit();
		}

		/*
		 * Used to disable certain display elements, e.g. password
		 * fields on profile screen, or functions, e.g. password reset.
		 */
		function disable($flag) {
			return false;
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
		function check_user() {
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

		/*
		 * Display the options for this plugin.
		 */
		function _display_options_page() {
?>
<div class="wrap">
  <h2>HTTP Authentication Options</h2>
  <form action="options.php" method="post">
    <?php settings_fields('http_authentication_options'); ?>
    <?php do_settings_sections(__FILE__); ?>
    <p class="submit">
      <input type="submit" name="Submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button-primary" />
    </p>
  </form>
</div>
<?php
		}

		function _display_options_section() {
		}

		function _display_option_logout_uri() {
			$logout_uri = $this->get_plugin_option('logout_uri');
?>
<input type="text" name="http_authentication_options[logout_uri]" id="http_authentication_logout_uri" value="<?php echo htmlspecialchars($logout_uri) ?>" size="50" /><br />
Default is <code><?php echo htmlspecialchars(get_site_option('home')); ?></code>; override to e.g. remove a cookie.
<?php
		}

		function _display_option_auto_create_user() {
			$auto_create_user = $this->get_plugin_option('auto_create_user');
?>
<input type="checkbox" name="http_authentication_options[auto_create_user]" id="http_authentication_auto_create_user"<?php if ($auto_create_user) echo ' checked="checked"' ?> value="1" /><br />
Should a new user be created automatically if not already in the WordPress database?<br />
Created users will obtain the role defined under &quot;New User Default Role&quot; on the <a href="options-general.php">General Options</a> page.
<?php
		}

		function _display_option_auto_create_email_domain() {
			$auto_create_email_domain = $this->get_plugin_option('auto_create_email_domain');
?>
<input type="text" name="http_authentication_options[auto_create_email_domain]" id="http_authentication_auto_create_email_domain" value="<?php echo htmlspecialchars($auto_create_email_domain) ?>" size="50" /><br />
When a new user logs in, this domain is used for the initial email address on their account. The user can change his or her email address by editing their profile.
<?php
		}
	}
}

// Load the plugin hooks, etc.
$http_authentication_plugin = new HTTPAuthenticationPlugin();

// Override pluggable function to avoid ordering problem with 'authenticate' filter
if (! function_exists('wp_authenticate')) {
	function wp_authenticate($username, $password) {
		global $http_authentication_plugin;

		$user = $http_authentication_plugin->check_user();
		if (! is_wp_error($user)) {
			$user = new WP_User($user->ID);
		}

		return $user;
	}
}
?>
