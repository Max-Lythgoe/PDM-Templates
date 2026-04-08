<?php

/**
 * Section Mapping for Templates
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Section Mapping Class
 */
class Section_Mapping
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        add_action('rest_api_init', array($this, 'register_meta_fields'));
    }

    /**
     * Register meta fields for REST API
     */
    public function register_meta_fields()
    {
        register_post_meta('pdm_template', '_pdm_section_mappings', array(
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_editor_assets()
    {
        $screen = get_current_screen();

        if (! $screen || $screen->post_type !== 'pdm_template') {
            return;
        }

        // Enqueue the section mapping script
        wp_enqueue_script(
            'pdm-section-mapping',
            PDM_TEMPLATES_URL . 'assets/js/section-mapping.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-plugins', 'wp-edit-post'),
            PDM_TEMPLATES_VERSION,
            true
        );

        wp_localize_script('pdm-section-mapping', 'pdmSectionMapping', array(
            'strings' => array(
                'title'            => __('Section Mapping', 'pdm-templates'),
                'description'      => __('Define sections for document import', 'pdm-templates'),
                'addSection'       => __('Add Section', 'pdm-templates'),
                'sectionName'      => __('Section Name', 'pdm-templates'),
                'blockPlaceholder' => __('Select a block to map this section to', 'pdm-templates'),
                'removeSection'    => __('Remove Section', 'pdm-templates'),
                'instructions'     => __('Add sections that match the structure of your Word documents. Each section can be mapped to specific blocks in your template.', 'pdm-templates'),
            ),
        ));
    }
}
