<?php
if ( !function_exists('wp_validate_auth_cookie') ) :
// Copied from WP2.8 pluggable.php file
/**
 * Validates authentication cookie.
 *
 * The checks include making sure that the authentication cookie is set and
 * pulling in the contents (if $cookie is not used).
 *
 * Makes sure the cookie is not expired. Verifies the hash in cookie is what is
 * should be and compares the two.
 *
 * @since 2.5
 *
 * @param string $cookie Optional. If used, will validate contents instead of cookie's
 * @param string $scheme Optional. The cookie scheme to use: auth, secure_auth, or logged_in
 * @return bool|int False if invalid cookie, User ID if valid.
 */
function wp_validate_auth_cookie($cookie = '', $scheme = '') {
	if ( ! $cookie_elements = wp_parse_auth_cookie($cookie, $scheme) ) {
		do_action('auth_cookie_malformed', $cookie, $scheme);
		return false;
	}

	extract($cookie_elements, EXTR_OVERWRITE);

	$expired = $expiration;

	// Allow a grace period for POST and AJAX requests
	if ( defined('DOING_AJAX') || 'POST' == $_SERVER['REQUEST_METHOD'] )
		$expired += 3600;

	// Quick check to see if an honest cookie has expired
	if ( $expired < time() ) {
		do_action('auth_cookie_expired', $cookie_elements);
		return false;
	}

	$user = get_userdatabylogin($username);
	if ( ! $user ) {
		do_action('auth_cookie_bad_username', $cookie_elements);
		return false;
	}

	$pass_frag = substr($user->user_pass, 8, 4);

	$key = wp_hash($username . $pass_frag . '|' . $expiration, $scheme);
	$hash = hash_hmac('md5', $username . '|' . $expiration, $key);

	if ( $hmac != $hash ) {
		do_action('auth_cookie_bad_hash', $cookie_elements);
		return false;
	}

	/******************** PLUGIN ADDITIONS ********************/

	if ( InvalidateLoggedOutCookies::is_cookie_invalid($username, $expiration, $hmac, $scheme) ) {
		return false;
	}

	// It'd be nice if the return statement below included 'apply_filter'
	//  Then we wouldn't have to override this entire function
	// Under PHP5 we could also set $user->ID via the 'auth_cookie_valid' action
	//  Unfortunately, this won't work in PHP4
	// Some talk about changing pluggable functions to 'return apply_filter()':
	//  http://www.nabble.com/overriding-pluggable.php-functions-td21379698.html
	//  http://core.trac.wordpress.org/ticket/8833

	/******************** PLUGIN ADDITIONS ********************/

	do_action('auth_cookie_valid', $cookie_elements, $user);

	return $user->ID;
}
return 'success';
endif;
return 'failure';