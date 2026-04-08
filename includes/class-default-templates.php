<?php

/**
 * Default Templates
 *
 * Creates default template posts with placeholder images on plugin activation
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Default_Templates
{
    /**
     * Create default templates
     * Called on plugin activation
     */
    public static function create_default_templates()
    {
        // Check if templates already exist
        $existing = get_posts(array(
            'post_type' => 'pdm_template',
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));

        // Only create if no templates exist
        if (!empty($existing)) {
            return;
        }

        // Upload placeholder image
        $placeholder_id = self::upload_placeholder_image();

        if (!$placeholder_id) {
            return;
        }

        // Get placeholder image URL
        $placeholder_url = wp_get_attachment_url($placeholder_id);

        // Create templates
        self::create_subpage_template($placeholder_id, $placeholder_url);
        self::create_service_area_template($placeholder_id, $placeholder_url);
    }

    /**
     * Upload placeholder image to media library
     *
     * @return int|false Attachment ID or false on failure
     */
    private static function upload_placeholder_image()
    {
        $placeholder_path = PDM_TEMPLATES_PATH . 'assets/placeholder.webp';

        if (!file_exists($placeholder_path)) {
            return false;
        }

        // Check if already uploaded
        $existing = get_posts(array(
            'post_type' => 'attachment',
            'meta_key' => '_pdm_placeholder_image',
            'meta_value' => '1',
            'posts_per_page' => 1,
        ));

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        // Upload image
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $filename = 'pdm-placeholder-' . time() . '.webp';
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;

        // Copy file to uploads directory
        if (!copy($placeholder_path, $target_path)) {
            return false;
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/webp',
            'post_title' => 'PDM Template Placeholder',
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $target_path);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Generate metadata
        $metadata = wp_generate_attachment_metadata($attachment_id, $target_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        // Mark as placeholder
        update_post_meta($attachment_id, '_pdm_placeholder_image', '1');
        update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Placeholder image');

        return $attachment_id;
    }

    /**
     * Create Subpage template
     *
     * @param int    $image_id  Placeholder image ID
     * @param string $image_url Placeholder image URL
     */
    private static function create_subpage_template($image_id, $image_url)
    {
        $content = <<<HTML
<!-- wp:pdm/section {"layout":{"type":"constrained","justifyContent":"center"},"useMinHeight":true,"imageURL":"$image_url","defaultAlt":"Placeholder image","defaultTitle":"Placeholder image","imageID":$image_id,"backgroundColor":"black","textColor":"white","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"}}}}} -->
<div class="wp-block-pdm-section alignfull has-bg-image  is-vertically-aligned-center has-white-color has-black-background-color has-text-color has-background has-link-color" style="min-height:50vh"><div class="section-flex-container content-last"><div class="section-background"><img class="wp-image-$image_id" src="$image_url" alt="" title="" style="object-fit:cover;object-position:50% 50%;opacity:0.5;mix-blend-mode:normal"/></div><div class="content-wrapper"><!-- wp:heading {"textAlign":"center","level":1,"placeholder":"Hero Title","fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size"></h1>
<!-- /wp:heading --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"tertiary"} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-tertiary-background-color has-background"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:pdm/media-and-content {"imageURL":"$image_url","imageID":$image_id,"defaultAlt":"Placeholder image","defaultTitle":"Placeholder image"} -->
<div class="wp-block-pdm-media-and-content mc-flex mc-media-image is-vertically-aligned-center" style="--mediaSide:row;--mediaStack:column;--imageFit:cover;--aspect:16/9;--max-width:800px;--itaGap:40px;--imageAspect:16/9;--imageMaxHeight:500px;--mediaBorderRadius:0px 0px 0px 0px"><div class="mc-media"><img class="mc-media-image wp-image-$image_id" src="$image_url" alt="" title="" style="object-fit:cover;object-position:50% 50%"/></div><div class="mc-content"><!-- wp:heading {"placeholder":"Heading (h2)","fontSize":"m-large"} -->
<h2 class="wp-block-heading has-m-large-font-size"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Lorem ipsum dolor sit amet consectetur. Tincidunt vel ornare duis ac posuere sed tempus leo viverra. Donec integer in in justo felis. Tristique in massa ut ut aliquet quisque aliquet urna. Arcu feugiat odio duis diam faucibus massa. Pulvinar donec in massa tincidunt tellus. Diam nec iaculis ut cras ornare. Neque semper et vestibulum quis hendrerit vulputate. Cum porta arcu fermentum maecenas. Quis nulla pretium convallis egestas pellentesque fusce scelerisque."} -->
<p></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:pdm/media-and-content --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"primary","metadata":{"name":"Contact Us Today"}} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-primary-background-color has-background"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading {"textAlign":"center","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color has-link-color has-x-large-font-size">Contact Us Today</h2>
<!-- /wp:heading -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"primary","style":{"elements":{"link":{"color":{"text":"var:preset|color|primary"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background has-link-color wp-element-button" href="/contact-us/">Click Here</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"tertiary"} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-tertiary-background-color has-background"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->
HTML;

        $template_id = wp_insert_post(array(
            'post_title' => 'Subpage',
            'post_content' => $content,
            'post_type' => 'pdm_template',
            'post_status' => 'publish',
        ));

        if (!is_wp_error($template_id)) {
            update_post_meta($template_id, '_pdm_default_template', '1');
        }
    }

    /**
     * Create Service Area template
     *
     * @param int    $image_id  Placeholder image ID
     * @param string $image_url Placeholder image URL
     */
    private static function create_service_area_template($image_id, $image_url)
    {
        $content = <<<HTML
<!-- wp:pdm/section {"layout":{"type":"constrained","justifyContent":"center"},"useMinHeight":true,"imageURL":"$image_url","defaultAlt":"Placeholder image","defaultTitle":"Placeholder image","imageID":$image_id,"backgroundColor":"black","textColor":"white","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"}}}}} -->
<div class="wp-block-pdm-section alignfull has-bg-image  is-vertically-aligned-center has-white-color has-black-background-color has-text-color has-background has-link-color" style="min-height:50vh"><div class="section-flex-container content-last"><div class="section-background"><img class="wp-image-$image_id" src="$image_url" alt="" title="" style="object-fit:cover;object-position:50% 50%;opacity:0.5;mix-blend-mode:normal"/></div><div class="content-wrapper"><!-- wp:heading {"textAlign":"center","level":1,"placeholder":"Hero Title","fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size"></h1>
<!-- /wp:heading --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:pdm/media-and-content {"mediaSide":"row-reverse","imageURL":"$image_url","imageID":$image_id,"defaultAlt":"Placeholder image","defaultTitle":"Placeholder image"} -->
<div class="wp-block-pdm-media-and-content mc-flex mc-media-image is-vertically-aligned-center" style="--mediaSide:row-reverse;--mediaStack:column;--imageFit:cover;--aspect:16/9;--max-width:800px;--itaGap:40px;--imageAspect:16/9;--imageMaxHeight:500px;--mediaBorderRadius:0px 0px 0px 0px"><div class="mc-media"><img class="mc-media-image wp-image-$image_id" src="$image_url" alt="" title="" style="object-fit:cover;object-position:50% 50%"/></div><div class="mc-content"><!-- wp:heading {"placeholder":"Heading (h2)","fontSize":"m-large"} -->
<h2 class="wp-block-heading has-m-large-font-size"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Lorem ipsum dolor sit amet consectetur. Tincidunt vel ornare duis ac posuere sed tempus leo viverra. Donec integer in in justo felis. Tristique in massa ut ut aliquet quisque aliquet urna. Arcu feugiat odio duis diam faucibus massa. Pulvinar donec in massa tincidunt tellus. Diam nec iaculis ut cras ornare. Neque semper et vestibulum quis hendrerit vulputate. Cum porta arcu fermentum maecenas. Quis nulla pretium convallis egestas pellentesque fusce scelerisque."} -->
<p></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:pdm/media-and-content --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"contrast","textColor":"base","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"metadata":{"name":"Service Areas"}} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-base-color has-contrast-background-color has-text-color has-background has-link-color"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:pdm/split-columns -->
<div class="wp-block-pdm-split-columns pdm-split-columns stack-1024 is-vertically-aligned-stretch" style="--column-sizing:50%;--h-gap:40px;--v-gap:20px;--row-direction:row;--stack-order:column"><!-- wp:pdm/split-column -->
<div class="wp-block-pdm-split-column"><!-- wp:pdm/company-info {"infoType":"map","mapWidth":"map-full"} /--></div>
<!-- /wp:pdm/split-column -->

<!-- wp:pdm/split-column -->
<div class="wp-block-pdm-split-column"><!-- wp:heading -->
<h2 class="wp-block-heading">Service Areas</h2>
<!-- /wp:heading -->

<!-- wp:pdm/service-areas {"markerColor":"#e11919"} /--></div>
<!-- /wp:pdm/split-column --></div>
<!-- /wp:pdm/split-columns --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"primary","textColor":"base","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"metadata":{"name":"GET A FREE QUOTE"}} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-base-color has-primary-background-color has-text-color has-background has-link-color"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading {"textAlign":"center","fontSize":"x-large"} -->
<h2 class="wp-block-heading has-text-align-center has-x-large-font-size">GET A FREE QUOTE</h2>
<!-- /wp:heading -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"primary","style":{"elements":{"link":{"color":{"text":"var:preset|color|primary"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-white-background-color has-text-color has-background has-link-color wp-element-button" href="/contact-us/">Contact Us</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section {"backgroundColor":"tertiary"} -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center has-tertiary-background-color has-background"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->

<!-- wp:pdm/section -->
<div class="wp-block-pdm-section alignfull is-vertically-aligned-center"><div class="section-flex-container content-last"><div class="content-wrapper"><!-- wp:heading -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph --></div></div></div>
<!-- /wp:pdm/section -->
HTML;

        $template_id = wp_insert_post(array(
            'post_title' => 'Service Area',
            'post_content' => $content,
            'post_type' => 'pdm_template',
            'post_status' => 'publish',
        ));

        if (!is_wp_error($template_id)) {
            update_post_meta($template_id, '_pdm_default_template', '1');
        }
    }
}
