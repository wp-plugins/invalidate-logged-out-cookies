=== Invalidate Logged Out Cookies ===
Contributors: laceous
Tags: login, logout, security, cookies
Requires at least: 2.9
Tested up to: 2.9
Stable tag: trunk

This plugin will immediately invalidate your auth cookies when you manually log out.

== Description ==

**Due to lack of interest (both my own and based on the number of downloads) this plugin will not be updated for WP 3.0**

WordPress' auth cookies include a built-in expiration date (either 2 or 14 days depending on if the 'Remember Me' option is checked). Even if you remove the client-side cookie (by manually logging out or just closing your browser if 'Remember Me' wasn't checked when logging in) the data that was stored within the cookie is still valid until the expiration date is reached.

This could be an issue if someone managed to "steal" your cookie(s). They would still be able to access your website for some time into the future.

This plugin will immediately invalidate your auth cookies when you manually log out. This, of course, also means that you have to manually click 'Log out' for this plugin to work properly (you can't just close your browser to remove any cookies that expire at the end of the session). This won't prevent session hijacking, but should limit the amount of time that an attacker can access your website.

== Installation ==

1. Upload the entire `invalidate-logged-out-cookies/` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

* If upgrading manually, make sure to disable and then re-enable the plugin (upgrading through the admin interface will do this automatically)

== Frequently Asked Questions ==

= Will this plugin invalidate my cookies if I logged in before the plugin was activated? =
No. This plugin will only invalidate cookies that were created after activating the plugin.

= Will this plugin work with non-standard auth cookies? =
Most likely, no. This plugin is only meant to be used with the standard auth cookies that WordPress uses.

= Known conflicts with other plugins =
This plugin overrides the core `wp_validate_auth_cookie` function. This means that you can't enable this plugin and another that also overrides the same function.

This is a non-comprehensive list of other plugins that also override this function (and should not be used at the same time as this plugin):

* [Safer Cookies](http://wordpress.org/extend/plugins/safer-cookies/ "Safer Cookies")
* [Admin SSL](http://wordpress.org/extend/plugins/admin-ssl-secure-admin/ "Admin SSL")
* [WordPress 2.6+ and bbPress 0.9 cookie integration](http://wordpress.org/extend/plugins/wordpress-26-and-bbpress-09-integration/ "WordPress 2.6+ and bbPress 0.9 cookie integration")
* [No Login](http://wordpress.org/extend/plugins/no-login/ "No Login")
* [Disclose-Secret](http://wordpress.org/extend/plugins/disclose-secret/ "Disclose-Secret")
* [PhotoQ Photoblog Plugin](http://wordpress.org/extend/plugins/photoq-photoblog-plugin/ "PhotoQ Photoblog Plugin")

It's also possible that if another plugin is overriding a related function (e.g. `wp_generate_auth_cookie`) that this plugin will not work correctly.

= How can I know if this plugin is properly overriding the 'wp_validate_auth_cookie' function? =
Once activated, if this plugin is NOT overriding the function, then a message will be shown to admin users towards the top of every admin page.

= What if I can't log in after activating this plugin? =
Simply rename or delete the plugin so WordPress can't find it. This step requires that you have access to the filesystem where WordPress is installed (via FTP, SFTP, etc).

There's a small chance that this might happen. It most likely happens because of an incompatibility with another plugin that also overrides one of the core `auth_cookie` functions.

== Changelog ==

= 0.1.1 =
* Update for WP 2.9 (supports WP 2.9 only)
* Added the `auth_cookie_invalidated_cookie` action
= 0.1 =
* Initial version (supports WP 2.8 only)