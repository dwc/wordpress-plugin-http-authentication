=== HTTP Authentication ===
Tags: authentication
Contributors: dwc

The HTTP Authentication plugin allows you to use existing means of authenticating people to WordPress. This includes Apache's basic HTTP authentication module and many others.

== Installation ==

1. Login as an existing user, such as admin.
2. Upload `http-authentication.php` to your plugins folder, usually `wp-content/plugins`.
3. Activate the plugin on the Plugins screen.
4. Add one or more users to WordPress, specifying the external username for the Nickname field. Also be sure to set the level for each user.
5. Logout.
6. Protect `wp-login.php` and `wp-admin` using your external authentication (using, for example, `.htaccess` files).

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

Then, create another `.htaccess` file in your `wp-admin` directory with the following contents:

`AuthName "WordPress"
AuthType Basic
AuthUserFile /path/to/passwords
Require user dwc`

In both files, be sure to set `/path/to/passwords` to the location of your password file. For more information on creating this file, see below.

= Where can I find more information on configuring Apache authentication? =

See Apache's HOWTO: <a href="http://httpd.apache.org/docs/howto/auth.html">Authentication, Authorization, and Access Control</a>.
