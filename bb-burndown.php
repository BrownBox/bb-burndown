<?php
/*
Plugin Name: BB Burndown
Description: Provides a way to burn down (or build up) hours, dollars, etc. over time
Version: 0.1
Author: Brown Box
Author URI: http://brownbox.net.au
License: GPLv2
Copyright Brown Box
*/
define('BB_BURNDOWN_TEMPLATE_DIR', dirname(__FILE__).'/templates/');

include('classes/burndown.php');
include('classes/burndowns.php');
include('classes/cpt_.php');
include('classes/meta_.php');
include('classes/tax_.php');
include('classes/tax_meta_.php');
include('functions.php');
include('widgets/burndowns.php');

new bb_burndown\cptClass('Burndown','Burndowns');
new bb_burndown\cptClass('Datapoint','Datapoints', array(
        'public' => false,
        'show_ui' => true,
));

// Filter the single_template with our custom function
add_filter('single_template', 'bb_burndown_custom_template');
function bb_burndown_custom_template($single) {
    global $post;

    /* Checks for single template by post type */
    if ($post->post_type == "burndown") {
        if (file_exists(BB_BURNDOWN_TEMPLATE_DIR.'single-burndown.php')) {
            return BB_BURNDOWN_TEMPLATE_DIR.'single-burndown.php';
        }
    }
    return $single;
}

$burndown_meta_fields = array(
        array(
                'title' => 'Graph interval (days)',
                'description' => '',
                'field_name' => 'graph_interval',
                'default' => '7',
                'type' => 'number',
        ),
        array(
                'title' => "Unit of measurement (prefix)",
                'description' => '',
                'default' => '',
                'field_name' => 'unit_of_measurement_prefix',
                'type' => 'text',
        ),
        array(
                'title' => 'Unit of measurement (suffix)',
                'description' => '',
                'field_name' => 'unit_of_measurement_suffix',
                'type' => 'text',
        ),
        array(
                'title' => "Starting Value",
                'description' => '',
                'default' => 0,
                'field_name' => 'start_value',
                'type' => 'number',
        ),
);
new bb_burndown\metaClass('Burndown Details', array('burndown'), $burndown_meta_fields);

$datapoint_meta_fields = array(
        array(
                'title' => "Build Up Amount",
                'description' => '',
                'default' => 0,
                'field_name' => 'increase_amount',
                'type' => 'number',
        ),
        array(
                'title' => 'Burndown Amount',
                'description' => '',
                'default' => 0,
                'field_name' => 'decrease_amount',
                'type' => 'number',
        ),
);
new bb_burndown\metaClass('Datapoint Details', array('datapoint'), $datapoint_meta_fields);

new bb_burndown\taxClass('Burndown as Category', 'Burndowns as Categories', array('datapoint'));

add_action('wp_enqueue_scripts', 'bb_burndown_enqueue_scripts');
add_action('admin_enqueue_scripts', 'bb_burndown_enqueue_scripts');
function bb_burndown_enqueue_scripts() {
    wp_enqueue_script('highcharts', plugins_url('/js/highcharts/js/highcharts.js', __FILE__), array('jquery'), '4.2.2');
    wp_enqueue_script('bb_burndown', plugins_url('/js/burndown.js', __FILE__), array('jquery', 'highcharts'), '4.2.2');
}

add_action('init', 'bb_burndown_add_post_status');
function bb_burndown_add_post_status(){
    register_post_status('archive', array(
            'label'                     => _x('Archived', 'post'),
            'public'                    => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>' ),
    ));
}

add_filter('title_save_pre', 'check_title');
function check_title($post_title) { // Hack to set a default post title for datapoints so they don't get lost
    if ($_POST['post_type'] == 'datapoint' and empty($post_title)) {
        $post_title = 'datapoint';
    }
    return $post_title;
}

add_action('admin_footer-post.php', 'bb_burndown_append_post_status_list');
function bb_burndown_append_post_status_list() {
    global $post;
    $complete = '';
    $label = '';
    if ($post->post_type == 'burndown') {
        if ($post->post_status == 'archive') {
            $complete = ' selected="selected"';
            $label = '<span id="post-status-display"> Archived</span>';
        }
?>
          <script>
          jQuery(document).ready(function($){
               $("select#post_status").append('<option value="archive" <?php echo $complete ?>>Archived</option>');
               $(".misc-pub-section label").append("<?php echo $label; ?>");
          });
          </script>
<?php
    }
}

add_filter('display_post_states', 'bb_burndown_display_archive_state');
function bb_burndown_display_archive_state($states) {
    global $post;
    $arg = get_query_var('post_status');
    if ($arg != 'archive') {
        if ($post->post_status == 'archive') {
            return array('Archived');
        }
    }
    return $states;
}

/*
 * Burndown as category
 */

function burndown_as_category($post_id) {
    // We don't want to do anything when autosaving a draft
    $post = get_post($post_id);
    if (wp_is_post_autosave($post_id) || $post->post_status == 'auto-draft')
        return;

    // Now let's make sure we have the right ID
    $revision = wp_is_post_revision($post_id);
    if ($revision) {
        $post_id = $revision;
        $post = get_post($post_id);
    }

    // Need to mirror the burndown hierarchy
    $parent_id = $post->post_parent;
    $parent_cat_id = 0;
    if ($parent_id > 0) {
        $parent_category = get_term_by('slug', $parent_id, 'burndownascategory');
        if ($parent_category)
            $parent_cat_id = (int)$parent_category->term_id;
    }

    $category = get_term_by('slug', $post_id, 'burndownascategory');
    if ($category) { // Update
        wp_update_term((int)$category->term_id, 'burndownascategory', array(
                'name' => $post->post_title,
                'slug' => $post_id,
                'parent'=> $parent_cat_id
        ));
    } else { // Create
        wp_insert_term($post->post_title, 'burndownascategory', array(
                'slug' => $post_id,
                'parent'=> $parent_cat_id
        ));
    }
}
add_action('save_post_burndown', 'burndown_as_category');

function refresh_burndown_hierarchy($post_id) {
    // Update child burndowns (which will in turn update their terms)
    $args = array(
            'post_parent' => $post_id,
            'post_type' => 'burndown',
    );
    $children = get_children($args);
    foreach (array_keys($children) as $child_id) {
        wp_update_post(array('ID' => $child_id));
    }

    return true;
}
add_action('before_delete_post', 'refresh_burndown_hierarchy');

function delete_burndown_as_category($post_id) {
    // If it's only a revision, ignore
    if (wp_is_post_revision($post_id))
        return true;

    $category = get_term_by('slug', $post_id, 'burndownascategory');
    if ($category) {
        // Delete term relationships
        global $wpdb;
        $wpdb->query($wpdb->prepare( 'DELETE FROM '.$wpdb->term_relationships.' WHERE term_taxonomy_id = %d', $category->term_id));

        // Delete from users
        $users = get_users();
        foreach ($users as $user) {
            $burndowns = get_user_meta($user->ID, 'burndownascategory', true);
            $burndownArr = explode(',',$burndowns);
            $idx = array_search($category->term_id, $burndownArr);
            if ($idx !== false) {
                unset($burndownArr[$idx]);
                update_user_meta($user->ID, 'burndownascategory', implode(',',$burndownArr));
            }
        }

        // Delete term
        wp_delete_term($category->term_id, 'burndownascategory');
    }

    return true;
}
add_action('deleted_post', 'delete_burndown_as_category');

/*
 * End burndown as category
 */