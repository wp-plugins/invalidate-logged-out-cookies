<?php
/*
Plugin Name: Invalidate Logged Out Cookies
Plugin URI: http://moggy.laceous.com/
Description: This plugin immediately invalidates your auth cookies when you manually log out. This can limit the amount of time an attacker can hijack your session.
Version: 0.1
Author: moggy
Author URI: http://moggy.laceous.com/
*/

/*
    Copyright 2009

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once( dirname(__FILE__).'/InvalidateLoggedOutCookies.php' );
require_once( dirname(__FILE__).'/pluggable_overrides.php' );
new InvalidateLoggedOutCookies;