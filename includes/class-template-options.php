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
        $show_as_starter = get_post_meta($post->ID, '_pdm_show_as_starter_pattern', true);
        $starter_for_post_type = get_post_meta($post->ID, '_pdm_starter_for_post_type', true);

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
                    <input type="checkbox" name="pdm_show_as_starter_pattern" value="1" <?php checked($show_as_starter, '1'); ?> id="pdm-show-as-starter-pattern" />
                    <?php esc_html_e('Show as Starter Pattern', 'pdm-templates'); ?>
                </label>
                <br>
                <span class="description"><?php esc_html_e('Offer this template as starter content when creating new items in the block editor', 'pdm-templates'); ?></span>
            </p>

            <p class="pdm-starter-post-type-field" style="<?php echo $show_as_starter !== '1' ? 'display: none;' : ''; ?>">
                <label for="pdm_starter_for_post_type">
                    <?php esc_html_e('Starter for Post Type (optional):', 'pdm-templates'); ?>
                </label>
                <select name="pdm_starter_for_post_type" id="pdm_starter_for_post_type" style="width: 100%;">
                    <option value=""><?php esc_html_e('— All Post Types —', 'pdm-templates'); ?></option>
                    <?php foreach ($post_types as $pt_slug => $pt_object) : ?>
                        <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($starter_for_post_type, $pt_slug); ?>>
                            <?php echo esc_html($pt_object->labels->singular_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br>
                <span class="description"><?php esc_html_e('Leave as "All Post Types" to offer it everywhere.', 'pdm-templates'); ?></span>
            </p>

            <?php
            // Show a note if another template is already a starter pattern for the same post type
            if ($show_as_starter === '1' && $starter_for_post_type) {
                $other_default = $this->get_starter_pattern_for_post_type($starter_for_post_type, $post->ID);
                if ($other_default) {
                    echo '<p class="pdm-warning" style="color: #d63638; font-size: 12px;">';
                    printf(
                        /* translators: %s: template name */
                        esc_html__('Note: "%s" is also shown as a starter pattern for this post type.', 'pdm-templates'),
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
     * Get a starter pattern template for a post type
     *
     * @param string $post_type Post type slug.
     * @param int    $exclude   Post ID to exclude from search.
     * @return int|false Template post ID or false if not found.
     */
    public function get_starter_pattern_for_post_type($post_type, $exclude = 0)
    {
        $args = array(
            'post_type'      => 'pdm_template',
            'posts_per_page' => 1,
            'post__not_in'   => array($exclude),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_pdm_show_as_starter_pattern',
                    'value' => '1',
                ),
                array(
                    'key'   => '_pdm_starter_for_post_type',
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

        // Save show as starter pattern
        $show_as_starter = isset($_POST['pdm_show_as_starter_pattern']) ? '1' : '0';
        update_post_meta($post_id, '_pdm_show_as_starter_pattern', $show_as_starter);

        // Save starter pattern post type (empty = all post types)
        if ($show_as_starter === '1' && isset($_POST['pdm_starter_for_post_type'])) {
            $post_type = sanitize_text_field($_POST['pdm_starter_for_post_type']);

            if ($post_type) {
                update_post_meta($post_id, '_pdm_starter_for_post_type', $post_type);
            } else {
                delete_post_meta($post_id, '_pdm_starter_for_post_type');
            }
        } else {
            delete_post_meta($post_id, '_pdm_starter_for_post_type');
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
				$('#pdm-show-as-starter-pattern').on('change', function() {
					if ($(this).is(':checked')) {
						$('.pdm-starter-post-type-field').slideDown();
					} else {
						$('.pdm-starter-post-type-field').slideUp();
					}
				});
			});
		");
    }
}
