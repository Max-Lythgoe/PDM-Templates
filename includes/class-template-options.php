<?php

/**
 * Template Options and Meta Boxes
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Template Options Class
 */
class Template_Options
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_pdm_template', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add default template content to new posts
        add_filter('default_content', array($this, 'insert_default_template'), 10, 2);
        add_filter('wp_insert_post_data', array($this, 'insert_template_on_autosave'), 10, 2);
    }

    /**
     * Register REST API filters for all public post types
     */
    public function register_rest_filters()
    {
        $post_types = get_post_types(array('public' => true, 'show_in_rest' => true), 'names');

        foreach ($post_types as $post_type) {
            add_filter("rest_pre_insert_{$post_type}", array($this, 'insert_template_via_rest'), 10, 2);
        }
    }

    /**
     * Add meta boxes to the template post type
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'pdm_template_options',
            __('Template Options', 'pdm-templates'),
            array($this, 'render_options_meta_box'),
            'pdm_template',
            'side',
            'high'
        );
    }

    /**
     * Render the template options meta box
     *
     * @param \WP_Post $post The post object.
     */
    public function render_options_meta_box($post)
    {
        wp_nonce_field('pdm_template_options', 'pdm_template_options_nonce');

        // Get current values
        $register_as_pattern = get_post_meta($post->ID, '_pdm_register_as_pattern', true);
        $set_as_default = get_post_meta($post->ID, '_pdm_set_as_default', true);
        $default_for_post_type = get_post_meta($post->ID, '_pdm_default_for_post_type', true);

        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);
?>
        <div class="pdm-template-options">
            <p>
                <label>
                    <input type="checkbox" name="pdm_register_as_pattern" value="1" <?php checked($register_as_pattern, '1'); ?> />
                    <?php esc_html_e('Register as Block Pattern', 'pdm-templates'); ?>
                </label>
                <br>
                <span class="description"><?php esc_html_e('Make this template available as a pattern in the block editor', 'pdm-templates'); ?></span>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="pdm_set_as_default" value="1" <?php checked($set_as_default, '1'); ?> id="pdm-set-as-default" />
                    <?php esc_html_e('Set as Default Template', 'pdm-templates'); ?>
                </label>
                <br>
                <span class="description"><?php esc_html_e('Use this as a default template for a post type', 'pdm-templates'); ?></span>
            </p>

            <p class="pdm-default-post-type-field" style="<?php echo $set_as_default !== '1' ? 'display: none;' : ''; ?>">
                <label for="pdm_default_for_post_type">
                    <?php esc_html_e('Default for Post Type:', 'pdm-templates'); ?>
                </label>
                <select name="pdm_default_for_post_type" id="pdm_default_for_post_type" style="width: 100%;">
                    <option value=""><?php esc_html_e('— Select Post Type —', 'pdm-templates'); ?></option>
                    <?php foreach ($post_types as $pt_slug => $pt_object) : ?>
                        <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($default_for_post_type, $pt_slug); ?>>
                            <?php echo esc_html($pt_object->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <?php
            // Show warning if another template is set as default for the same post type
            if ($set_as_default === '1' && $default_for_post_type) {
                $other_default = $this->get_default_template_for_post_type($default_for_post_type, $post->ID);
                if ($other_default) {
                    echo '<p class="pdm-warning" style="color: #d63638; font-size: 12px;">';
                    printf(
                        /* translators: %s: template name */
                        esc_html__('Warning: "%s" is already set as default for this post type. Saving will replace it.', 'pdm-templates'),
                        esc_html(get_the_title($other_default))
                    );
                    echo '</p>';
                }
            }
            ?>
        </div>
<?php
    }

    /**
     * Get the default template for a post type
     *
     * @param string $post_type Post type slug.
     * @param int    $exclude   Post ID to exclude from search.
     * @return int|false Template post ID or false if not found.
     */
    public function get_default_template_for_post_type($post_type, $exclude = 0)
    {
        $args = array(
            'post_type'      => 'pdm_template',
            'posts_per_page' => 1,
            'post__not_in'   => array($exclude),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_pdm_set_as_default',
                    'value' => '1',
                ),
                array(
                    'key'   => '_pdm_default_for_post_type',
                    'value' => $post_type,
                ),
            ),
        );

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            return $query->posts[0]->ID;
        }

        return false;
    }

    /**
     * Save meta box data
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function save_meta_boxes($post_id, $post)
    {
        // Check nonce
        if (! isset($_POST['pdm_template_options_nonce']) || ! wp_verify_nonce($_POST['pdm_template_options_nonce'], 'pdm_template_options')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save register as pattern
        $register_as_pattern = isset($_POST['pdm_register_as_pattern']) ? '1' : '0';
        update_post_meta($post_id, '_pdm_register_as_pattern', $register_as_pattern);

        // Save set as default
        $set_as_default = isset($_POST['pdm_set_as_default']) ? '1' : '0';
        update_post_meta($post_id, '_pdm_set_as_default', $set_as_default);

        // Save default for post type
        if ($set_as_default === '1' && isset($_POST['pdm_default_for_post_type'])) {
            $post_type = sanitize_text_field($_POST['pdm_default_for_post_type']);

            // Remove default flag from other templates for this post type
            if ($post_type) {
                $other_defaults = get_posts(array(
                    'post_type'      => 'pdm_template',
                    'posts_per_page' => -1,
                    'post__not_in'   => array($post_id),
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'   => '_pdm_set_as_default',
                            'value' => '1',
                        ),
                        array(
                            'key'   => '_pdm_default_for_post_type',
                            'value' => $post_type,
                        ),
                    ),
                ));

                foreach ($other_defaults as $other) {
                    update_post_meta($other->ID, '_pdm_set_as_default', '0');
                    delete_post_meta($other->ID, '_pdm_default_for_post_type');
                }
            }

            update_post_meta($post_id, '_pdm_default_for_post_type', $post_type);
        } else {
            delete_post_meta($post_id, '_pdm_default_for_post_type');
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts($hook)
    {
        if (! in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        global $post;
        if (! $post || $post->post_type !== 'pdm_template') {
            return;
        }

        wp_add_inline_script('jquery', "
			jQuery(document).ready(function($) {
				$('#pdm-set-as-default').on('change', function() {
					if ($(this).is(':checked')) {
						$('.pdm-default-post-type-field').slideDown();
					} else {
						$('.pdm-default-post-type-field').slideUp();
					}
				});
			});
		");
    }

    /**
     * Insert default template content for new posts
     *
     * @param string   $content Default post content.
     * @param \WP_Post $post    Post object.
     * @return string Modified content with template blocks.
     */
    public function insert_default_template($content, $post)
    {
        // Only run for new posts (no ID yet)
        if ($post->ID !== 0) {
            return $content;
        }

        // Get the post type
        $post_type = $post->post_type;

        // Find the default template for this post type
        $template_id = $this->get_default_template_for_post_type($post_type);

        if (!$template_id) {
            return $content;
        }

        // Get the template post
        $template_post = get_post($template_id);

        if (!$template_post || $template_post->post_status !== 'publish') {
            return $content;
        }

        // Return the template's content (which contains the block markup)
        return $template_post->post_content;
    }

    /**
     * Insert template content via REST API (for block editor)
     *
     * @param \stdClass        $prepared_post An object representing a single post prepared for insertion.
     * @param \WP_REST_Request $request       Request object.
     * @return \stdClass Modified post object.
     */
    public function insert_template_via_rest($prepared_post, $request)
    {
        // Skip if there's already substantial content (not just empty paragraph)
        $content = isset($prepared_post->post_content) ? trim($prepared_post->post_content) : '';
        if (!empty($content) && $content !== '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->') {
            return $prepared_post;
        }

        // Get post type from the prepared post
        $post_type = $prepared_post->post_type;

        // Find the default template for this post type
        $template_id = $this->get_default_template_for_post_type($post_type);

        if (!$template_id) {
            return $prepared_post;
        }

        // Get the template post
        $template_post = get_post($template_id);

        if (!$template_post || $template_post->post_status !== 'publish') {
            return $prepared_post;
        }

        // Set the template's content
        $prepared_post->post_content = $template_post->post_content;

        return $prepared_post;
    }

    /**
     * Insert template content when auto-draft is created
     *
     * @param array $data    Post data array.
     * @param array $postarr Post array.
     * @return array Modified post data.
     */
    public function insert_template_on_autosave($data, $postarr)
    {
        // Only apply to auto-drafts with empty or minimal content
        if ($data['post_status'] !== 'auto-draft') {
            return $data;
        }

        $content = trim($data['post_content']);
        if (!empty($content) && $content !== '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->') {
            return $data;
        }

        // Find the default template for this post type
        $template_id = $this->get_default_template_for_post_type($data['post_type']);

        if (!$template_id) {
            return $data;
        }

        // Get the template post
        $template_post = get_post($template_id);

        if (!$template_post || $template_post->post_status !== 'publish') {
            return $data;
        }

        // Set the template's content
        $data['post_content'] = $template_post->post_content;

        return $data;
    }
}
