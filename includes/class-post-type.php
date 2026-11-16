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
            'menu_icon'             => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="white" d="M240.1 112L128.1 112C119.3 112 112.1 119.2 112.1 128L112.1 512C112.1 520.8 119.3 528 128.1 528L283.9 528L274.3 576L128.1 576C92.8 576 64.1 547.3 64.1 512L64.1 128C64.1 92.7 92.8 64 128.1 64L261.6 64C278.6 64 294.9 70.7 306.9 82.7L429.3 205.3C441.3 217.3 448 233.6 448 250.6L448 332.1L400 380.1L400 272.1L312 272.1C272.2 272.1 240 239.9 240 200.1L240 112.1zM380.2 224L288.1 131.9L288.1 200C288.1 213.3 298.8 224 312.1 224L380.2 224zM332.3 530.9C334.8 518.5 340.9 507.1 349.8 498.2L468.7 379.3L548.7 459.3L429.8 578.2C420.9 587.1 409.5 593.2 397.1 595.7L337.5 607.6C336.6 607.8 335.6 607.9 334.6 607.9C326.6 607.9 320 601.4 320 593.3C320 592.3 320.1 591.4 320.3 590.4L332.2 530.8zM600.1 407.9L571.3 436.7L491.3 356.7L520.1 327.9C542.2 305.8 578 305.8 600.1 327.9C622.2 350 622.2 385.8 600.1 407.9z"/></svg>'),
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
