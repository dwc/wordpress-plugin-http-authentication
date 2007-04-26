<?php
/*
Plugin Name: HTTP Authentication
Version: 1.8
Plugin URI: http://dev.webadmin.ufl.edu/~dwc/2005/03/10/http-authentication-plugin/
Description: Authenticate users using basic HTTP authentication (<code>REMOTE_USER</code>). This plugin assumes users are externally authenticated, as with <a href="http://www.gatorlink.ufl.edu/">GatorLink</a>.
Author: Daniel Westermann-Clark
Author URI: http://dev.webadmin.ufl.edu/~dwc/
*/

if (! class_exists('HTTPAuthenticationPlugin')) {
	class HTTPAuthenticationPlugin {
		function HTTPAuthenticationPlugin() {
			if (isset($_GET['activate']) and $_GET['activate'] == 'true') {
				add_action('init', array(&$this, 'init'));
			}
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('wp_authenticate', array(&$this, 'authenticate'), 10, 2);
			add_action('wp_logout', array(&$this, 'logout'));
			add_action('lost_password', array(&$this, 'disable_function'));
			add_action('retrieve_password', array(&$this, 'disable_function'));
			add_action('password_reset', array(&$this, 'disable_function'));
			add_action('check_passwords', array(&$this, 'check_passwords'), 10, 3);
			add_filter('show_password_fields', array(&$this, 'show_password_fields'));
		}


		/*************************************************************
		 * Plugin hooks
		 *************************************************************/

		/*
		 * Add options for this plugin to the database.
		 */
		function init() {
			if (current_user_can('manage_options')) {
				add_option('http_authentication_logout_uri', get_option('home'), 'The URI to which the user is redirected when she chooses "Logout".');
				add_option('http_authentication_auto_create_user', false, 'Should a new user be created automatically if not already in the WordPress database?');
				add_option('http_authentication_auto_create_email_domain', '', 'The domain to use for the email address of an automatically created user.');
			}
		}

		/*
		 * Add an options pane for this plugin.
		 */
		function admin_menu() {
			if (function_exists('add_options_page')) {
				add_options_page('HTTP Authentication', 'HTTP Authentication', 9, __FILE__, array(&$this, 'display_options_page'));
			}
		}

		/*
		 * If the REMOTE_USER evironment is set, use it as the username.
		 * This assumes that you have externally authenticated the user.
		 */
		function authenticate($username, $password) {
			global $using_cookie;

			// Reset values from input ($_POST and $_COOKIE)
			$username = $password = '';

			if (! empty($_SERVER['REMOTE_USER'])) {
				if (function_exists('get_userdatabylogin')) {
					$username = $_SERVER['REMOTE_USER'];
					$user = get_userdatabylogin($username);

					if (! $user or $username != $user->user_login) {
						if ((bool) get_option('http_authentication_auto_create_user')) {
							// Create user and re-read from database for login (next step)
							$this->create_user($username);
							$user = get_userdatabylogin($username);
						}
						else {
							// User is not in the WordPress database, and thus not authorized
							die("User $username does not exist in the WordPress database");
						}
					}

					// Login the user by feeding WordPress a double-MD5 hash
					$password = md5($user->user_pass);

					// User is now authorized; force WordPress to use the generated password
					$using_cookie = true;
					wp_setcookie($user->user_login, $password, $using_cookie);
				}
				else {
					die("Could not load user data");
				}
			}
			else {
				die("No REMOTE_USER found; please check your external authentication configuration");
			}
		}

		/*
		 * Logout the user by redirecting them to the logout URI.
		 */
		function logout() {
			header('Location: ' . get_option('http_authentication_logout_uri'));
			exit();
		}

		/*
		 * Generate a password for the user. This plugin does not
		 * require the user to enter this value, but we want to set it
		 * to something nonobvious.
		 */
		function check_passwords($username, $password1, $password2) {
			$password1 = $password2 = $this->get_password();
		}

		/*
		 * Used to disable certain display elements, e.g. password
		 * fields on profile screen.
		 */
		function show_password_fields($show_password_fields) {
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
		 * Generate a random password.
		 */
		function get_password($length = 10) {
			return substr(md5(uniqid(microtime())), 0, $length);
		}

		/*
		 * Create a new WordPress account for the specified username.
		 */
		function create_user($username) {
			$password = $this->get_password();
			$email_domain = get_option('http_authentication_auto_create_email_domain');

			require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
			wp_create_user($username, $password, $username . '@' . $email_domain);
		}

		/*
		 * Display the options for this plugin.
		 */
		function display_options_page() {
			$logout_uri = get_option('http_authentication_logout_uri');
			$auto_create_user = (bool) get_option('http_authentication_auto_create_user');
			$auto_create_email_domain = get_option('http_authentication_auto_create_email_domain');
?>
<div class="wrap">
  <h2>HTTP Authentication Options</h2>
  <form action="options.php" method="post">
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="http_authentication_logout_uri,http_authentication_auto_create_user,http_authentication_auto_create_email_domain" />
    <?php if (function_exists('wp_nonce_field')): wp_nonce_field('update-options'); endif; ?>

    <fieldset class="options">
      <table class="editform optiontable">
        <tr valign="top">
          <th scope="row"><label for="http_authentication_logout_uri">Logout URI</label></th>
          <td>
            <input type="text" name="http_authentication_logout_uri" id="http_authentication_logout_uri" value="<?php echo htmlspecialchars($logout_uri) ?>" size="50" /><br />
            Default is <code><?php echo htmlspecialchars(get_settings('home')); ?></code>; override to e.g. remove a cookie.
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
    </fieldset>
    <p class="submit">
      <input type="submit" name="Submit" value="Update Options &raquo;" />
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
