<?php

/**
 * AI Page Generator
 *
 * Generates WordPress pages using Gemini AI based on selected templates.
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * AI Page Generator Class
 */
class AI_Page_Generator
{

    /**
     * Gemini model to use (flash-lite for cost efficiency)
     */
    const GEMINI_MODEL = 'gemini-2.5-flash-lite';

    /**
     * PDM knowledge base categories used for page content.
     *
     * STYLE first so brand voice is never the block trimmed by the character
     * cap, then CORE for who the business is and where it works, CONVERSION for
     * the audience, objections and FAQ-worthy questions, and OFFERINGS for the
     * services, brands, warranties and pricing the page should describe.
     * AUTHORITY is left out: credentials and awards carry little weight in a
     * generated page body and it is one of the larger blocks.
     */
    const KB_CATEGORIES = array('STYLE', 'CORE', 'CONVERSION', 'OFFERINGS');

    /**
     * Ceiling for the knowledge base block, so a very verbose questionnaire
     * cannot bloat every request (pages already carry site + reference context).
     */
    const KB_MAX_CHARS = 16000;

    /**
     * Request-scoped cache for the knowledge base block.
     *
     * @var string|null
     */
    private $kb_context_cache = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pdm_ai_generate_page', array($this, 'handle_generate_page'));
        add_action('wp_ajax_pdm_ai_get_template_info', array($this, 'handle_get_template_info'));
        add_action('wp_ajax_pdm_ai_search_pages', array($this, 'handle_search_pages'));
    }

    /**
     * Add submenu page under Templates
     */
    public function add_menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=pdm_template',
            __('Generate Page', 'pdm-templates'),
            __('Generate Page', 'pdm-templates'),
            'edit_posts',
            'pdm-ai-generate',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts and styles for the generate page screen
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'pdm_template_page_pdm-ai-generate') {
            return;
        }

        wp_enqueue_style(
            'pdm-ai-page-generator',
            PDM_TEMPLATES_URL . 'assets/css/ai-page-generator.css',
            array(),
            filemtime(PDM_TEMPLATES_PATH . 'assets/css/ai-page-generator.css')
        );

        // Needed for the WordPress media library picker
        wp_enqueue_media();

        wp_enqueue_script(
            'pdm-ai-page-generator',
            PDM_TEMPLATES_URL . 'assets/js/ai-page-generator.js',
            array('jquery'),
            filemtime(PDM_TEMPLATES_PATH . 'assets/js/ai-page-generator.js'),
            true
        );

        wp_localize_script('pdm-ai-page-generator', 'pdmAIGenerator', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pdm_ai_generate'),
            'strings' => array(
                'generating'       => __('Generating content with AI…', 'pdm-templates'),
                'creating'         => __('Creating your page…', 'pdm-templates'),
                'success'          => __('Page generated successfully!', 'pdm-templates'),
                'error'            => __('An error occurred. Please try again.', 'pdm-templates'),
                'viewPage'         => __('View Page', 'pdm-templates'),
                'editPage'         => __('Edit Page', 'pdm-templates'),
                'generateAnother'  => __('Generate Another Page', 'pdm-templates'),
                'templateInfo'     => __('Template info loaded', 'pdm-templates'),
                'selectImage'      => __('Select Image', 'pdm-templates'),
                'changeImage'      => __('Change Image', 'pdm-templates'),
                'removeImage'      => __('Remove', 'pdm-templates'),
                'mediaTitle'       => __('Select an Image for this Slot', 'pdm-templates'),
                'mediaButton'      => __('Use this Image', 'pdm-templates'),
                'noImages'         => __('This template has no image slots.', 'pdm-templates'),
            ),
            'tones' => array(
                '1' => array('slug' => 'friendly',     'label' => 'Friendly',     'description' => 'Warm and conversational.'),
                '2' => array('slug' => 'professional', 'label' => 'Professional', 'description' => 'Formal and authoritative.'),
                '3' => array('slug' => 'persuasive',   'label' => 'Persuasive',   'description' => 'Bold and benefit-driven.'),
                '4' => array('slug' => 'creative',     'label' => 'Creative',     'description' => 'Playful and distinctive.'),
                '5' => array('slug' => 'empathetic',   'label' => 'Empathetic',   'description' => 'Compassionate and reassuring.'),
            ),
        ));
    }

    /**
     * Render the Generate Page admin screen
     */
    public function render_page()
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'pdm-templates'));
        }

        $templates = get_posts(array(
            'post_type'      => 'pdm_template',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $post_types = get_post_types(array('public' => true), 'objects');
        unset($post_types['attachment']);

        $api_key_configured = $this->get_api_key() !== null;
?>
        <div class="wrap pdm-ai-generator-wrap">
            <div class="pdm-ai-page-header">
                <h1><?php esc_html_e('Generate Page with AI', 'pdm-templates'); ?></h1>
                <p class="pdm-ai-page-subtitle">
                    <?php esc_html_e('Describe the content and Gemini will write it into your chosen template.', 'pdm-templates'); ?>
                </p>
            </div>

            <?php if (! $api_key_configured) : ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: link to connectors settings */
                            esc_html__('Google API key not found. Please configure your Google connector in %s or define the GOOGLE_API_KEY constant.', 'pdm-templates'),
                            '<a href="' . esc_url(admin_url('options-connectors.php')) . '">' . esc_html__('Settings → Connectors', 'pdm-templates') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($templates)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: link to add new template */
                            esc_html__('No published templates found. %s first.', 'pdm-templates'),
                            '<a href="' . esc_url(admin_url('post-new.php?post_type=pdm_template')) . '">' . esc_html__('Create a template', 'pdm-templates') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="pdm-ai-generator-layout">

                <div class="pdm-ai-generator-form-wrap card">
                    <form id="pdm-ai-generator-form" method="post">
                        <?php wp_nonce_field('pdm_ai_generate', 'pdm_ai_nonce'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-page-name">
                                        <?php esc_html_e('Page Name', 'pdm-templates'); ?>
                                        <span class="required" aria-hidden="true">*</span>
                                    </label>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="pdm-ai-page-name"
                                        name="page_name"
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e('e.g. Our Services', 'pdm-templates'); ?>"
                                        required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-description">
                                        <?php esc_html_e('Content Description', 'pdm-templates'); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea
                                        id="pdm-ai-description"
                                        name="description"
                                        class="large-text"
                                        rows="5"
                                        placeholder="<?php esc_attr_e('Describe the content you want the AI to generate. Include your business name, key messages, tone, and any specific details…', 'pdm-templates'); ?>"></textarea>
                                    <p class="description">
                                        <?php esc_html_e('Optional — more detail gives better output.', 'pdm-templates'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-tone">
                                        <?php esc_html_e('Tone', 'pdm-templates'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="pdm-ai-tone-wrap">
                                        <input type="range" id="pdm-ai-tone" name="tone" min="1" max="5" step="1" value="1" class="pdm-ai-tone-slider">
                                        <div class="pdm-ai-tone-labels" aria-hidden="true">
                                            <span><?php esc_html_e('Friendly', 'pdm-templates'); ?></span>
                                            <span><?php esc_html_e('Professional', 'pdm-templates'); ?></span>
                                            <span><?php esc_html_e('Persuasive', 'pdm-templates'); ?></span>
                                            <span><?php esc_html_e('Creative', 'pdm-templates'); ?></span>
                                            <span><?php esc_html_e('Empathetic', 'pdm-templates'); ?></span>
                                        </div>
                                        <div id="pdm-ai-tone-info" class="pdm-ai-tone-info">
                                            <strong id="pdm-ai-tone-name">Friendly</strong><span id="pdm-ai-tone-desc"> — Warm and conversational.</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-template">
                                        <?php esc_html_e('Template', 'pdm-templates'); ?>
                                        <span class="required" aria-hidden="true">*</span>
                                    </label>
                                </th>
                                <td>
                                    <select id="pdm-ai-template" name="template_id" required <?php disabled(empty($templates)); ?>>
                                        <option value=""><?php esc_html_e('— Select a Template —', 'pdm-templates'); ?></option>
                                        <?php foreach ($templates as $template) : ?>
                                            <option value="<?php echo esc_attr($template->ID); ?>">
                                                <?php echo esc_html($template->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" id="pdm-ai-template-info">
                                        <?php esc_html_e('Layout for the generated content.', 'pdm-templates'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-post-type">
                                        <?php esc_html_e('Post Type', 'pdm-templates'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="pdm-ai-post-type" name="post_type">
                                        <?php foreach ($post_types as $pt_slug => $pt_object) : ?>
                                            <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($pt_slug, 'page'); ?>>
                                                <?php echo esc_html($pt_object->labels->singular_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-post-status">
                                        <?php esc_html_e('Publish Status', 'pdm-templates'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="pdm-ai-post-status" name="post_status">
                                        <option value="draft" selected><?php esc_html_e('Draft', 'pdm-templates'); ?></option>
                                        <option value="publish"><?php esc_html_e('Published', 'pdm-templates'); ?></option>
                                        <option value="pending"><?php esc_html_e('Pending Review', 'pdm-templates'); ?></option>
                                        <option value="private"><?php esc_html_e('Private', 'pdm-templates'); ?></option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="pdm-ai-ref-search">
                                        <?php esc_html_e('Reference Pages', 'pdm-templates'); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="pdm-ai-ref-wrap">
                                        <input
                                            type="text"
                                            id="pdm-ai-ref-search"
                                            class="regular-text pdm-ai-ref-search-input"
                                            placeholder="<?php esc_attr_e('Search pages…', 'pdm-templates'); ?>"
                                            autocomplete="off">
                                        <div id="pdm-ai-ref-results" class="pdm-ai-ref-results" style="display:none;"></div>
                                        <div id="pdm-ai-ref-chips" class="pdm-ai-ref-chips"></div>
                                        <input type="hidden" id="pdm-ai-ref-ids" name="ref_page_ids" value="">
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Optional — up to 3 pages the AI reads for style and detail.', 'pdm-templates'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Landing Page?', 'pdm-templates'); ?>
                                </th>
                                <td>
                                    <label class="pdm-ai-check">
                                        <input type="checkbox" id="pdm-ai-landing-page" name="is_landing_page" value="1">
                                        <span><?php esc_html_e('This is a landing page', 'pdm-templates'); ?></span>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Strips outbound links and phone numbers.', 'pdm-templates'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- Image slots — populated by JS when a template is selected -->
                        <div id="pdm-ai-image-section" style="display:none;">
                            <hr class="pdm-ai-divider">
                            <h2 class="pdm-ai-section-title">
                                <?php esc_html_e('Template Images', 'pdm-templates'); ?>
                            </h2>
                            <label class="pdm-ai-check">
                                <input type="checkbox" id="pdm-ai-auto-images" name="auto_images" value="1" checked>
                                <span><?php esc_html_e('Find relevant images automatically', 'pdm-templates'); ?></span>
                            </label>
                            <div id="pdm-ai-manual-slots" style="display:none;">
                                <div id="pdm-ai-image-slots"></div>
                            </div>
                            <!-- Hidden field carrying the JSON image mapping -->
                            <input type="hidden" id="pdm-ai-image-mapping" name="image_mapping" value="{}">
                        </div>

                        <div class="pdm-ai-generator-submit">
                            <button
                                type="submit"
                                id="pdm-ai-generate-btn"
                                class="button button-primary button-hero"
                                <?php disabled(! $api_key_configured || empty($templates)); ?>>
                                <?php esc_html_e('Generate Page', 'pdm-templates'); ?>
                            </button>
                        </div>
                    </form>

                    <!-- Progress & Result area -->
                    <div id="pdm-ai-progress" class="pdm-ai-progress" style="display:none;">
                        <div class="pdm-ai-progress-inner">
                            <div class="pdm-brand-loader" aria-hidden="true">
                                <!-- Arrow 1 (shortest - left) -->
                                <svg class="pdm-brand-loader-arrow pdm-brand-loader-arrow-1" viewBox="0 0 54 74" focusable="false">
                                    <polygon points="27,0 54,26 42,26 42,74 12,74 12,26 0,26" fill="#e96b26" />
                                </svg>
                                <!-- Arrow 2 (medium - center) -->
                                <svg class="pdm-brand-loader-arrow pdm-brand-loader-arrow-2" viewBox="0 0 54 96" focusable="false">
                                    <polygon points="27,0 54,26 42,26 42,96 12,96 12,26 0,26" fill="#e96b26" />
                                </svg>
                                <!-- Arrow 3 (tallest - right) -->
                                <svg class="pdm-brand-loader-arrow pdm-brand-loader-arrow-3" viewBox="0 0 54 118" focusable="false">
                                    <polygon points="27,0 54,26 42,26 42,118 12,118 12,26 0,26" fill="#e96b26" />
                                </svg>
                            </div>
                            <span class="pdm-ai-progress-label"></span>
                        </div>
                    </div>

                    <div id="pdm-ai-result" class="pdm-ai-result" style="display:none;"></div>
                </div>

                <div class="pdm-ai-generator-sidebar">
                    <?php
                    $company_ctx = $this->get_company_context();
                    if (! empty($company_ctx['company_name']) || ! empty($company_ctx['locations'])) :
                    ?>
                        <div class="card pdm-ai-company-context">
                            <h2><?php esc_html_e('Company Context', 'pdm-templates'); ?></h2>
                            <p class="description"><?php esc_html_e('Sent with every prompt automatically.', 'pdm-templates'); ?></p>
                            <?php if (! empty($company_ctx['company_name'])) : ?>
                                <div class="pdm-ai-context-row">
                                    <span class="pdm-ai-context-label"><?php esc_html_e('Name', 'pdm-templates'); ?></span>
                                    <span class="pdm-ai-context-value"><?php echo esc_html($company_ctx['company_name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($company_ctx['locations'] as $i => $loc) : ?>
                                <div class="pdm-ai-context-row">
                                    <?php if (count($company_ctx['locations']) > 1) : ?>
                                        <span class="pdm-ai-context-label"><?php printf(esc_html__('Location %d', 'pdm-templates'), $i + 1); ?></span>
                                    <?php endif; ?>
                                    <span class="pdm-ai-context-value">
                                        <?php
                                        $location_lines = array();
                                        foreach (array('name', 'phone', 'email', 'address') as $loc_field) {
                                            if (! empty($loc[$loc_field])) {
                                                $location_lines[] = esc_html($loc[$loc_field]);
                                            }
                                        }
                                        echo implode('<br>', $location_lines); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
                                        ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>

                            <div class="pdm-ai-context-row pdm-ai-context-kb">
                                <?php if (! empty($company_ctx['knowledge_base'])) : ?>
                                    <span class="pdm-ai-context-label"><?php esc_html_e('Knowledge base', 'pdm-templates'); ?></span>
                                    <span class="pdm-ai-context-value pdm-ai-kb-pills">
                                        <?php foreach (self::KB_CATEGORIES as $kb_category) : ?>
                                            <span class="pdm-ai-kb-pill"><?php echo esc_html($kb_category); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php else : ?>
                                    <p class="description">
                                        <?php
                                        printf(
                                            /* translators: %s: admin URL */
                                            esc_html__('No knowledge base answers yet — add the client questionnaire in %s.', 'pdm-templates'),
                                            '<a href="' . esc_url(admin_url('tools.php?page=pdm-plugin-manager&tab=knowledge-base')) . '">' . esc_html__('Tools → PDM Plugins → Knowledge Base', 'pdm-templates') . '</a>'
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card pdm-ai-model-info">
                        <h2><?php esc_html_e('AI Model', 'pdm-templates'); ?></h2>
                        <p class="pdm-ai-model-name"><?php echo esc_html(self::GEMINI_MODEL); ?></p>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to connectors settings */
                                esc_html__('Key from %s.', 'pdm-templates'),
                                '<a href="' . esc_url(admin_url('options-connectors.php')) . '">' . esc_html__('WordPress Connectors', 'pdm-templates') . '</a>'
                            );
                            ?>
                        </p>
                        <p>
                            <span class="pdm-ai-key-status <?php echo $api_key_configured ? 'configured' : 'not-configured'; ?>">
                                <?php if ($api_key_configured) : ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('API key configured', 'pdm-templates'); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php esc_html_e('API key not found', 'pdm-templates'); ?>
                                <?php endif; ?>
                            </span>
                        </p>
                    </div>
                </div>

            </div><!-- .pdm-ai-generator-layout -->
        </div><!-- .wrap -->
<?php
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * Handle the generate page AJAX request
     */
    public function handle_generate_page()
    {
        if (! check_ajax_referer('pdm_ai_generate', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'pdm-templates')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'pdm-templates')));
        }

        // Sanitize inputs
        $page_name    = sanitize_text_field(wp_unslash($_POST['page_name'] ?? ''));
        $description  = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $template_id  = absint($_POST['template_id'] ?? 0);
        $post_type    = sanitize_key($_POST['post_type'] ?? 'page');
        $post_status  = sanitize_key($_POST['post_status'] ?? 'draft');
        $tone         = sanitize_key($_POST['tone'] ?? 'friendly');
        $is_landing   = ! empty($_POST['is_landing_page']);
        $ref_ids_raw  = isset($_POST['ref_page_ids']) ? sanitize_text_field(wp_unslash($_POST['ref_page_ids'])) : '';
        $ref_page_ids = array_filter(array_slice(array_map('absint', explode(',', $ref_ids_raw)), 0, 3));
        $image_mapping_raw = isset($_POST['image_mapping']) ? wp_unslash($_POST['image_mapping']) : '{}';
        $auto_images  = ! empty($_POST['auto_images']);

        // Validate required fields
        if (empty($page_name)) {
            wp_send_json_error(array('message' => __('Page name is required.', 'pdm-templates')));
        }

        if (! $template_id) {
            wp_send_json_error(array('message' => __('Please select a template.', 'pdm-templates')));
        }

        // Validate post type against allowed public types
        $allowed_post_types = array_keys(get_post_types(array('public' => true)));
        if (! in_array($post_type, $allowed_post_types, true)) {
            wp_send_json_error(array('message' => __('Invalid post type selected.', 'pdm-templates')));
        }

        // Validate post status
        $allowed_statuses = array('draft', 'publish', 'pending', 'private');
        if (! in_array($post_status, $allowed_statuses, true)) {
            wp_send_json_error(array('message' => __('Invalid post status selected.', 'pdm-templates')));
        }

        // Validate tone
        $allowed_tones = array('friendly', 'professional', 'persuasive', 'creative', 'empathetic');
        if (! in_array($tone, $allowed_tones, true)) {
            $tone = 'friendly';
        }

        // Verify template exists
        $template = get_post($template_id);
        if (! $template || $template->post_type !== 'pdm_template') {
            wp_send_json_error(array('message' => __('Template not found.', 'pdm-templates')));
        }

        // Get API key
        $api_key = $this->get_api_key();
        if (! $api_key) {
            wp_send_json_error(array(
                'message' => __('Google API key is not configured. Please add your Google connector in Settings → Connectors.', 'pdm-templates'),
            ));
        }

        // Validate and sanitize image mapping
        $image_mapping = $this->sanitize_image_mapping($image_mapping_raw);
        if (is_wp_error($image_mapping)) {
            wp_send_json_error(array('message' => $image_mapping->get_error_message()));
        }

        // If auto-images is enabled and no manual mapping was provided, find images automatically
        if ($auto_images && empty($image_mapping)) {
            $image_replacer     = new Image_Replacer();
            $image_requirements = $image_replacer->detect_image_requirements($template_id);
            if (! empty($image_requirements)) {
                $image_mapping = $this->find_matching_images($page_name, $description, $image_requirements);
            }
        }

        // Analyse the template to understand its section structure
        $template_structure = $this->analyze_template_structure($template);

        // Build the AI prompt (includes auto-detected company info)
        $prompt = $this->build_prompt($page_name, $description, $template_structure, $post_type, $tone, $is_landing, $ref_page_ids);

        // Call Gemini
        $ai_response = $this->call_gemini_api($api_key, $prompt);
        if (is_wp_error($ai_response)) {
            wp_send_json_error(array('message' => $ai_response->get_error_message()));
        }

        // Parse AI response into the document format Page_Generator understands
        $document = $this->parse_ai_response($ai_response, $page_name);
        if (is_wp_error($document)) {
            wp_send_json_error(array('message' => $document->get_error_message()));
        }

        // Use the existing Page_Generator to create the post (with optional image mapping)
        $page_generator = new Page_Generator();
        $post_id        = $page_generator->generate_page($template_id, $document, $post_type, $post_status, $image_mapping);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        // Tag the post as AI-generated
        update_post_meta($post_id, '_pdm_ai_generated', true);
        update_post_meta($post_id, '_pdm_ai_description', $description);
        update_post_meta($post_id, '_pdm_ai_model', self::GEMINI_MODEL);

        wp_send_json_success(array(
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
            'message'  => __('Page generated successfully!', 'pdm-templates'),
        ));
    }

    /**
     * Handle get template info AJAX request (returns section count for UI feedback)
     */
    public function handle_get_template_info()
    {
        if (! check_ajax_referer('pdm_ai_generate', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'pdm-templates')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'pdm-templates')));
        }

        $template_id = absint($_POST['template_id'] ?? 0);
        if (! $template_id) {
            wp_send_json_error(array('message' => __('Invalid template ID.', 'pdm-templates')));
        }

        $template = get_post($template_id);
        if (! $template || $template->post_type !== 'pdm_template') {
            wp_send_json_error(array('message' => __('Template not found.', 'pdm-templates')));
        }

        $structure = $this->analyze_template_structure($template);

        // Also detect image slots so the form can render them
        $image_replacer   = new Image_Replacer();
        $image_requirements = $image_replacer->detect_image_requirements($template_id);

        wp_send_json_success(array(
            'section_count'         => count($structure['sections']),
            'content_section_count' => count(array_filter($structure['sections'], function ($s) {
                return ! $s['is_hero'];
            })),
            'has_hero'              => $structure['has_hero'],
            'description'           => $this->describe_template_structure($structure),
            'image_requirements'    => $image_requirements,
            'image_count'           => count($image_requirements),
        ));
    }

    // =========================================================================
    // Page Search AJAX
    // =========================================================================

    /**
     * AJAX handler: search published posts/pages by keyword for the reference picker.
     */
    public function handle_search_pages()
    {
        if (! check_ajax_referer('pdm_ai_generate', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'pdm-templates')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'pdm-templates')));
        }

        $query = sanitize_text_field(wp_unslash($_POST['query'] ?? ''));
        if (strlen($query) < 2) {
            wp_send_json_success(array());
        }

        $excluded_types = array('attachment', 'pdm_template', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face');
        $post_types     = array_diff(
            array_keys(get_post_types(array('public' => true))),
            $excluded_types
        );

        $results = get_posts(array(
            'post_type'      => array_values($post_types),
            'post_status'    => 'publish',
            's'              => $query,
            'numberposts'    => 10,
            'orderby'        => 'relevance',
        ));

        $data = array();
        foreach ($results as $post) {
            $pt_obj  = get_post_type_object($post->post_type);
            $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $post->post_type;
            $data[] = array(
                'id'    => $post->ID,
                'title' => get_the_title($post->ID),
                'type'  => $pt_label,
            );
        }

        wp_send_json_success($data);
    }

    // =========================================================================
    // Image Mapping Sanitization
    // =========================================================================

    /**
     * Sanitize and validate the image_mapping JSON string from POST.
     *
     * @param string $raw Raw JSON string.
     * @return array|\WP_Error Validated mapping array (path_key => attachment_id) or WP_Error.
     */
    private function sanitize_image_mapping($raw)
    {
        if (empty($raw) || $raw === '{}') {
            return array();
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return new \WP_Error('invalid_image_mapping', __('Invalid image mapping data.', 'pdm-templates'));
        }

        $sanitized = array();
        foreach ($decoded as $path_key => $attachment_id) {
            // Path key must be a string of digits and dots (e.g. "0", "1.2")
            if (! preg_match('/^[0-9.]+$/', (string) $path_key)) {
                continue;
            }
            $att_id = absint($attachment_id);
            if (! $att_id) {
                continue;
            }
            // Verify the attachment actually exists
            if (get_post_type($att_id) !== 'attachment') {
                continue;
            }
            $sanitized[$path_key] = $att_id;
        }

        return $sanitized;
    }

    // =========================================================================
    // Site Pages Context
    // =========================================================================

    /**
     * Return an array of published pages and posts that can be referenced as
     * links in AI-generated content.
     *
     * Only pages and posts are listed: internal links must point at real
     * editorial content, not directory/CPT data such as distributors.
     *
     * @return array[] Array of ['title' => string, 'url' => string].
     */
    private function get_site_pages()
    {
        $post_types = array_values(array_intersect(
            array('page', 'post'),
            array_keys(get_post_types(array('public' => true)))
        ));

        if (empty($post_types)) {
            return array();
        }

        // Pages first — they make the strongest internal link targets.
        $ordered_types  = array_merge(array_intersect(array('page'), $post_types), array_diff($post_types, array('page')));
        $per_type_limit = 15;
        $max_entries    = 30;

        $pages = array();
        foreach ($ordered_types as $type) {
            if (count($pages) >= $max_entries) {
                break;
            }

            $post_ids = get_posts(array(
                'post_type'        => $type,
                'post_status'      => 'publish',
                'numberposts'      => $per_type_limit,
                'orderby'          => 'title',
                'order'            => 'ASC',
                'fields'           => 'ids',
                'no_found_rows'    => true,
                'suppress_filters' => false,
            ));

            foreach ($post_ids as $post_id) {
                $title = get_the_title($post_id);
                $url   = get_permalink($post_id);
                if ($title && $url) {
                    $pages[] = array(
                        'title'     => sanitize_text_field($title),
                        'url'       => esc_url_raw($url),
                        'post_type' => $type,
                    );
                }
                if (count($pages) >= $max_entries) {
                    break;
                }
            }
        }

        return $pages;
    }

    // =========================================================================
    // Reference Page Content
    // =========================================================================

    /**
     * Extract clean, readable text from a post's block content for AI reference.
     *
     * Strips block markup, shortcodes, and HTML tags, then trims to a reasonable
     * token limit so it doesn't bloat the prompt.
     *
     * @param array $post_ids Array of post IDs (max 3).
     * @return string Formatted reference block or empty string.
     */
    private function get_reference_content(array $post_ids)
    {
        if (empty($post_ids)) {
            return '';
        }

        $blocks = array();
        foreach (array_slice($post_ids, 0, 3) as $post_id) {
            $post = get_post(absint($post_id));
            if (! $post || ! in_array(get_post_status($post_id), array('publish', 'draft', 'private'), true)) {
                continue;
            }

            if (! current_user_can('read_post', $post_id)) {
                continue;
            }

            $raw     = $post->post_content;
            // Render Gutenberg blocks to HTML first so block attributes don't become noise
            if (function_exists('do_blocks')) {
                $raw = do_blocks($raw);
            }
            // Remove any remaining HTML comments and shortcodes
            $raw     = preg_replace('/<!--.*?-->/s', '', $raw);
            $raw     = strip_shortcodes($raw);
            $raw     = wp_strip_all_tags($raw);
            // Collapse whitespace
            $raw     = preg_replace('/\s+/', ' ', $raw);
            $raw     = trim($raw);

            if (empty($raw)) {
                continue;
            }

            // Limit each page to ~600 words to avoid bloating the prompt
            $words   = explode(' ', $raw);
            if (count($words) > 600) {
                $raw = implode(' ', array_slice($words, 0, 600)) . '…';
            }

            $title    = get_the_title($post_id);
            $blocks[] = "--- Reference: \"{$title}\" ---\n{$raw}";
        }

        if (empty($blocks)) {
            return '';
        }

        return "IMPORTANT — Reference page content (you MUST draw on this):\nThe following pages were selected as references. Extract their specific services, features, facts, and key messages and incorporate them directly into the new page content. Do NOT ignore this section. Do NOT copy sentences verbatim — rewrite in the target tone, but preserve all specific details, offerings, and facts:\n"
            . implode("\n\n", $blocks) . "\n";
    }

    // =========================================================================
    // Auto Image Finder
    // =========================================================================

    /**
     * Automatically find relevant images from the media library for each image slot.
     *
     * Searches attachment titles, captions, and alt text using keywords extracted
     * from the page name and description. Falls back to the most recent images
     * when no keyword match is found for a slot.
     *
     * @param string $page_name         Page title (used for keyword extraction).
     * @param string $description       Content description (used for keyword extraction).
     * @param array  $image_requirements Image requirements from Image_Replacer::detect_image_requirements().
     * @return array Image mapping array (path key => attachment ID).
     */
    private function find_matching_images($page_name, $description, $image_requirements)
    {
        $mapping = array();

        // Extract keywords: lowercase words 4+ chars, minus stopwords
        $stopwords = array('with', 'that', 'this', 'from', 'have', 'will', 'your', 'their', 'about', 'page', 'section', 'content');
        $raw_text  = $page_name . ' ' . $description;
        preg_match_all('/[a-z]{4,}/i', strtolower($raw_text), $word_matches);
        $keywords = array_values(array_unique(array_diff($word_matches[0], $stopwords)));

        // Fetch all image attachments once (up to 200) — cheaper than per-slot queries
        $all_images = get_posts(array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'numberposts'    => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ));

        if (empty($all_images)) {
            return $mapping;
        }

        // Score each image against the keywords.
        // Alt text is the most descriptive field — weight it 3×.
        // Title is 2×, caption is 1×.
        $scored = array();
        foreach ($all_images as $att_id) {
            $alt     = strtolower(get_post_meta($att_id, '_wp_attachment_image_alt', true));
            $title   = strtolower(get_the_title($att_id));
            $caption = strtolower(get_post_field('post_excerpt', $att_id));
            $score   = 0;
            foreach ($keywords as $kw) {
                if (strpos($alt, $kw) !== false)     $score += 3;
                if (strpos($title, $kw) !== false)   $score += 2;
                if (strpos($caption, $kw) !== false) $score += 1;
            }
            $scored[] = array('id' => $att_id, 'score' => $score);
        }

        // Sort: highest score first, then preserve insertion (date DESC) order
        usort($scored, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Assign one unique image per slot
        $used = array();
        foreach ($image_requirements as $req) {
            $path_key = implode('.', $req['block_path']);
            foreach ($scored as $item) {
                if (! in_array($item['id'], $used, true)) {
                    $mapping[$path_key] = $item['id'];
                    $used[]               = $item['id'];
                    break;
                }
            }
        }

        return $mapping;
    }

    // =========================================================================
    // Company Context
    // =========================================================================

    /**
     * Gather site / company information to pass as context to the AI.
     *
     * Pulls the site title from WordPress core and, when the PDM Blocks plugin
     * is active, also reads location data (name, phone, email, address).
     *
     * @return array {
     *     @type string $company_name  Site title.
     *     @type array  $locations     Array of location arrays.
     * }
     */
    private function get_company_context()
    {
        $kb_name = $this->get_knowledge_base_company_name();

        $context = array(
            'company_name'   => '' !== $kb_name ? $kb_name : get_bloginfo('name'),
            'locations'      => array(),
            'knowledge_base' => $this->get_knowledge_base_context(),
        );

        if (function_exists('pdm_blocks_get_company_locations')) {
            $raw_locations = pdm_blocks_get_company_locations();
            foreach ($raw_locations as $loc) {
                $entry = array();
                if (! empty($loc['name']))    $entry['name']    = sanitize_text_field($loc['name']);
                if (! empty($loc['phone']))   $entry['phone']   = sanitize_text_field($loc['phone']);
                if (! empty($loc['email']))   $entry['email']   = sanitize_email($loc['email']);
                if (! empty($loc['address'])) $entry['address'] = sanitize_textarea_field($loc['address']);
                if (! empty($entry)) {
                    $context['locations'][] = $entry;
                }
            }
        }

        return $context;
    }

    /**
     * Public-facing business name from the PDM knowledge base.
     *
     * Empty when the knowledge base (PDM Plugins Manager) is absent or has no
     * answer, so the caller falls back to the site title.
     *
     * @return string
     */
    private function get_knowledge_base_company_name()
    {
        if (! function_exists('pdm_kb_get_items')) {
            return '';
        }

        foreach (pdm_kb_get_items(array('include_restricted' => true)) as $item) {
            $answer = trim((string) ($item['answer'] ?? ''));
            if ('' === $answer) {
                continue;
            }
            if (false !== strpos(strtolower((string) ($item['question'] ?? '')), 'business / company name')) {
                return wp_specialchars_decode($answer, ENT_QUOTES);
            }
        }

        return '';
    }

    /**
     * Knowledge base block for page prompts.
     *
     * @return string Empty when the knowledge base is missing or unanswered.
     */
    private function get_knowledge_base_context()
    {
        if (null !== $this->kb_context_cache) {
            return $this->kb_context_cache;
        }

        $this->kb_context_cache = '';

        if (! function_exists('pdm_kb_prompt_block')) {
            return '';
        }

        $block = pdm_kb_prompt_block(
            self::KB_CATEGORIES,
            array(
                'max_chars' => self::KB_MAX_CHARS,
                'order'     => self::KB_CATEGORIES,
                'intro'     => 'Client knowledge base (verified business facts — these take priority over the business context above):',
            )
        );

        $this->kb_context_cache = trim((string) $block);

        return $this->kb_context_cache;
    }

    // =========================================================================
    // API Key
    // =========================================================================

    /**
     * Retrieve the Google API key using WordPress Connectors (WP 7.0+).
     *
     * Checks in order:
     *  1. GOOGLE_API_KEY constant — set automatically by the Connectors Google integration.
     *  2. 'connectors_ai_google_api_key' option — written by the Connectors settings UI.
     *
     * @return string|null API key string, or null when not configured.
     */
    private function get_api_key()
    {
        if (defined('GOOGLE_API_KEY') && ! empty(GOOGLE_API_KEY)) {
            return GOOGLE_API_KEY;
        }

        $option_key = get_option('connectors_ai_google_api_key');
        if (! empty($option_key)) {
            return $option_key;
        }

        return null;
    }

    // =========================================================================
    // Template Analysis
    // =========================================================================

    /**
     * Analyse a template's block structure so we can tell the AI what to generate.
     *
     * @param \WP_Post $template Template post.
     * @return array {
     *     @type string $title     Template title.
     *     @type bool   $has_hero  Whether the template contains a hero section.
     *     @type array  $sections  Array of section descriptor arrays.
     * }
     */
    private function analyze_template_structure($template)
    {
        $blocks = parse_blocks($template->post_content);

        $structure = array(
            'title'    => $template->post_title,
            'has_hero' => false,
            'sections' => array(),
        );

        $this->extract_sections_from_blocks($blocks, $structure);

        return $structure;
    }

    /**
     * Recursively walk blocks and record top-level section blocks.
     *
     * @param array $blocks    Blocks to walk.
     * @param array $structure Structure array (passed by reference).
     */
    private function extract_sections_from_blocks($blocks, &$structure)
    {
        $section_block_names = array('pdm/section', 'core/group', 'core/cover');

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            if (in_array($block['blockName'], $section_block_names, true)) {
                $section_info = array(
                    'is_hero'     => false,
                    'type'        => 'content',
                    'has_heading' => false,
                    'has_body'    => false,
                );

                // Detect hero via attrs
                if (
                    isset($block['attrs']['imageURL']) ||
                    isset($block['attrs']['url']) ||
                    (isset($block['attrs']['useMinHeight']) && $block['attrs']['useMinHeight'])
                ) {
                    $section_info['is_hero'] = true;
                }

                $this->scan_inner_blocks($block['innerBlocks'] ?? array(), $section_info);

                // Detect hero via contained H1
                if (! $section_info['is_hero'] && ! empty($section_info['has_h1'])) {
                    $section_info['is_hero'] = true;
                }

                if ($section_info['is_hero']) {
                    $structure['has_hero'] = true;
                }

                $structure['sections'][] = $section_info;
            }
        }
    }

    /**
     * Scan inner blocks to detect content types within a section.
     *
     * @param array $inner_blocks Inner blocks.
     * @param array $section_info Section info (passed by reference).
     */
    private function scan_inner_blocks($inner_blocks, &$section_info)
    {
        foreach ($inner_blocks as $block) {
            if (empty($block['blockName'])) {
                continue;
            }

            switch ($block['blockName']) {
                case 'core/heading':
                    $level = $block['attrs']['level'] ?? 2;
                    $section_info['has_heading'] = true;
                    if ($level === 1) {
                        $section_info['has_h1'] = true;
                    }
                    break;

                case 'core/paragraph':
                case 'core/list':
                    $section_info['has_body'] = true;
                    break;

                default:
                    $name = $block['blockName'];
                    if (strpos($name, 'accordion') !== false || strpos($name, 'faq') !== false) {
                        $section_info['type'] = 'faq';
                    }
                    break;
            }

            if (! empty($block['innerBlocks'])) {
                $this->scan_inner_blocks($block['innerBlocks'], $section_info);
            }
        }
    }

    /**
     * Return a human-readable description of a template's structure.
     *
     * @param array $structure Template structure.
     * @return string Description string.
     */
    private function describe_template_structure($structure)
    {
        $total   = count($structure['sections']);
        $content = count(array_filter($structure['sections'], function ($s) {
            return ! $s['is_hero'];
        }));

        if ($structure['has_hero']) {
            return sprintf(
                /* translators: 1: total section count  2: content section count */
                _n(
                    '%1$d section total (%2$d content section + hero)',
                    '%1$d sections total (%2$d content sections + hero)',
                    $total,
                    'pdm-templates'
                ),
                $total,
                $content
            );
        }

        return sprintf(
            _n('%d content section', '%d content sections', $total, 'pdm-templates'),
            $total
        );
    }

    // =========================================================================
    // Prompt Building
    // =========================================================================

    /**
     * Build the Gemini prompt from the page name, user description, and template structure.
     *
     * @param string $page_name         The desired page title.
     * @param string $description       User's content description.
     * @param array  $template_structure Analysed template structure.
     * @return string Prompt string.
     */
    private function build_prompt($page_name, $description, $template_structure, $post_type = 'page', $tone = 'friendly', $is_landing = false, $ref_page_ids = array())
    {
        $tone_descriptions = array(
            'friendly'     => 'Friendly — Warm, conversational, and inviting. Write like you are speaking directly to the reader.',
            'professional' => 'Professional — Formal, authoritative, and clear. Use precise language and avoid colloquialisms.',
            'persuasive'   => 'Persuasive — Bold, urgent, and benefit-driven. Emphasise value, outcomes, and calls to action.',
            'creative'     => 'Creative — Playful, witty, and distinctive. Use vivid language, unexpected angles, and personality.',
            'empathetic'   => 'Empathetic — Compassionate, understanding, and reassuring. Acknowledge the reader\'s challenges and offer genuine help.',
        );
        $tone_instruction = $tone_descriptions[$tone] ?? $tone_descriptions['friendly'];
        $content_sections = array_values(
            array_filter($template_structure['sections'], function ($s) {
                return ! $s['is_hero'];
            })
        );

        $section_count = count($content_sections);

        if ($section_count === 0) {
            // Template has no detectable content sections — generate a single one
            $section_count    = 1;
            $content_sections = array(array('type' => 'content'));
        }

        // Build per-section instructions
        $section_lines = array();
        foreach ($content_sections as $i => $section) {
            $num = $i + 1;
            if ($section['type'] === 'faq') {
                $section_lines[] = "  Section {$num}: FAQ — include a heading and 4–6 question-and-answer pairs as accordions.";
            } else {
                $section_lines[] = "  Section {$num}: Content — include a heading and 2–4 paragraphs of body text.";
            }
        }
        $sections_instruction = implode("\n", $section_lines);

        $hero_note = $template_structure['has_hero']
            ? "The template contains a hero section that will automatically display the page title — do NOT include a hero section in the JSON.\n"
            : '';

        // Build company context block
        $company_context = $this->get_company_context();
        $context_lines   = array();

        if (! empty($company_context['company_name'])) {
            $context_lines[] = 'Company name: ' . $company_context['company_name'];
        }

        foreach ($company_context['locations'] as $i => $loc) {
            $prefix = count($company_context['locations']) > 1 ? 'Branch ' . ($i + 1) . ' — ' : '';
            if (! empty($loc['name']))    $context_lines[] = $prefix . 'Branch label: "' . $loc['name'] . '" (internal office name — not a city, region or service area)';
            if (! $is_landing && ! empty($loc['phone']))   $context_lines[] = $prefix . 'Phone: ' . $loc['phone'];
            if (! empty($loc['email']))   $context_lines[] = $prefix . 'Email: ' . $loc['email'];
            if (! empty($loc['address'])) $context_lines[] = $prefix . 'Address: ' . $loc['address'];
        }

        $company_block = empty($context_lines)
            ? ''
            : "Business context (use naturally in the content):\n" . implode("\n", $context_lines) . "\n";

        // Knowledge base context — empty on sites without a filled-in questionnaire.
        $kb_block = '';
        $kb       = $this->get_knowledge_base_context();
        if ('' !== $kb) {
            $kb_block = $kb . "\n\nUse these facts for the services, materials, terminology, objections and questions this page covers. Where they disagree with the business context above, the knowledge base is correct. Follow the selected writing tone for register, but use the knowledge base for preferred wording and for phrases to avoid. Never invent a service, brand, warranty, price, credential or service area that is not listed.\n";
        }

        // Resolve the post type label and build a post-type context note
        $pt_object    = get_post_type_object($post_type);
        $pt_label     = $pt_object ? $pt_object->labels->singular_name : ucfirst(str_replace(array('-', '_'), ' ', $post_type));
        $pt_note      = "Page type being generated: {$pt_label}\n";

        // Build site pages block — grouped by post type so the AI understands the site structure
        $site_pages   = $this->get_site_pages();
        $by_type      = array();
        foreach ($site_pages as $p) {
            $by_type[$p['post_type']][] = $p;
        }
        $pages_lines = array();
        foreach ($by_type as $type_slug => $type_pages) {
            if ($type_slug === $post_type) {
                continue; // skip pages of the same type being generated — they're peers, not references
            }
            $type_obj    = get_post_type_object($type_slug);
            $type_label  = $type_obj ? $type_obj->labels->name : ucfirst(str_replace(array('-', '_'), ' ', $type_slug));
            $pages_lines[] = "{$type_label}:";
            foreach ($type_pages as $p) {
                $pages_lines[] = '  - ' . $p['title'] . ': ' . $p['url'];
            }
        }
        // Also list same-type pages as reference context (not for linking, but for knowing what the site offers)
        if (! empty($by_type[$post_type])) {
            $same_label    = $pt_object ? $pt_object->labels->name : $pt_label . 's';
            $pages_lines[] = "Other {$same_label} on this site (use as context for what this business offers in other locations/areas — link to them when it genuinely helps the reader):";
            foreach ($by_type[$post_type] as $p) {
                $pages_lines[] = '  - ' . $p['title'] . ': ' . $p['url'];
            }
        }
        $pages_block = empty($pages_lines)
            ? ''
            : 'Site content (use for context' . ($is_landing ? ' only — do NOT add any links' : ' and linking') . "):\n" . implode("\n", $pages_lines) . "\n";

        // Build reference content block from selected pages
        $reference_block = $this->get_reference_content($ref_page_ids);

        $contact_rules = $is_landing
            ? "- Do NOT reference the company phone number anywhere in the content — keep visitors on-page for lead tracking."
            : "- Weave the company name, phone number, and email naturally into the content ONLY IF relevant";

        // Internal links are required on every page except landing pages.
        $links_rule = $is_landing
            ? "- This is a LANDING PAGE: do NOT include any hyperlinks or anchor tags anywhere in the content."
            : "- INTERNAL LINKS (required): include at least 3 and at most 6 internal links in the body text of this page. This is a hard requirement, not optional, so a page with fewer than 3 links is not finished. Take every link from the \"Site content\" lists above — they list published pages and posts only — and copy each URL exactly as written. Write them as HTML anchors: <a href=\"FULL-URL\">descriptive anchor text</a>. Spread the links across different sections instead of clustering them in one paragraph, and make the anchor text describe the destination (for example \"the services we offer\" or \"our recent work\") — never \"click here\", never a bare URL. Never link to the page being generated, never point two links at the same URL, and never invent or guess a URL.";

        $final_link_check = $is_landing
            ? "- Final check before you answer: there must be ZERO <a href=\"...\"> tags in the content. If you included any, delete them and return the corrected JSON."
            : "- Final check before you answer: count the <a href=\"...\"> tags in your paragraphs. There must be 3 to 6 of them. If there are fewer than 3, add more internal links from the \"Site content\" lists and return the corrected JSON.";

        /* Build the full prompt. The JSON schema is embedded so the model knows exactly what to produce. */
        return <<<PROMPT
You are a professional web content writer. Generate structured content for a WordPress page.

Page title: {$page_name}
{$pt_note}Content brief: {$description}

{$company_block}{$kb_block}{$pages_block}{$reference_block}{$hero_note}Generate content for exactly {$section_count} section(s) as follows:
{$sections_instruction}

Respond with ONLY a single valid JSON object — no markdown fences, no explanatory text, nothing else.


JSON schema:
{
  "title": "<string — use the page title exactly as given>",
  "sections": [
    {
      "heading": "<string>",
      "paragraphs": ["<string>", "..."]
    },
    {
      "heading": "<string>",
      "isFAQ": true,
      "accordions": [
        { "title": "<question string>", "content": "<answer string>" }
      ]
    }
  ]
}

Rules:
- Use the title exactly: "{$page_name}"
- Use the page type ("{$pt_label}") to guide the focus and structure of the content
- Writing tone: {$tone_instruction}
- Write in a natural, human voice. Vary sentence length and structure. NEVER use the word "Imagine" anywhere in the content — not at the start of a sentence, not mid-sentence, not at all. Also never use these openers: "Are you looking for", "In today's world", "Whether you", "Look no further", "Picture yourself", "Envision", or "At [Company]".
- Avoid AI buzzwords: do not use "seamless", "robust", "leverage", "cutting-edge", "comprehensive", "tailored solutions", "empower", "holistic", or "game-changing". Write plainly and specifically.
- Do not start two consecutive sentences with the same word.
- Write as a knowledgeable human would — concrete, specific, and direct.
{$contact_rules}
{$links_rule}
- The business context may list branch labels in quotes (for example "PDM Utah"). Those are internal office names, NOT cities, towns or regions: never write a sentence like "whether you're in PDM Utah" and never treat a label as a place. When a place name is needed, use the city and state from that branch's address line (for example "Layton, Utah"), or refer to the team instead ("our Utah team").
- You may reference the city and state/region the business is located in, but do NOT include the full street address in any content
- paragraphs arrays are used for normal content sections
- accordions arrays (with isFAQ: true) are used for FAQ sections only
- Each paragraph should be 2–4 sentences long
{$final_link_check}
- Return ONLY the JSON object
PROMPT;
    }

    // =========================================================================
    // Gemini API
    // =========================================================================

    /**
     * Call the Gemini Flash-Lite API and return the generated text.
     *
     * @param string $api_key Google API key.
     * @param string $prompt  Prompt to send.
     * @return string|\WP_Error Generated text, or WP_Error on failure.
     */
    private function call_gemini_api($api_key, $prompt)
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode(self::GEMINI_MODEL)
            . ':generateContent?key=' . urlencode($api_key);

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature'      => 0.7,
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',
            ),
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return new \WP_Error(
                'api_request_failed',
                /* translators: %s: underlying error message */
                sprintf(__('Gemini API request failed: %s', 'pdm-templates'), $response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (200 !== (int) $response_code) {
            $error_data    = json_decode($response_body, true);
            $error_message = $error_data['error']['message'] ?? $response_body;
            return new \WP_Error(
                'api_error',
                /* translators: 1: HTTP status code  2: error message */
                sprintf(__('Gemini API error (HTTP %1$d): %2$s', 'pdm-templates'), $response_code, $error_message)
            );
        }

        $data = json_decode($response_body, true);

        if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return new \WP_Error(
                'unexpected_response',
                __('Unexpected response format from Gemini API. Please try again.', 'pdm-templates')
            );
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    // =========================================================================
    // Response Parsing
    // =========================================================================

    /**
     * Parse the raw Gemini JSON response into the document array format
     * expected by Page_Generator::generate_page().
     *
     * @param string $ai_response Raw JSON string from Gemini.
     * @param string $page_name   Fallback page title.
     * @return array|\WP_Error Document array or WP_Error.
     */
    private function parse_ai_response($ai_response, $page_name)
    {
        // Strip markdown code fences if the model included them despite instructions
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($ai_response));
        $clean = preg_replace('/\s*```$/m', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'json_parse_error',
                __('Failed to parse the AI response as JSON. Please try again.', 'pdm-templates')
            );
        }

        if (empty($data['sections']) || ! is_array($data['sections'])) {
            return new \WP_Error(
                'invalid_response_structure',
                __('AI response did not contain a valid sections array. Please try again.', 'pdm-templates')
            );
        }

        // Build document in the format Page_Generator expects
        $document = array(
            'title'    => sanitize_text_field($data['title'] ?? $page_name),
            'fileName' => 'ai-generated',
            'sections' => array(),
        );

        foreach ($data['sections'] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $doc_section = array(
                'heading' => sanitize_text_field($section['heading'] ?? ''),
            );

            if (! empty($section['isFAQ']) && ! empty($section['accordions']) && is_array($section['accordions'])) {
                // FAQ section
                $doc_section['isFAQ']      = true;
                $doc_section['accordions'] = array();

                foreach ($section['accordions'] as $accordion) {
                    if (! is_array($accordion)) {
                        continue;
                    }
                    $doc_section['accordions'][] = array(
                        'title'   => sanitize_text_field($accordion['title'] ?? ''),
                        'content' => wp_kses_post($accordion['content'] ?? ''),
                    );
                }
            } else {
                // Standard content section — join paragraphs into a single HTML string
                // because Page_Generator::fill_inner_blocks() reads $section['content'].
                $raw_paragraphs = isset($section['paragraphs']) && is_array($section['paragraphs'])
                    ? $section['paragraphs']
                    : array();
                $clean_paragraphs = array_filter(array_map('wp_kses_post', $raw_paragraphs));
                $doc_section['content'] = implode(
                    '',
                    array_map(
                        function ($p) {
                            return '<p>' . $p . '</p>';
                        },
                        $clean_paragraphs
                    )
                );
            }

            $document['sections'][] = $doc_section;
        }

        return $document;
    }
}
