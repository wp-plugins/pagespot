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
        global $wpdb;
        
        if (!$template_file = self::get_pagespot_template_name($post->ID)) {
            ?>
            <i>Set this page's template to a PageSpot template to use PageSpot.</i>
            <?php
            return;
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
                <td><?php wp_dropdown_pages(array('selected' => $selected, 'name' => "pagespot[$spot]", 'show_option_none' => false, 'sort_column'=> 'menu_order, post_title')); ?></td>
            </tr>
        <?php
        } ?>
        </table>
        <?php
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
    
    public static function save_post_action($post_id) {
        global $wpdb;
        
        if (false !== ($parent = wp_is_post_revision($post_id))) {
            //$post_id = $parent;
            return;
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
        
        //print_r($pagespot);
        //exit;
        
        foreach ($pagespot as $spot=>$pageAtSpotId) {
            $existing = $wpdb->get_var($wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->prefix . self::$TBL_NAME .
                ' WHERE page_id=%d and spot=%s'
                , $post_id, $spot
            ));
            
            if (!empty($existing) && ($pageAtSpotId == $existing)) {
                continue;
            }
            else if (!empty($existing) && !empty($pageAtSpotId)) {
                $wpdb->query($wpdb->prepare(
                    'UPDATE ' . $wpdb->prefix . self::$TBL_NAME .
                    ' SET post_id=%d WHERE page_id=%d AND spot=%s'
                    , $pageAtSpotId, $post_id, $spot
                ));
            }
            else if (!empty($existing) && empty($pageAtSpotId)) {
                $wpdb->query($wpdb->prepare(
                    'DELETE FROM ' . $wpdb->prefix . self::$TBL_NAME .
                    ' WHERE page_id=%d AND spot=%s'
                    , $post_id, $spot
                ));
            }
            else if (empty($existing) && !empty($pageAtSpotId)) {
                $wpdb->query($wpdb->prepare(
                    'INSERT INTO ' . $wpdb->prefix . self::$TBL_NAME .
                    ' (page_id, spot, post_id) VALUES (%d, %s, %d)'
                    , $post_id, $spot, $pageAtSpotId
                ));
            }
        }
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
}