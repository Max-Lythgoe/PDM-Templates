<?php

/**
 * Custom Post Type Registration
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Post Type Class
 */
class Post_Type
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
    }

    /**
     * Register the template custom post type
     */
    public function register_post_type()
    {
        $labels = array(
            'name'                  => _x('Templates', 'Post Type General Name', 'pdm-templates'),
            'singular_name'         => _x('Template', 'Post Type Singular Name', 'pdm-templates'),
            'menu_name'             => __('Templates', 'pdm-templates'),
            'name_admin_bar'        => __('Template', 'pdm-templates'),
            'archives'              => __('Template Archives', 'pdm-templates'),
            'attributes'            => __('Template Attributes', 'pdm-templates'),
            'parent_item_colon'     => __('Parent Template:', 'pdm-templates'),
            'all_items'             => __('All Templates', 'pdm-templates'),
            'add_new_item'          => __('Add New Template', 'pdm-templates'),
            'add_new'               => __('Add New', 'pdm-templates'),
            'new_item'              => __('New Template', 'pdm-templates'),
            'edit_item'             => __('Edit Template', 'pdm-templates'),
            'update_item'           => __('Update Template', 'pdm-templates'),
            'view_item'             => __('View Template', 'pdm-templates'),
            'view_items'            => __('View Templates', 'pdm-templates'),
            'search_items'          => __('Search Template', 'pdm-templates'),
            'not_found'             => __('Not found', 'pdm-templates'),
            'not_found_in_trash'    => __('Not found in Trash', 'pdm-templates'),
            'featured_image'        => __('Featured Image', 'pdm-templates'),
            'set_featured_image'    => __('Set featured image', 'pdm-templates'),
            'remove_featured_image' => __('Remove featured image', 'pdm-templates'),
            'use_featured_image'    => __('Use as featured image', 'pdm-templates'),
            'insert_into_item'      => __('Insert into template', 'pdm-templates'),
            'uploaded_to_this_item' => __('Uploaded to this template', 'pdm-templates'),
            'items_list'            => __('Templates list', 'pdm-templates'),
            'items_list_navigation' => __('Templates list navigation', 'pdm-templates'),
            'filter_items_list'     => __('Filter templates list', 'pdm-templates'),
        );

        $args = array(
            'label'                 => __('Template', 'pdm-templates'),
            'description'           => __('Block templates for content creation', 'pdm-templates'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'custom-fields'),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 25,
            'menu_icon'             => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="currentColor" d="M128.1 64C92.8 64 64.1 92.7 64.1 128L64.1 512C64.1 547.3 92.8 576 128.1 576L274.3 576L285.2 521.5C289.5 499.8 300.2 479.9 315.8 464.3L448 332.1L448 234.6C448 217.6 441.3 201.3 429.3 189.3L322.8 82.7C310.8 70.7 294.5 64 277.6 64L128.1 64zM389.6 240L296.1 240C282.8 240 272.1 229.3 272.1 216L272.1 122.5L389.6 240zM332.3 530.9L320.4 590.5C320.2 591.4 320.1 592.4 320.1 593.4C320.1 601.4 326.6 608 334.7 608C335.7 608 336.6 607.9 337.6 607.7L397.2 595.8C409.6 593.3 421 587.2 429.9 578.3L548.8 459.4L468.8 379.4L349.9 498.3C341 507.2 334.9 518.6 332.4 531zM600.1 407.9C622.2 385.8 622.2 350 600.1 327.9C578 305.8 542.2 305.8 520.1 327.9L491.3 356.7L571.3 436.7L600.1 407.9z"/></svg>'),
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'pdm-templates',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );

        register_post_type('pdm_template', $args);
    }
}
