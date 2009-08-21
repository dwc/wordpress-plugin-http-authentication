<?php
/*
Plugin Name: HTTP Authentication
Version: 2.2
Plugin URI: http://dev.webadmin.ufl.edu/~dwc/2008/04/16/http-authentication-20/
Description: Authenticate users using basic HTTP authentication (<code>REMOTE_USER</code>). This plugin assumes users are externally authenticated, as with <a href="http://www.gatorlink.ufl.edu/">GatorLink</a>.
Author: Daniel Westermann-Clark
Author URI: http://dev.webadmin.ufl.edu/~dwc/
*/

if (! class_exists('HTTPAuthenticationPlugin')) {
	$wpmu;
	$username;

	class HTTPAuthenticationPlugin {
		function HTTPAuthenticationPlugin() {
			register_activation_hook(__FILE__, array(&$this, 'initialize_options'));

			$this->username = '';
			foreach (array('REMOTE_USER', 'REDIRECT_REMOTE_USER') as $key) {
				if (isset($_SERVER[$key])) {
					$this->username = $_SERVER[$key];
				}
			}

			// Allow fallthrough for non-protected blogs
			if ($this->username !== '') {
				add_action('wp_authenticate', array(&$this, 'authenticate'), 10, 2);
				add_filter('check_password', array(&$this, 'skip_password_check'), 10, 4);
			}

			add_action('admin_menu', array(&$this, 'add_options_page'));
			add_action('wp_logout', array(&$this, 'logout'));
			add_action('lost_password', array(&$this, 'disable_function'));
			add_action('retrieve_password', array(&$this, 'disable_function'));
			add_action('password_reset', array(&$this, 'disable_function'));
			add_action('check_passwords', array(&$this, 'generate_password'), 10, 3);
			add_filter('show_password_fields', array(&$this, 'disable_password_fields'));

			// WORDPRESS MU DETECTION
			//    0 - Regular WordPress installation
			//    1 - WordPress MU forced activation
			//    2 - WordPress MU optional activation
			$this->wpmu = 0;
			if (basename(dirname(__FILE__)) == "mu-plugins") {
				// Forced activation
				$this->wpmu = 1;
			}
			else if (basename(dirname(__FILE__)) == "plugins" && function_exists('is_site_admin')) {
				// Optional activation
				$this->wpmu = 2;
			}
		}


		/*************************************************************
		 * Plugin hooks
		 *************************************************************/

		/*
		 * Add options for this plugin to the database.
		 */
		function initialize_options() {
			if (current_user_can('manage_options')) {
				add_option('http_authentication_logout_uri', $this->get_option('home'), 'The URI to which the user is redirected when she chooses "Logout".');
				add_option('http_authentication_auto_create_user', false, 'Should a new user be created automatically if not already in the WordPress database?');
				add_option('http_authentication_auto_create_email_domain', '', 'The domain to use for the email address of an automatically created user.');
			}
		}

		/*
		 * Add an options pane for this plugin.
		 */
		function add_options_page() {
			if (function_exists('add_options_page')) {
				if ($this->wpmu == 1 && function_exists('is_site_admin') && is_site_admin()) {
					add_submenu_page('wpmu-admin.php', 'HTTP Authentication', 'HTTP Authentication', 'manage_options', __FILE__, array(&$this, '_display_options_page'));
					add_options_page('HTTP Authentication', 'HTTP Authentication', 9, __FILE__, array(&$this, '_display_options_page'));
				}
				else if ($this->wpmu != 1) {
					add_options_page('HTTP Authentication', 'HTTP Authentication', 9, __FILE__, array(&$this, '_display_options_page'));
				}
			}
		}

		/*
		 * If the REMOTE_USER or REDIRECT_REMOTE_USER evironment
		 * variable is set, use it as the username. This assumes that
		 * you have externally authenticated the user.
		 */
		function authenticate($username, $password) {
			$username = $this->username;

			// Fake WordPress into authenticating by overriding the credentials
			$password = $this->_get_password();

			// Create new users automatically, if configured
			$user = get_userdatabylogin($username);
			if (! $user or $user->user_login != $username) {
				if ((bool) $this->get_option('http_authentication_auto_create_user')) {
					$this->_create_user($username);
				}
				else {
					// Bail out to avoid showing the login form
					die("User $username does not exist in the WordPress database");
				}
			}
		}

		/*
		 * Skip the password check, since we've externally authenticated.
		 */
		function skip_password_check($check, $password, $hash, $user_id) {
			return true;
		}

		/*
		 * Logout the user by redirecting them to the logout URI.
		 */
		function logout() {
			header('Location: ' . $this->get_option('http_authentication_logout_uri'));
			exit();
		}

		/*
		 * Generate a password for the user. This plugin does not
		 * require the user to enter this value, but we want to set it
		 * to something nonobvious.
		 */
		function generate_password($username, $password1, $password2) {
			$password1 = $password2 = $this->_get_password();
		}

		/*
		 * Used to disable certain display elements, e.g. password
		 * fields on profile screen.
		 */
		function disable_password_fields($show_password_fields) {
			return false;
		}

		/*
		 * Used to disable certain login functions, e.g. retrieving a
		 * user's password.
		 */
		function disable_function() {
			die('Disabled');
		}


		/*************************************************************
		 * Functions
		 *************************************************************/

		/*
		 * Use the appropriate get_option function for our MU config.
		 */
		function get_option($option) {
			if ($this->wpmu == 1) {
				return get_site_option($option);
			}
			else {
				return get_option($option);
			}
		}

		/*
		 * Use the appropriate update_option function for our MU config.
		 */
		function update_option($option, $value) {
			if ($this->wpmu == 1) {
				update_site_option($option, $value);
			}
			else {
				update_option($option, $value);
			}
		}

		/*
		 * Generate a random password.
		 */
		function _get_password($length = 10) {
			return substr(md5(uniqid(microtime())), 0, $length);
		}

		/*
		 * Create a new WordPress account for the specified username.
		 */
		function _create_user($username) {
			$password = $this->_get_password();
			$email_domain = $this->get_option('http_authentication_auto_create_email_domain');

			require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
			wp_create_user($username, $password, $username . ($email_domain ? '@' . $email_domain : ''));
		}

		/*
		 * Display the options for this plugin.
		 */
		function _display_options_page() {
			// Update any options that were set
			foreach (array('logout_uri', 'auto_create_email_domain') as $key) {
				$key = "http_authentication_$key";
				if (isset($_POST[$key])) {
					$this->update_option($key, trim($_POST[$key]));
				}
			}

			// Update boolean option (if unchecked, it doesn't get set)
			if ($_POST) {
				$key = 'http_authentication_auto_create_user';
				$this->update_option($key, (bool) $_POST[$key]);
			}

			$logout_uri = $this->get_option('http_authentication_logout_uri');
			$auto_create_user = (bool) $this->get_option('http_authentication_auto_create_user');
			$auto_create_email_domain = $this->get_option('http_authentication_auto_create_email_domain');
?>
<div class="wrap">
  <h2>HTTP Authentication Options</h2>
  <form method="post" action="<?php echo $_SERVER['REDIRECT_SCRIPT_URI'] . '?page=' . plugin_basename(__FILE__); ?>&updated=true">
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="http_authentication_logout_uri,http_authentication_auto_create_user,http_authentication_auto_create_email_domain" />
    <?php if (function_exists('wp_nonce_field')): wp_nonce_field('update-options'); endif; ?>

    <table class="form-table">
      <tr valign="top">
        <th scope="row"><label for="http_authentication_logout_uri">Logout URI</label></th>
        <td>
          <input type="text" name="http_authentication_logout_uri" id="http_authentication_logout_uri" value="<?php echo htmlspecialchars($logout_uri) ?>" size="50" /><br />
          Default is <code><?php echo htmlspecialchars($this->get_option('home')); ?></code>; override to e.g. remove a cookie.
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="http_authentication_auto_create_user">Automatically create accounts?</label></th>
        <td>
          <input type="checkbox" name="http_authentication_auto_create_user" id="http_authentication_auto_create_user"<?php if ($auto_create_user) echo ' checked="checked"' ?> value="1" /><br />
          Should a new user be created automatically if not already in the WordPress database?<br />
          Created users will obtain the role defined under &quot;New User Default Role&quot; on the <a href="options-general.php">General Options</a> page.
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="http_authentication_auto_create_email_domain">Email address domain</label></th>
        <td>
          <input type="text" name="http_authentication_auto_create_email_domain" id="http_authentication_auto_create_email_domain" value="<?php echo htmlspecialchars($auto_create_email_domain) ?>" size="50" /><br />
          When a new user logs in, this domain is used for the initial email address on their account. The user can change his or her email address by editing their profile.
        </td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="Submit" value="Save Changes" />
    </p>
  </form>
</div>
<?php
		}
	}
}

// Load the plugin hooks, etc.
$http_authentication_plugin = new HTTPAuthenticationPlugin();
?>
