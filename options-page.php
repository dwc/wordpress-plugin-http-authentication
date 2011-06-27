<?php
class HTTPAuthenticationOptionsPage {
	var $plugin;
	var $group;
	var $page;
	var $options;
	var $title;

	function HTTPAuthenticationOptionsPage($plugin, $group, $page, $options, $title = 'HTTP Authentication') {
		$this->plugin = $plugin;
		$this->group = $group;
		$this->page = $page;
		$this->options = $options;
		$this->title = $title;

		add_action('admin_init', array(&$this, 'register_options'));
		add_action('admin_menu', array(&$this, 'add_options_page'));
	}

	/*
	 * Register the options for this plugin so they can be displayed and updated below.
	 */
	function register_options() {
		register_setting($this->group, $this->group, array(&$this, 'sanitize_settings'));

		$section = 'http_authentication_main';
		add_settings_section($section, 'Main Options', array(&$this, '_display_options_section'), $this->page);
		add_settings_field('http_authentication_allow_wp_auth', 'Allow WordPress authentication?', array(&$this, '_display_option_allow_wp_auth'), $this->page, $section);
		add_settings_field('http_authentication_auth_label', 'Authentication label', array(&$this, '_display_option_auth_label'), $this->page, $section);
		add_settings_field('http_authentication_login_uri', 'Login URI', array(&$this, '_display_option_login_uri'), $this->page, $section);
		add_settings_field('http_authentication_logout_uri', 'Logout URI', array(&$this, '_display_option_logout_uri'), $this->page, $section);
		add_settings_field('http_authentication_auto_create_user', 'Automatically create accounts?', array(&$this, '_display_option_auto_create_user'), $this->page, $section);
		add_settings_field('http_authentication_auto_create_email_domain', 'Email address domain', array(&$this, '_display_option_auto_create_email_domain'), $this->page, $section);
	}

	/*
	 * Set the database version on saving the options.
	 */
	function sanitize_settings($input) {
		$output = $input;
		$output['db_version'] = $this->plugin->db_version;

		return $output;
	}

	/*
	 * Add an options page for this plugin.
	 */
	function add_options_page() {
		if (function_exists('is_site_admin') && is_site_admin()) {
			add_submenu_page('wpmu-admin.php', $this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
			add_options_page($this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
		}
		else {
			add_options_page($this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
		}
	}

	/*
	 * Display the options for this plugin.
	 */
	function _display_options_page() {
?>
<div class="wrap">
  <h2>HTTP Authentication Options</h2>
  <form action="options.php" method="post">
    <?php settings_errors(); ?>
    <?php settings_fields($this->group); ?>
    <?php do_settings_sections($this->page); ?>
    <p class="submit">
      <input type="submit" name="Submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button-primary" />
    </p>
  </form>
</div>
<?php
	}

	/*
	 * Display explanatory text for the main options section.
	 */
	function _display_options_section() {
	}

	/*
	 * Display the HTTP authentication database version option.
	 */
	function _display_option_db_version() {
		$db_version = $this->options['db_version'];
?>
<input type="hidden" name="<?php echo htmlspecialchars($this->group); ?>[db_version]" id="http_authentication_db_version" value="<?php echo htmlspecialchars($db_version) ?>" />
<?php
	}

	/*
	 * Display the WordPress authentication checkbox.
	 */
	function _display_option_allow_wp_auth() {
		$allow_wp_auth = $this->options['allow_wp_auth'];
?>
<input type="checkbox" name="<?php echo htmlspecialchars($this->group); ?>[allow_wp_auth]" id="http_authentication_allow_wp_auth"<?php if ($allow_wp_auth) echo ' checked="checked"' ?> value="1" /><br />
Should the plugin fallback to WordPress authentication if none is found from the server?
<?php
		if ($allow_wp_auth && $this->options['login_uri'] == wp_login_url()) {
			echo '<br /><strong>WARNING</strong>: You must set the login URI below to your external authentication system. Otherwise you will not be able to login!';
		}
	}

	/*
	 * Display the authentication label field, describing the authentication system
	 * in use.
	 */
	function _display_option_auth_label() {
		$auth_label = $this->options['auth_label'];
?>
<input type="text" name="<?php echo htmlspecialchars($this->group); ?>[auth_label]" id="http_authentication_auth_label" value="<?php echo htmlspecialchars($auth_label) ?>" size="50" /><br />
Default is <code>HTTP authentication</code>; override to use the name of your single sign-on system.
<?php
	}

	/*
	 * Display the login URI field.
	 */
	function _display_option_login_uri() {
		$login_uri = $this->options['login_uri'];
?>
<input type="text" name="<?php echo htmlspecialchars($this->group); ?>[login_uri]" id="http_authentication_login_uri" value="<?php echo htmlspecialchars($login_uri) ?>" size="50" /><br />
Default is <code><?php echo htmlspecialchars(wp_login_url()); ?></code>; override to direct users to a single sign-on system.<br />
The string <code>%s</code> will be replaced with the appropriate return URI as provided by WordPress.
<?php
	}

	/*
	 * Display the logout URI field.
	 */
	function _display_option_logout_uri() {
		$logout_uri = $this->options['logout_uri'];
?>
<input type="text" name="<?php echo htmlspecialchars($this->group); ?>[logout_uri]" id="http_authentication_logout_uri" value="<?php echo htmlspecialchars($logout_uri) ?>" size="50" /><br />
Default is <code><?php echo htmlspecialchars(wp_logout_url()); ?></code>; override to e.g. remove a cookie.<br />
The string <code>%s</code> will be replaced with your blog's home URI.
<?php
	}

	/*
	 * Display the automatically create accounts checkbox.
	 */
	function _display_option_auto_create_user() {
		$auto_create_user = $this->options['auto_create_user'];
?>
<input type="checkbox" name="<?php echo htmlspecialchars($this->group); ?>[auto_create_user]" id="http_authentication_auto_create_user"<?php if ($auto_create_user) echo ' checked="checked"' ?> value="1" /><br />
Should a new user be created automatically if not already in the WordPress database?<br />
Created users will obtain the role defined under &quot;New User Default Role&quot; on the <a href="options-general.php">General Options</a> page.
<?php
	}

	/*
	 * Display the email domain field.
	 */
	function _display_option_auto_create_email_domain() {
		$auto_create_email_domain = $this->options['auto_create_email_domain'];
?>
<input type="text" name="<?php echo htmlspecialchars($this->group); ?>[auto_create_email_domain]" id="http_authentication_auto_create_email_domain" value="<?php echo htmlspecialchars($auto_create_email_domain) ?>" size="50" /><br />
When a new user logs in, this domain is used for the initial email address on their account. The user can change his or her email address by editing their profile.
<?php
	}
}
?>
