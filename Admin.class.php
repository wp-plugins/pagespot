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

class PageSpot_Admin extends PageSpot
{
    public static function page_edit_form($post) {
        global $wpdb, $wp_filter;
        
        if (!$template_file = self::get_pagespot_template_name($post->ID)) {
            ?>
            <i>Set this page's template to a PageSpot template to use PageSpot.</i>
            <?php
            return;
        }
        
        // Conflict with RoleScoper plugin filter on wp_dropdown_pages causing a bug here.
        // Remove all filters on that action and re-add when finished.
        // A little heavy-handed but for now we'll just allow ANY page to be assigned to a Spot,
        // regardless of other plugins' filters
        
        $_sv_filter = null;
        if (!empty($wp_filter['wp_dropdown_pages'])) {
            $_sv_filter = $wp_filter['wp_dropdown_pages'];
            remove_all_filters('wp_dropdown_pages');
        }
        
        $spots = self::parse_tags(file_get_contents($template_file));
        ?>
        <table class="widefat">
        <thead>
            <tr>
                <th>Spot</th>
                <th>What to Put There</th>
            </tr>
        </thead>
        <?php
        foreach ($spots as $spot) {
            $spot = self::get_tag_name($spot);
            $selected = self::get_post_for_page_spot($post->ID, $spot);
            ?>
            <tr>
                <td><b><?php print ucfirst($spot) ?></b></td>
                <td><?php wp_dropdown_pages(array('selected' => $selected, 'name' => "pagespot[$spot]", 'show_option_none' => 'Ignore', 'sort_column'=> 'menu_order, post_title')); ?></td>
            </tr>
        <?php
        } ?>
        </table>
        <?php
        
        if ($_sv_filter != null) {
            $wp_filter['wp_dropdown_pages'] = $_sv_filter;
        }
    }
    
    public static function page_edit_sidebar_form($post) {
        global $wpdb;
        $rs = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} 
                WHERE post_type='page'
                AND post_status='private'
                AND post_title like '[Sidebar]%'"
        );
        $selected = self::get_post_for_page_spot($post->ID, self::$SIDEBAR_SPOT);
        if (empty($selected)) {
            $selected = get_option('pagespot_sidebar_container_id');
        }
        ?>
        <select name="pagespot[<?php print self::$SIDEBAR_SPOT ?>]">
            <?php
            if (!empty($rs)) {
                foreach ($rs as $row) {
                    $t = trim(str_replace('[Sidebar]', '', $row->post_title));
                    $opt_sel = ($row->ID == $selected ? ' selected' : '');
                    print "<option value=\"{$row->ID}\"{$opt_sel}>{$t}</option>";
                }
            }
            ?>
            <option value="-1" <?php if ($selected == -1) print 'selected' ?>>None</option>
        </select>
        <?php
    }
    
    public static function post_edit_sidebar_form($post) {
        global $wpdb;
        
        if (!self::isPostTemplatingEnabled())
            return;
        
        $tpl = get_post_meta($post->ID, '_wp_page_template', true);
        
        ?>
        <h5><?php _e('Template') ?></h5>
        <label class="screen-reader-text" for="pagespot[<?php print self::$SIDEBAR_SPOT ?>]">
            <?php _e('Post Template') ?></label>
        <select name="page_template" id="page_template">
        <option value='default'><?php _e('Default Template'); ?></option>
        <?php page_template_dropdown($tpl); ?>
        </select>
        <?php
        
        if ($template_file = self::get_pagespot_template_name($post->ID)) {
            $rs = $wpdb->get_results(
                "SELECT ID, post_title FROM {$wpdb->posts} 
                    WHERE post_type='page'
                    AND post_status='private'
                    AND post_title like '[Sidebar]%'"
            );
            $selected = self::get_post_for_page_spot($post->ID, self::$SIDEBAR_SPOT);
            if ($selected === null) {
                $selected = get_option('pagespot_sidebar_container_id');
            }
            ?>
            <h5><?php _e('Sidebar') ?></h5>
            <label class="screen-reader-text" for="pagespot[<?php print self::$SIDEBAR_SPOT ?>]">
                <?php _e('PageSpot Sidebar') ?></label>
            <select name="pagespot[<?php print self::$SIDEBAR_SPOT ?>]">
                <?php
                if (!empty($rs)) {
                    foreach ($rs as $row) {
                        $t = trim(str_replace('[Sidebar]', '', $row->post_title));
                        $opt_sel = ($row->ID == $selected ? ' selected' : '');
                        print "<option value=\"{$row->ID}\"{$opt_sel}>{$t}</option>";
                    }
                }
                ?>
                <option value="0" <?php if ($selected === '0') print 'selected' ?>>None</option>
            </select>
        <?php
        }
    }
    
    public static function save_post_action($post_id) {
        global $wpdb;
        
        if (false !== ($parent = wp_is_post_revision($post_id))) {
            //$post_id = $parent;
            return;
        }
        
        // Save the Template for posts
        if (self::isPostTemplatingEnabled() && isset($_POST['page_template'])) {
            if (!is_page($post_id)) {
                //die("save template {$_POST['page_template']} for post {$post_id}");
                update_post_meta($post_id, '_wp_page_template', $_POST['page_template']);
            }
        }
        
        // Set Private visibility to any child of private [PageSpot] page
        $query = "SELECT ID FROM {$wpdb->posts}
             WHERE ID=(SELECT post_parent FROM {$wpdb->posts} WHERE id=%d) 
             AND post_status='private'
             AND (ID=%d or ID=%d or post_title like '[Sidebar]%%' or post_title like '[PageSpot]%%')";
        $parent = $wpdb->get_var($wpdb->prepare($query, 
            $post_id, 
            get_option('pagespot_sidebar_container_id'),
            get_option('pagespot_page_container_id')));
        if ($parent || 
            $post_id == get_option('pagespot_page_container_id') || 
            $post_id == get_option('pagespot_sidebar_container_id')) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_status='private'
                 WHERE id=%d"
                 , $post_id
            ));
        }
        
        /*if (isset($_POST['pagespot_sidebar_ctr'])) {
            $existing = self::get_post_for_page_spot($post_id, self::$SIDEBAR_SPOT);
        }*/
        
        if (!isset($_POST['pagespot'])) {
            //die('No pagespot variable');
            return;
        }
        
        $pagespot = $_POST['pagespot'];
        if (!is_array($pagespot)) {
            //die('Pagespot is not an array');
            //TODO generate a proper error here?  seems to get triggered on autosaves as well.
            return;
        }
        
        //print_r($pagespot); //exit;
        
        foreach ($pagespot as $spot=>$pageAtSpotId) {
            $existing = $wpdb->get_var($wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->prefix . self::$TBL_NAME .
                ' WHERE page_id=%d and spot=%s'
                , $post_id, $spot
            ));
            
            //print_r("Post {$post_id} at spot {$spot} has existing {$existing}");
            
            if ($existing !== null && ($pageAtSpotId == $existing)) {
                continue;
            }
            else if ($existing !== null && !empty($pageAtSpotId)) {
                //print_r("Update");
                $wpdb->query($wpdb->prepare(
                    'UPDATE ' . $wpdb->prefix . self::$TBL_NAME .
                    ' SET post_id=%d WHERE page_id=%d AND spot=%s'
                    , $pageAtSpotId, $post_id, $spot
                ));
            }
            else if ($existing !== null && empty($pageAtSpotId)) {
                //print_r("Delete");
                $wpdb->query($wpdb->prepare(
                    'DELETE FROM ' . $wpdb->prefix . self::$TBL_NAME .
                    ' WHERE page_id=%d AND spot=%s'
                    , $post_id, $spot
                ));
            }
            else if ($existing === null && !empty($pageAtSpotId)) {
                //print_r("Insert");
                $wpdb->query($wpdb->prepare(
                    'INSERT INTO ' . $wpdb->prefix . self::$TBL_NAME .
                    ' (page_id, spot, post_id) VALUES (%d, %s, %d)'
                    , $post_id, $spot, $pageAtSpotId
                ));
            }
        }
        
        //exit;
    }
    
    /**
     * Add a filter to get_pages, which doesn't include private pages 
     * in the dropdown list for Page Parent.  Allows us to put pages 
     * underneath the private [PageSpot] page.
     *
     * @param unknown_type $pages
     * @return unknown
     */
    public static function get_pages_filter($pages) {
        //print_r($pages[0]);
        global $wpdb, $post;
        
        $privatePages = $wpdb->get_results(
            'SELECT * FROM ' . $wpdb->posts . 
            ' WHERE post_type = \'page\' 
                AND post_status = \'private\'
                ORDER BY post_title'
        );
        if (empty($privatePages)) {
            return $pages;
        }
        
        //$privatePages = & get_page_children(0, $privatePages);
        if ($post->ID != 0) {
            $exclude = (int) $post->ID;
            $children = get_page_children($exclude, $privatePages);
            $excludes = array();
            foreach ( $children as $child )
                $excludes[] = $child->ID;
            $excludes[] = $exclude;
            $total = count($privatePages);
            for ( $i = 0; $i < $total; $i++ ) {
                if ( in_array($privatePages[$i]->ID, $excludes) )
                    unset($privatePages[$i]);
            }
        }
        
        return array_merge($pages, $privatePages);
    }
    
    public static function isPostTemplatingEnabled() {
        return get_option('pagespot_post_templating', 1);
    }
    
    public static function setPostTemplatingEnabled($option) {
        $option = ($option ? 1 : 0);
        update_option('pagespot_post_templating', $option);   
    }
    
    /**
     * Called from hook to add an admin page for PageSpot
     *
     */
    public static function admin_menu() {
        global $wpdb;
        
        
        ?>
        <div class="wrap">
            <div id='icon-options-general' class='icon32'>
                <br/>
            </div>
            <h2>PageSpot Options</h2>
            <form id="pagespot_admin_form" action="<?php bloginfo('wpurl') ?>/wp-admin/admin-ajax.php" 
             method="get" onsubmit="return pagespot_admin_submit(this)">
                <?php wp_nonce_field('update-options') ?>
                <table id="pagespot_admin_tbl" class="form-table">
                <tr valign="top">
                    <th scope="row"><h3>PageSpot Post Templating:</h3></th>
                    <td>
                        <input type="radio" name="ps_post_templating" value="1" <?php 
                            if (self::isPostTemplatingEnabled()) print "checked"; 
                            ?>/>&nbsp;Enabled
                        
                        &nbsp;&nbsp;&nbsp;
                        
                        <input type="radio" name="ps_post_templating" value="0" <?php 
                            if (!self::isPostTemplatingEnabled()) print "checked"; 
                            ?>/>&nbsp;Disabled
                    
                        <p>
                            When enabled, PageSpot will add a control to your 
                            Posts to select a template from your theme, so you 
                            can add PageSpot to your Posts!
                        </p>
                        <p>
                            To do this, PageSpot adds metadata to your Posts in 
                            the same way that Wordpress does it for Pages.  Disable 
                            this if you're using another plugin that provides Post 
                            templating by the same method.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <input type="submit" class="button-primary" value="<?php _e('Update') ?>" />
                        <img id="pagespot_wait" src="<?php bloginfo('wpurl') ?>/wp-admin/images/wpspin_dark.gif" style="display:none;" title="" alt="" />
                    </td>
                </tr>
                </table>
                
            </form>
        </div>
        <?php
    }
    
    public static function admin_scripts() {
        wp_enqueue_script('pagespot-admin', 
            path_join(WP_PLUGIN_URL, basename(dirname(__FILE__)).'/pagespot-admin.js'));
    }
    
    public static function save_options() {
        global $wpdb;
        $errors = array();
        
        if (isset($_POST['action']) && $_POST['action'] == 'pagespot_save_options') {
            $templating = filter_input(INPUT_POST, 'ps_post_templating', FILTER_VALIDATE_INT);
            //var_dump($templating); exit;
            if (null !== $templating) {
                self::setPostTemplatingEnabled($templating);
            }
            else {
                $errors[] = 'No options available to save';
            }
        }
        else {
           $errors[] = 'Bad request';
        }
        
        $out = array(
            'errors' => implode("\n", $errors)
        );
        
        print json_encode($out);
        exit;
    }
}