<?php

/**
 * Pattern Registration
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pattern Registration Class
 */
class Pattern_Registration
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', array($this, 'register_pattern_category'));
        add_action('init', array($this, 'register_patterns'), 20);
    }

    /**
     * Register custom pattern category
     */
    public function register_pattern_category()
    {
        register_block_pattern_category(
            'pdm-templates',
            array(
                'label' => __('PDM Templates', 'pdm-templates'),
            )
        );
    }

    /**
     * Register templates as block patterns
     */
    public function register_patterns()
    {
        // Get all templates that should be registered as patterns (including starter patterns)
        $templates = get_posts(array(
            'post_type'      => 'pdm_template',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'   => '_pdm_register_as_pattern',
                    'value' => '1',
                ),
                array(
                    'key'   => '_pdm_show_as_starter_pattern',
                    'value' => '1',
                ),
            ),
        ));

        foreach ($templates as $template) {
            $this->register_single_pattern($template);
        }
    }

    /**
     * Register a single template as a pattern
     *
     * @param \WP_Post $template Template post object.
     */
    private function register_single_pattern($template)
    {
        // Get the template content
        $content = $template->post_content;

        if (empty($content)) {
            return;
        }

        // Parse blocks to ensure valid block markup
        $blocks = parse_blocks($content);
        $content = serialize_blocks($blocks);

        // Register the pattern
        $pattern_name = 'pdm-templates/' . $template->post_name;

        $pattern_properties = array(
            'title'         => $template->post_title,
            'description'   => get_the_excerpt($template),
            'content'       => $content,
            'categories'    => array('pdm-templates'),
            'keywords'      => array('template', 'pdm'),
            'viewportWidth' => 1400,
        );

        // Make the template a starter pattern (overall or for a specific post type)
        $show_as_starter = get_post_meta($template->ID, '_pdm_show_as_starter_pattern', true);
        $starter_for = get_post_meta($template->ID, '_pdm_starter_for_post_type', true);

        if ($show_as_starter === '1') {
            // Declare core/post-content so the pattern is offered as starter content
            // when creating new content in the block editor.
            $pattern_properties['blockTypes'] = array('core/post-content');

            // Optional: restrict to a single post type. Empty = all post types.
            if ($starter_for) {
                $pattern_properties['postTypes'] = array($starter_for);
            }
        }

        register_block_pattern($pattern_name, $pattern_properties);
    }
}
