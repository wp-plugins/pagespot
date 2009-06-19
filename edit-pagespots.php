<?php

/** WordPress Administration Bootstrap */
require_once('admin.php');

if ( empty($title) ) {
    $title = __('PageSpot');
}
$parent_file = 'edit-pagespots.php';

$query = array('post_type' => 'pagespot', 'orderby' => 'menu_order title', 'what_to_show' => 'posts',
    'posts_per_page' => -1, 'posts_per_archive_page' => -1, 'order' => 'asc');
wp($query);


    
require_once('admin-header.php');

?>
<div class="wrap">
<h2><?php echo wp_specialchars( $title ); ?></h2>

<?php if ($posts) { ?>

<table class="widefat page fixed" cellspacing="0">
<thead>
    <tr>
    <?php //print_column_headers('edit-pages'); ?>
    </tr>
</thead>

<tfoot>
    <tr>
    <?php //print_column_headers('edit-pages', false); ?>
    </tr>
</tfoot>

<tbody>
    <?php //page_rows($posts, $pagenum, $per_page); ?>
</tbody>
</table>

  <?php } else { ?>
<div class="clear"></div>
<p><?php _e('No pagespots found.') ?></p>
<?php
}
?>

</div><!-- /wrap -->

<?php include('admin-footer.php'); ?>