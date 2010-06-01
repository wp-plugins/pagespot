<?php
/*
 * Plugin Name: PageSpot
 * Plugin URI: http://pixelnix.com
 * Description: Annotate your Page templates with spots to pull in other Pages.
 * Version: 0.1.4
 * Author: Nick Eby
 * Author URI: http://pixelnix.com
 */

/*  Copyright 2009 Nick Eby (email:nick@pixelnix.com)

    This file is part of PageSpot.

    PageSpot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PageSpot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with PageSpot.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__).'/PageSpot.class.php';
require_once dirname(__FILE__).'/Admin.class.php';

// Installing the plugin
register_activation_hook(__FILE__, array('PageSpot', 'install'));

// Adding an edit area to the main Page edit screen
add_action('edit_page_form', create_function('',
    "add_meta_box('pagespotdiv', 'PageSpot', array('PageSpot_Admin', 'page_edit_form'), 'page');"
));
add_action('edit_form_advanced', create_function('',
    "add_meta_box('pagespotdiv', 'PageSpot', array('PageSpot_Admin', 'page_edit_form'), 'post');"
));

add_action('submitpage_box', create_function('',
    "add_meta_box('pagespotdiv', 'Sidebar', array('PageSpot_Admin', 'page_edit_sidebar_form'), 'page', 'side');"
));

if (PageSpot_Admin::isPostTemplatingEnabled()) {
    add_action('submitpost_box', create_function('',
        "add_meta_box('pagespotdiv', 'PageSpot', array('PageSpot_Admin', 'post_edit_sidebar_form'), 'post', 'side');"
    ));
}

function _pagespot_admin_head() {
    global $post;
    add_filter('get_pages', array('PageSpot_Admin', 'get_pages_filter'));
    if (!empty($post) && empty($post->ID) && isset($_GET['_ps_parent_id']))
        $post->post_parent = $_GET['_ps_parent_id'];
}
add_action('admin_head', '_pagespot_admin_head', 999);

function _pagespot_wp_dropdown_pages_filter($orig_html) {
    global $post;
    if (!empty($post) && empty($post->ID) && isset($_GET['_ps_parent_id'])) {
        $html = str_replace(array('selected="selected"', 'selected'), '', $orig_html);
        $html = preg_replace("/option value=['\"]{$_GET['_ps_parent_id']}['\"]/", "option value='{$_GET['_ps_parent_id']}' selected", $html);
        return $html;
    }
    else return $orig_html;
}
add_filter('wp_dropdown_pages', '_pagespot_wp_dropdown_pages_filter', 999);

function _pagespot_admin_notices() {
    global $post, $wpdb;
    if (empty($post)) return;

    if (preg_match("/(page-new.php|page.php)/", $_SERVER['SCRIPT_NAME']) &&
        $post->post_type == 'page') {
        //print_r($post);

        if ($post->post_parent == get_option('pagespot_page_container_id')) {
            ?>
            <div class="error">
                This page is a PageSpot snippet; to keep it that way, keep Parent set to
                [PageSpot] Page Snippets.  This page's Visibility will be auto-set
                to Private.
            </div>
            <?php
        }
        elseif ($post->ID == get_option('pagespot_page_container_id')) {
            ?>
            <div class="error">
                This is the PageSpot page snippets container.  Keep its Visibility
                set to Private at all times!  It is only used as the Parent of
                all your PageSpot snippets.  It will not be shown on your public
                website.
            </div>
            <?php
        }
        //elseif ($post->post_parent == get_option('pagespot_sidebar_container_id')) {
        elseif (null != $wpdb->get_var($wpdb->prepare(
            "SELECT ID from {$wpdb->posts}
                WHERE post_status='private'
                and post_title like '[Sidebar]%%'
                and ID=%d",
            $post->post_parent))) {
            ?>
            <div class="error">
                This page is a Sidebar Item; to keep it that way, keep Parent set to
                a [Sidebar] page.  This page's Visibility will be auto-set
                to Private.
            </div>
            <?php
        }
        //elseif ($post->ID == get_option('pagespot_sidebar_container_id')) {
        elseif ($post->post_status=='private' && 0===strpos($post->post_title, '[Sidebar]')) {
            ?>
            <div class="error">
                This is the Sidebar items container.  Keep its Visibility
                set to Private at all times!  It is only used as the Parent of
                all your Sidebar Items.  It will not be shown on your public
                website.
            </div>
            <?php
        }

    }

}
add_action('admin_notices', '_pagespot_admin_notices');

add_action('save_post', array('PageSpot_Admin', 'save_post_action'));

function _pagespot_favorite_actions_filter($actions) {
    $actions['page-new.php?_ps_parent_id='.get_option('pagespot_page_container_id')] =
        array(__('New PageSpot Snippet'), 'edit_pages');

    $actions['page-new.php?_ps_parent_id='.get_option('pagespot_sidebar_container_id')] =
        array(__('New Sidebar Item'), 'edit_pages');

    return $actions;
}
add_filter('favorite_actions', '_pagespot_favorite_actions_filter');

// Munge content for pages
add_action('template_redirect', array('PageSpot', 'template_redirect_action'));

// Adding an admin page under Themes
function _pagespot_admin_menu() {
    // Add the menu link to our admin page
    /*$page = add_submenu_page('themes.php',
        'PageSpot',
        'PageSpot',
        9,
        __FILE__,
        array('PageSpot_Admin', 'admin_menu'));*/
    $page = add_options_page('PageSpot', 'PageSpot', 9, __FILE__, array('PageSpot_Admin', 'admin_menu'));
    // Add our javascript file to the admin scripts for the page we just created
    add_action('admin_print_scripts-'.$page, array('PageSpot_Admin', 'admin_scripts'));
}
add_action('admin_menu', '_pagespot_admin_menu');

// Add response hooks to ajax actions taken from our admin page/javascript
add_action('wp_ajax_pagespot_save_options', array('PageSpot_Admin', 'save_options'));

if (!function_exists('esc_attr')) {
    function esc_attr( $text ) {
        $safe_text = $text;
        //$safe_text = wp_check_invalid_utf8( $text );
        //$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
        //return apply_filters( 'attribute_escape', $safe_text, $text );
        return $safe_text;
    }
}
