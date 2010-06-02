<?php
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

class PageSpot
{
    public static $VERSION = '2010-04';
    public static $DB_VERSION = '2009-03';

    public static $TBL_NAME = 'pagespot';

    public static $SIDEBAR_SPOT = '_ps_sidebar';

    public static function install() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . self::$TBL_NAME;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = 'CREATE TABLE ' . $table_name . ' (
                        pagespot_id mediumint(9) NOT NULL AUTO_INCREMENT,
                        page_id bigint(20) UNSIGNED NOT NULL,
                        spot varchar(50) NOT NULL,
                        post_id bigint(20) UNSIGNED NOT NULL,
                        PRIMARY KEY  (pagespot_id),
                        UNIQUE KEY page_id_spot(page_id, spot)
                    );';

            $out = dbDelta($sql);

            add_option("pagespot_db_version", self::$DB_VERSION);
        }

        ob_start();
        $page_ctr_id = get_option('pagespot_page_container_id');
        if (!$page_ctr_id) {
            $page_ctr_id = $wpdb->get_var(
                'SELECT ID from '.$wpdb->posts.' WHERE post_type=\'page\'
                AND post_title=\'[PageSpot] Page Snippets\''
            );

            if (!$page_ctr_id) {
                $page_ctr_id = wp_insert_post(array(
                    'post_type'=>'page', 'post_status'=>'private', 'menu_order'=>999,
                    'post_content'=>'PageSpot special page - leave [PageSpot] at the start of the page title!',
                    'post_title'=>'[PageSpot] Page Snippets'
                ));
            }

            add_option('pagespot_page_container_id', $page_ctr_id);
        }

        $sidebar_ctr_id = get_option('pagespot_sidebar_container_id');
        if (!$sidebar_ctr_id) {
            $sidebar_ctr_id = $wpdb->get_var(
                'SELECT ID from '.$wpdb->posts.' WHERE post_type=\'page\'
                AND post_title=\'[Sidebar] Default Sidebar\''
            );

            if (!$sidebar_ctr_id) {
                $sidebar_ctr_id = wp_insert_post(array(
                    'post_type'=>'page', 'post_status'=>'private', 'menu_order'=>999,
                    'post_content'=>'PageSpot special page - leave [Sidebar] at the start of the page title!',
                    'post_title'=>'[Sidebar] Default Sidebar'
                ));
            }

            add_option('pagespot_sidebar_container_id', $sidebar_ctr_id);
        }

        ob_end_clean();


        $installed_ver = get_option("pagespot_db_version");
        if ($installed_ver != self::$DB_VERSION) {

            //TODO Future upgrades go here

        }
    }

    /**
     * Called from action hook "template_redirect".
     *
     * From http://codex.wordpress.org/Plugin_API/Action_Reference:
     * Runs before the determination of the template file to be used to display
     * the requested page, so that a plugin can override the template file choice.
     */
    public static function template_redirect_action() {
        if (!have_posts())
            return;
        the_post();

        if (!is_page() && !is_single()) {
            rewind_posts();
            return;
        }

        $id = get_the_ID();
        if (!$id) {
            die('No ID in context!');
        }

        // Grab template for this page.
        if (!$template_file = self::get_pagespot_template_name($id)) {
            rewind_posts();
            return;
        }

        /*****
        $content = self::do_replace($template_file);
        $tfn = tempnam(sys_get_temp_dir(), 'pagespot_'.get_the_ID().'_');
        file_put_contents($tfn, $content);

        rewind_posts();
        include $tfn;
        unlink($tfn);
        ******/

        rewind_posts();
        ob_start();
        include $template_file;
        $out = ob_get_clean();

        $out = self::do_replace_str($out, $id);
        print $out;

        exit;
    }

    /**
     * Get the current page template and return its filename if it's a
     * PageSpot template.  Otherwise return false.
     *
     * @return false|string
     */
    public static function get_pagespot_template_name($id=null) {
        if (empty($id)) {
            $id = get_the_ID();
        }
        //$template_file = get_page_template();
        $template_file = TEMPLATEPATH.'/'.get_post_meta($id, '_wp_page_template', true);
        //print_r($template_file); exit;
        if (false === strpos(strtolower($template_file), '.php')
            || 0 !== strpos(basename(strtolower($template_file)), 'pagespot')
            || !file_exists($template_file)) {

            return false;
        }
        return $template_file;
    }

    /**
     * Given a template file, replace PageSpot annotations with content and
     * return the modified total contents.
     *
     * @param string $from_filename
     * @return string
     */
    protected static function do_replace($from_filename) {
        $content = file_get_contents($from_filename);
        return self::do_replace_str($content);

    }

    protected static function do_replace_str($content, $id=null) {
        $tags = self::parse_tags($content);

        foreach ($tags as $tag) {
            $content = self::do_tag_replace($content, $tag, $id);
        }
        return $content;
    }

    /**
     * Parse out PageSpot tag annotations from a template's content.
     *
     * @param string $template
     * @return array
     */
    protected static function parse_tags($content) {
        $mat = array();
        preg_match_all("/\[\[PageSpot[:]+[^\]]+\]\]/", $content, $mat);
        return $mat[0];
    }

    /**
     * Replace specified tag in content
     *
     * @param string $content
     * @param string $tag
     * @return string
     */
    protected static function do_tag_replace($content, $tag, $page_id) {
        global $wpdb;
        if (empty($page_id))
            $page_id = get_the_ID();

        if (empty($page_id)) {
            trigger_error('No page ID in context', E_USER_WARNING);
            return $content;
        }

        $tag_name = self::get_tag_name($tag);

        $inject_post_id = self::get_post_for_page_spot($page_id, $tag_name);
        if (empty($inject_post_id)) {
            $injectContent = '';
            /*'<?php
            print "No post assigned to " . ucFirst("'.$tag_name.'") . "!";
            ?>
            ';*/
        }
        else {
            $injectContent = $wpdb->get_var($wpdb->prepare(
                'SELECT post_content FROM ' . $wpdb->posts .
                ' WHERE id=%d'
                , $inject_post_id
            ));
            $injectContent = apply_filters('the_content', $injectContent);

            $url = get_edit_post_link($inject_post_id);
            if ($url) {
                $link = '<a class="post-edit-link" href="' . $url . '" title="' . esc_attr( __( 'Edit Spot' ) ) . '">Edit this Spot</a>';
                $injectContent .= '<p>' . apply_filters( 'edit_post_link', $link, $inject_post_id ) . '</p>';
            }
        }

        return str_replace($tag, $injectContent, $content);
    }

    /**
     * Get the post ID that goes at particular spot on a given page
     *
     * @param int $pageId
     * @param string $spot
     * @return int
     */
    public static function get_post_for_page_spot($pageId, $spot) {
        global $wpdb;
        $postId = $wpdb->get_var($wpdb->prepare(
            'SELECT post_id FROM ' . $wpdb->prefix . self::$TBL_NAME .
            ' WHERE page_id=%d and lower(spot)=%s'
            , $pageId, strtolower($spot)
        ));
        return $postId;
    }

    /**
     * Given a PageSpot annotation, get the short tag name for the
     * `spot` column in the PageSpot table
     *
     * @param string $tag
     * @return string
     */
    protected static function get_tag_name($tag) {
        $mat = array();
        preg_match("/\[\[PageSpot[:]+([^\]]+)\]\]/", $tag, $mat);
        return strtolower($mat[1]);
    }

    /**
     * Print the sidebar for this page, if one is assigned.
     *
     * @param string $wrapperClass Each component page of the sidebar will be
     * wrapped in a <div> with this CSS class assigned.
     */
    public static function print_sidebar($wrapperClass="sidebarmodule") {
        global $wpdb;

        $sidebarCtr = self::get_post_for_page_spot(get_the_ID(), PageSpot::$SIDEBAR_SPOT);
        if (empty($sidebarCtr)) {
            //$sidebarCtr = get_option('pagespot_sidebar_container_id');
            return;
        }

        // Plugin "events-manager" has this stupid hook that's interfering
        // with the_content.  Remove it and re-add it when we're done with these posts.
        $_my_dbem_workaround = false;
        if (has_filter('the_content', 'dbem_filter_events_page')) {
            remove_filter('the_content', 'dbem_filter_events_page');
            $_my_dbem_workaround = true;
        }

        $rs = $wpdb->get_results(
            'SELECT post_content FROM ' . $wpdb->posts .
            ' WHERE post_parent = ' . $sidebarCtr .
            ' and post_type=\'page\' and post_status!=\'draft\'' .
            ' order by menu_order, id'
        );
        //print_r($rs);
        if (!empty($rs)) {
            foreach ($rs as $row) {
                $injectContent = apply_filters('the_content', $row->post_content);
                ?>
                <div class="<?php print $wrapperClass; ?>">
                    <?php print $injectContent; ?>
                </div>
                <?php
            }
        }

        if ($_my_dbem_workaround) {
            add_filter('the_content', 'dbem_filter_events_page');
        }
    }
}

