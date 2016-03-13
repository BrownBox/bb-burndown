<?php
namespace bb_burndown;
class taxClass {
    function __construct($singular, $plural, array $posttypes, array $args = array()) {
        $this->plural = $plural;
        $this->singular = $singular;
        $this->taxonomy = str_replace(' ', '', strtolower($singular));
        $this->args = $args;
        foreach($posttypes as $key => & $posttype) {
            $posttype = strtolower($posttype);
            add_post_type_support($posttype, 'page-attributes');
        }

        $this->posttypes = $posttypes;
        add_action('init', array($this, 'register'), 0);

        // User taxonomies are a bit special (i.e. there's no proper core support so we have to do a lot more work)
        if (in_array('user', $this->posttypes)) {
            /*
             * Add the taxonomy page in the admin
             */
            add_action('admin_menu', array($this, 'add_user_tax_admin_page'));

            /*
             * Add section to the edit user page in the admin to select terms
             */
            add_action('show_user_profile', array($this, 'user_profile_tax_section'));
            add_action('edit_user_profile', array($this, 'user_profile_tax_section'));
            add_action('user_new_form', array($this, 'user_profile_tax_section'));

            /*
             * Update the terms when the edit user page is updated
             */
            add_action('personal_options_update', array($this, 'save_user_tax_terms'));
            add_action('edit_user_profile_update', array($this, 'save_user_tax_terms'));
            add_action('user_register', array($this, 'save_user_tax_terms'));
        }
    }

    function register() {
        $labels = array(
                'name' => _x(ucfirst($this->singular) , 'taxonomy general name') ,
                'singular_name' => _x(ucfirst($this->singular) , 'taxonomy singular name') ,
                'search_items' => __('Search ' . ucfirst($this->plural)) ,
                'all_items' => __('All ' . ucfirst($this->plural)) ,
                'parent_item' => __('Parent ' . ucfirst($this->singular)) ,
                'parent_item_colon' => __('Parent ' . ucfirst($this->singular) . ':') ,
                'edit_item' => __('Edit ' . ucfirst($this->singular)) ,
                'update_item' => __('Update ' . ucfirst($this->singular)) ,
                'add_new_item' => __('Add New ' . ucfirst($this->singular)) ,
                'new_item_name' => __('New ' . ucfirst($this->singular)) ,
                'menu_name' => __(ucfirst($this->plural)) ,
        );
        $default_args = array(
                'labels' => $labels,
                'hierarchical' => true, // true = categories & false = tags
                'public' => true,
                'show_ui' => true,
                'show_tagcloud' => true,
                'show_in_nav_menus' => true,
                'show_admin_column' => true,
                'update_count_callback' => '_update_generic_term_count',
                'query_var' => $this->taxonomy,
                'rewrite' => array(
                        'slug' => $this->taxonomy,
                ),
        );
        $args = array_merge($default_args, $this->args);
        register_taxonomy($this->taxonomy, $this->posttypes, $args);
    }

    /**
     * Creates the admin page for the taxonomy under the 'Users' menu.  It works the same as any
     * other taxonomy page in the admin.  However, this is kind of hacky and is meant as a quick solution.  When
     * clicking on the menu item in the admin, WordPress' menu system thinks you're viewing something under 'Posts'
     * instead of 'Users'. We really need WP core support for this.
     */
    function add_user_tax_admin_page()
    {
        $tax = get_taxonomy(strtolower($this->singular));
        add_users_page(esc_attr($tax->labels->menu_name), esc_attr($tax->labels->menu_name), $tax->cap->manage_terms, 'edit-tags.php?taxonomy='.$tax->name);
    }

    /**
     * Adds an additional settings section on the edit user/profile page in the admin. This section allows users to
     * select checkboxes for terms from the taxonomy.
     *
     * @param object $user The user object currently being edited.
     */
    function user_profile_tax_section($user)
    {
        $tax = get_taxonomy(strtolower($this->singular));
        /* Make sure the user can assign terms of the taxonomy before proceeding. */
        if (!current_user_can($tax->cap->assign_terms)) {
            return;
        }
        /* Get the terms of the taxonomy. */
        $terms = get_terms(strtolower($this->singular), array('hide_empty' => false));
?>
<h3><?php _e(ucfirst($this->singular)); ?></h3>
<table class="form-table">
    <tr>
        <th><label for="<?php
        echo strtolower($this->singular); ?>"><?php
        _e('Select ' . ucfirst($this->singular)); ?></label></th>
        <td><?php
        /* If there are any terms, loop through them and display checkboxes. */
        if (!empty($terms)) {
            foreach($terms as $term) {
?>
            <input type="checkbox" name="<?php echo strtolower($this->singular);?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(true, is_object_in_term($user->ID, strtolower($this->singular), $term)); ?>> <label><?php echo $term->name; ?></label><br>
<?php
            }
        } else { /* If there are no terms, display a message. */
            _e('There are no '.ucfirst($this->plural).' available.');
        }
?></td>
    </tr>
</table>
<?php
    }

    /**
     * Saves the term(s) selected on the edit user/profile page in the admin. This function is triggered when the page
     * is updated.  We just grab the posted data and use wp_set_object_terms() to save it.
     *
     * @param int $user_id The ID of the user to save the terms for.
     */
    function save_user_tax_terms($user_id)
    {
        $tax = get_taxonomy(strtolower($this->singular));
        /* Make sure the current user can edit the user and assign terms before proceeding. */
        if (!current_user_can('edit_user', $user_id) && current_user_can($tax->cap->assign_terms)) {
            return false;
        }
        $term = $_POST[strtolower($this->singular)];

        /* Sets the terms for the user. */
        wp_set_object_terms($user_id, $term, strtolower($this->singular), false);
        clean_object_term_cache($user_id, strtolower($this->singular));
    }
}
