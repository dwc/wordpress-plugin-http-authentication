=== HTTP Authentication ===
Contributors: dwc
Tags: authentication
Requires at least: 2.0
Tested up to: 2.2
Stable tag: 1.7

Use an external authentication source in WordPress.

== Description ==

The HTTP Authentication plugin allows you to use existing means of authenticating people to WordPress. This includes Apache's basic HTTP authentication module and many others.

== Installation ==

1. Login as an existing user, such as admin.
2. Upload `http-authentication.php` to your plugins folder, usually `wp-content/plugins`.
3. Activate the plugin on the Plugins screen.
4. Add one or more users to WordPress, specifying the external username for the Nickname field. Also be sure to set the role for each user.
5. Logout.
6. Protect `wp-login.php` and `wp-admin` using your external authentication (using, for example, `.htaccess` files).
7. Try logging in as one of the users added in step 4.

Note: This version works with WordPress 2.0 and above. Use [version 1.3](http://downloads.wordpress.org/plugin/http-authentication.1.3.zip) if you are using WordPress 1.5.2.

== Frequently Asked Questions ==

= What authentication mechanisms can I use? =

Any authentication mechanism which sets the `REMOTE_USER` environment variable can be used in conjunction with this plugin. Examples include Apache's `mod_auth` and `mod_auth_ldap`.

= How should I set up external authentication? =

This depends on your hosting environment and your means of authentication.

Many Apache installations allow configuration of authentication via `.htaccess` files, while some do not. Try adding the following to your blog's top-level `.htaccess` file:

`<Files wp-login.php>
AuthName "WordPress"
AuthType Basic
AuthUserFile /path/to/passwords
Require user dwc
</Files>`

(You may also want to protect your `xmlrpc.php` file, which uses separate authentication code.)

Then, create another `.htaccess` file in your `wp-admin` directory with the following contents:

`AuthName "WordPress"
AuthType Basic
AuthUserFile /path/to/passwords
Require user dwc`

In both files, be sure to set `/path/to/passwords` to the location of your password file. For more information on creating this file, see below.

= Where can I find more information on configuring Apache authentication? =

See Apache's HOWTO: [Authentication, Authorization, and Access Control](http://httpd.apache.org/docs/howto/auth.html).

= How does this plugin authenticate users? =

This plugin doesn't actually authenticate users. It simply feeds WordPress the name of a user who has successfully authenticated through Apache.

To determine the username, this plugin uses the `REMOTE_USER` environment variable, which is set by many Apache authentication modules. If someone can find a way to spoof this value, this plugin is not guaranteed to be secure.

This plugin generates a random password each time you create a user or edit an existing user's profile. However, since this plugin requires an external authentication mechanism, this password is not requested by WordPress. Generating a random password helps protect accounts, preventing one authorized user from pretending to be another.

= If I disable this plugin, how will I login? =

Because this plugin generates a random password when you create a new user or edit an existing user's profile, you will most likely have to reset each user's password if you disable this plugin. WordPress provides a link for requesting a new password on the login screen.

Also, you should leave the `admin` user as a fallback, i.e. create a new account to use with this plugin. As long as you don't edit the `admin` profile, WordPress will store the password set when you installed WordPress.

In the worst case scenario, you may have to use phpMyAdmin or the MySQL command line to [reset a user's password](http://codex.wordpress.org/Resetting_Your_Password).
