<?php

/**
 * Bulk Upload Handler
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Bulk Upload Class
 */
class Bulk_Upload
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pdm_upload_docx', array($this, 'handle_upload'));
        add_action('wp_ajax_pdm_process_docx', array($this, 'process_documents'));
        add_action('wp_ajax_pdm_get_image_requirements', array($this, 'get_image_requirements'));
    }

    /**
     * Add submenu page
     */
    public function add_menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=pdm_template',
            __('Bulk Upload', 'pdm-templates'),
            __('Bulk Upload', 'pdm-templates'),
            'edit_posts',
            'pdm-bulk-upload',
            array($this, 'render_page')
        );
    }

    /**
     * Render the bulk upload page
     */
    public function render_page()
    {
        // Get available templates
        $templates = get_posts(array(
            'post_type'      => 'pdm_template',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);
?>
        <div class="wrap">
            <h1><?php esc_html_e('Bulk Upload Word Documents', 'pdm-templates'); ?></h1>

            <div class="pdm-upload-container">
                <div class="pdm-upload-settings card">
                    <h2><?php esc_html_e('Upload Settings', 'pdm-templates'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="pdm-template-select"><?php esc_html_e('Select Template', 'pdm-templates'); ?></label>
                            </th>
                            <td>
                                <select id="pdm-template-select" name="template_id" required>
                                    <option value=""><?php esc_html_e('— Select Template —', 'pdm-templates'); ?></option>
                                    <?php foreach ($templates as $template) : ?>
                                        <option value="<?php echo esc_attr($template->ID); ?>">
                                            <?php echo esc_html($template->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose a template to use for the imported documents', 'pdm-templates'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="pdm-post-type-select"><?php esc_html_e('Post Type', 'pdm-templates'); ?></label>
                            </th>
                            <td>
                                <select id="pdm-post-type-select" name="post_type" required>
                                    <option value=""><?php esc_html_e('— Select Post Type —', 'pdm-templates'); ?></option>
                                    <?php foreach ($post_types as $pt_slug => $pt_object) : ?>
                                        <option value="<?php echo esc_attr($pt_slug); ?>">
                                            <?php echo esc_html($pt_object->labels->singular_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Select the post type for created pages', 'pdm-templates'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="pdm-post-status-select"><?php esc_html_e('Post Status', 'pdm-templates'); ?></label>
                            </th>
                            <td>
                                <select id="pdm-post-status-select" name="post_status">
                                    <option value="draft" selected><?php esc_html_e('Draft', 'pdm-templates'); ?></option>
                                    <option value="publish"><?php esc_html_e('Publish', 'pdm-templates'); ?></option>
                                    <option value="pending"><?php esc_html_e('Pending Review', 'pdm-templates'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Set the status for created pages', 'pdm-templates'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="pdm-file-upload card">
                    <h2><?php esc_html_e('Upload Documents', 'pdm-templates'); ?></h2>

                    <input type="file" id="pdm-file-input" multiple accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display: none;">

                    <div id="pdm-drop-zone" class="pdm-drop-zone">
                        <p class="pdm-drop-instructions">
                            <span class="dashicons dashicons-upload"></span><br>
                            <?php esc_html_e('Drop Word documents here or click to upload', 'pdm-templates'); ?>
                        </p>
                    </div>

                    <div id="pdm-file-list" class="pdm-file-list"></div>

                    <div class="pdm-actions">
                        <button type="button" id="pdm-process-btn" class="button button-primary button-large" disabled>
                            <?php esc_html_e('Process Documents', 'pdm-templates'); ?>
                        </button>
                        <button type="button" id="pdm-clear-btn" class="button button-secondary">
                            <?php esc_html_e('Clear All', 'pdm-templates'); ?>
                        </button>
                    </div>
                </div>

                <div id="pdm-progress-container" class="pdm-progress-container card" style="display: none;">
                    <h2><?php esc_html_e('Processing Progress', 'pdm-templates'); ?></h2>
                    <div class="pdm-progress">
                        <div class="pdm-progress-bar">
                            <div class="pdm-progress-fill" style="width: 0%;"></div>
                        </div>
                        <p class="pdm-progress-text">0 / 0</p>
                    </div>
                    <div id="pdm-results-list" class="pdm-results-list"></div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Enqueue scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'pdm_template_page_pdm-bulk-upload') {
            return;
        }

        // Enqueue WordPress media library
        wp_enqueue_media();

        // Enqueue Mammoth.js from CDN
        wp_enqueue_script(
            'mammoth',
            'https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js',
            array(),
            '1.6.0',
            true
        );

        // Enqueue image selector
        wp_enqueue_script(
            'pdm-image-selector',
            PDM_TEMPLATES_URL . 'assets/js/image-selector.js',
            array('jquery', 'wp-util', 'media-editor', 'media-views'),
            PDM_TEMPLATES_VERSION,
            true
        );

        // Enqueue custom JavaScript
        wp_enqueue_script(
            'pdm-bulk-upload',
            PDM_TEMPLATES_URL . 'assets/js/bulk-upload.js',
            array('jquery', 'mammoth', 'pdm-image-selector'),
            PDM_TEMPLATES_VERSION,
            true
        );

        // Enqueue custom CSS
        wp_enqueue_style(
            'pdm-bulk-upload',
            PDM_TEMPLATES_URL . 'assets/css/bulk-upload.css',
            array(),
            PDM_TEMPLATES_VERSION
        );

        // Localize script
        wp_localize_script('pdm-bulk-upload', 'pdmBulkUpload', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pdm_bulk_upload'),
            'strings' => array(
                'selectTemplate' => __('Please select a template', 'pdm-templates'),
                'selectPostType' => __('Please select a post type', 'pdm-templates'),
                'uploading'      => __('Uploading...', 'pdm-templates'),
                'processing'     => __('Processing...', 'pdm-templates'),
                'complete'       => __('Complete!', 'pdm-templates'),
                'error'          => __('Error', 'pdm-templates'),
                'success'        => __('Success', 'pdm-templates'),
            ),
        ));
    }

    /**
     * Handle file upload via AJAX
     */
    public function handle_upload()
    {
        check_ajax_referer('pdm_bulk_upload', 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'pdm-templates')));
        }

        if (! isset($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'pdm-templates')));
        }

        $file = $_FILES['file'];

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'docx') {
            wp_send_json_error(array('message' => __('Invalid file type. Only .docx files are allowed.', 'pdm-templates')));
        }

        // Upload file
        $upload = wp_handle_upload($file, array('test_form' => false));

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        wp_send_json_success(array(
            'file_path' => $upload['file'],
            'file_url'  => $upload['url'],
        ));
    }

    /**
     * Process uploaded documents
     */
    public function process_documents()
    {
        check_ajax_referer('pdm_bulk_upload', 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'pdm-templates')));
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
        $document = isset($_POST['document']) ? json_decode(stripslashes($_POST['document']), true) : array();
        $image_mapping = isset($_POST['image_mapping']) ? json_decode(stripslashes($_POST['image_mapping']), true) : array();

        if (! $template_id || ! get_post($template_id)) {
            wp_send_json_error(array('message' => __('Invalid template', 'pdm-templates')));
        }

        if (empty($document)) {
            wp_send_json_error(array('message' => __('Invalid document data', 'pdm-templates')));
        }

        // Use Page_Generator to create the page
        $generator = new Page_Generator();
        $result = $generator->generate_page($template_id, $document, $post_type, $post_status, $image_mapping);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'post_id'    => $result,
            'edit_url'   => get_edit_post_link($result, 'raw'),
            'post_title' => get_the_title($result),
        ));
    }

    /**
     * Get image requirements for a template
     */
    public function get_image_requirements()
    {
        check_ajax_referer('pdm_bulk_upload', 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'pdm-templates')));
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if (! $template_id || ! get_post($template_id)) {
            wp_send_json_error(array('message' => __('Invalid template', 'pdm-templates')));
        }

        $image_replacer = new Image_Replacer();
        $requirements = $image_replacer->detect_image_requirements($template_id);

        wp_send_json_success(array(
            'requirements' => $requirements,
            'count'        => count($requirements),
        ));
    }
}
