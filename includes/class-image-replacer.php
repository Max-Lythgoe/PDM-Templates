<?php

/**
 * Image Replacer Class
 *
 * Handles detection and replacement of images in template blocks.
 *
 * @package PDM_Templates
 */

namespace PDM\Templates;

/**
 * Image Replacer class
 */
class Image_Replacer
{
    /**
     * Detect all image requirements in a template
     *
     * @param int $template_id Template post ID.
     * @return array Array of image requirements with block info.
     */
    public function detect_image_requirements($template_id)
    {
        $content = get_post_field('post_content', $template_id);
        if (empty($content)) {
            return array();
        }

        $blocks = parse_blocks($content);
        $image_requirements = array();

        $this->find_images_in_blocks($blocks, $image_requirements);

        return $image_requirements;
    }

    /**
     * Recursively find all blocks with images
     *
     * @param array $blocks              Block array.
     * @param array &$image_requirements Reference to image requirements array.
     * @param array $path                Current block path for tracking.
     */
    private function find_images_in_blocks($blocks, &$image_requirements, $path = array())
    {
        foreach ($blocks as $index => $block) {
            $current_path = array_merge($path, array($index));

            // Check for images in block attributes
            if (!empty($block['blockName'])) {
                $image_info = $this->extract_image_info($block);

                if ($image_info) {
                    $image_requirements[] = array(
                        'block_name' => $block['blockName'],
                        'block_path' => $current_path,
                        'image_info' => $image_info,
                        'context' => $this->get_block_context($block),
                    );
                }
            }

            // Recursively check inner blocks
            if (!empty($block['innerBlocks'])) {
                $this->find_images_in_blocks($block['innerBlocks'], $image_requirements, $current_path);
            }
        }
    }

    /**
     * Extract image information from a block
     *
     * @param array $block Block data.
     * @return array|null Image info or null if no image.
     */
    private function extract_image_info($block)
    {
        $attrs = $block['attrs'] ?? array();
        $image_info = null;

        // Check for imageURL (pdm/section, pdm/media-and-content)
        if (!empty($attrs['imageURL'])) {
            $image_info = array(
                'url' => $attrs['imageURL'],
                'id' => $attrs['imageID'] ?? null,
                'alt' => $attrs['imageAlt'] ?? '',
                'title' => $attrs['defaultTitle'] ?? '',
            );
        }
        // Check for core/image block
        elseif ($block['blockName'] === 'core/image' && !empty($attrs['url'])) {
            $image_info = array(
                'url' => $attrs['url'],
                'id' => $attrs['id'] ?? null,
                'alt' => $attrs['alt'] ?? '',
                'title' => $attrs['title'] ?? '',
            );
        }
        // Check for inline images in HTML
        elseif (!empty($block['innerHTML']) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $block['innerHTML'])) {
            // Has inline image but we'll handle this during replacement
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $block['innerHTML'], $matches);
            $image_info = array(
                'url' => $matches[1] ?? '',
                'id' => null,
                'alt' => '',
                'title' => '',
                'inline' => true,
            );
        }

        return $image_info;
    }

    /**
     * Get human-readable context for a block
     *
     * @param array $block Block data.
     * @return string Context description.
     */
    private function get_block_context($block)
    {
        $block_name = $block['blockName'] ?? '';
        $attrs = $block['attrs'] ?? array();

        switch ($block_name) {
            case 'pdm/section':
                if (!empty($attrs['imageURL'])) {
                    // Check if it's a hero section
                    $has_heading = false;
                    foreach ($block['innerBlocks'] ?? array() as $inner) {
                        if ($inner['blockName'] === 'core/heading' && ($inner['attrs']['level'] ?? 2) === 1) {
                            $has_heading = true;
                            break;
                        }
                    }
                    return $has_heading ? 'Hero Section Background' : 'Section Background';
                }
                break;

            case 'pdm/media-and-content':
                return 'Media and Content Block';

            case 'core/image':
                return 'Image Block';
        }

        return ucfirst(str_replace(array('/', '-'), ' ', $block_name));
    }

    /**
     * Replace images in template blocks with selected images
     *
     * @param array $blocks          Block array.
     * @param array $image_mapping   Array mapping block paths to attachment IDs.
     * @return array Modified blocks.
     */
    public function replace_images_in_blocks($blocks, $image_mapping)
    {
        return $this->process_image_replacements($blocks, $image_mapping);
    }

    /**
     * Recursively process image replacements
     *
     * @param array $blocks         Block array.
     * @param array $image_mapping  Image mapping data.
     * @param array $path           Current block path.
     * @return array Modified blocks.
     */
    private function process_image_replacements($blocks, $image_mapping, $path = array())
    {
        foreach ($blocks as $index => &$block) {
            $current_path = array_merge($path, array($index));
            $path_key = implode('.', $current_path);

            // Check if this block needs image replacement
            if (isset($image_mapping[$path_key])) {
                $attachment_id = $image_mapping[$path_key];
                $block = $this->apply_image_to_block($block, $attachment_id);
            } else {
                // Even if not replacing the image, sync HTML alt with defaultAlt attribute
                $block = $this->sync_alt_attribute($block);
            }

            // Recursively process inner blocks
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = $this->process_image_replacements(
                    $block['innerBlocks'],
                    $image_mapping,
                    $current_path
                );
            }
        }

        return $blocks;
    }

    /**
     * Apply image data to a block
     *
     * @param array $block         Block data.
     * @param int   $attachment_id Attachment ID.
     * @return array Modified block.
     */
    private function apply_image_to_block($block, $attachment_id)
    {

        $image_url = wp_get_attachment_url($attachment_id);
        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $image_title = get_the_title($attachment_id);

        // HTML-decode title and alt to avoid double-encoding issues
        // serialize_block_attributes() will convert & to \u0026, but WordPress editor
        // doesn't properly decode it back, causing validation issues
        $image_alt = html_entity_decode($image_alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $image_title = html_entity_decode($image_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (!$image_url) {
            error_log("  -> Failed to get URL for attachment $attachment_id");
            return $block;
        }

        $attrs = &$block['attrs'];

        // Handle pdm/section and pdm/media-and-content blocks
        if (in_array($block['blockName'], array('pdm/section', 'pdm/media-and-content'))) {
            $attrs['imageURL'] = $image_url;
            $attrs['imageID'] = $attachment_id;
            // Always set defaultAlt, even if empty, to keep attributes and HTML in sync
            $attrs['defaultAlt'] = $image_alt;
            $attrs['defaultTitle'] = $image_title;

            // Use the final alt value that matches what the save() function would use:
            // customAlt || defaultAlt || ''
            $final_alt = '';
            if (!empty($attrs['customAlt'])) {
                $final_alt = $attrs['customAlt'];
            } elseif (!empty($attrs['defaultAlt'])) {
                $final_alt = $attrs['defaultAlt'];
            }

            // Update innerHTML with new image
            $block['innerHTML'] = $this->update_image_in_html(
                $block['innerHTML'],
                $image_url,
                $attachment_id,
                $final_alt,
                $image_title
            );

            // Also update innerContent to match innerHTML
            $block['innerContent'] = $this->rebuild_inner_content($block);
        }
        // Handle core/image blocks
        elseif ($block['blockName'] === 'core/image') {
            // Only update the ID attribute
            $attrs['id'] = $attachment_id;
            // Don't add url, alt, or title attributes - just update the HTML

            // Update innerHTML (src and wp-image-* class only)
            $block['innerHTML'] = $this->update_core_image_html(
                $block['innerHTML'],
                $image_url,
                $attachment_id
            );

            // Also update innerContent to match innerHTML
            $block['innerContent'] = array($block['innerHTML']);
        }

        return $block;
    }

    /**
     * Update image HTML with new image data
     *
     * @param string $html         Original HTML.
     * @param string $url          New image URL.
     * @param int    $id           New image ID.
     * @param string $alt          New alt text.
     * @param string $title        New title.
     * @return string Modified HTML.
     */
    private function update_image_in_html($html, $url, $id, $alt, $title)
    {
        // Replace src attribute
        $html = preg_replace('/src="[^"]*"/', 'src="' . esc_url($url) . '"', $html);

        // Replace wp-image-* class
        $html = preg_replace('/wp-image-\d+/', 'wp-image-' . $id, $html);

        // Decode JSON unicode escapes (u0026 -> &) to convert to proper HTML entities
        // Example: "u0026#8211;" becomes "&#8211;" (en dash entity)
        $alt = str_replace('u0026', '&', $alt);
        $title = str_replace('u0026', '&', $title);

        // Always set alt to empty or the provided value
        if (preg_match('/alt="[^"]*"/', $html)) {
            $html = preg_replace('/alt="[^"]*"/', 'alt="' . $alt . '"', $html);
        } else {
            $html = preg_replace('/<img/', '<img alt="' . $alt . '"', $html);
        }

        // Replace title attribute
        if (!empty($title)) {
            if (preg_match('/title="[^"]*"/', $html)) {
                $html = preg_replace('/title="[^"]*"/', 'title="' . $title . '"', $html);
            } else {
                $html = preg_replace('/<img/', '<img title="' . $title . '"', $html);
            }
        }

        return $html;
    }

    /**
     * Update core/image block HTML with new image data (only src and class)
     *
     * @param string $html Original HTML.
     * @param string $url  New image URL.
     * @param int    $id   New image ID.
     * @return string Modified HTML.
     */
    private function update_core_image_html($html, $url, $id)
    {
        // Replace src attribute
        $html = preg_replace('/src="[^"]*"/', 'src="' . esc_url($url) . '"', $html);

        // Replace wp-image-* class
        $html = preg_replace('/wp-image-\d+/', 'wp-image-' . $id, $html);

        // Don't modify alt or title attributes - keep them as-is (empty)

        return $html;
    }

    /**
     * Rebuild innerContent array based on updated innerHTML
     * This ensures serialization uses the updated HTML
     */
    private function rebuild_inner_content($block)
    {
        if (!isset($block['innerBlocks']) || empty($block['innerBlocks'])) {
            // No inner blocks, innerContent is just the innerHTML
            return array($block['innerHTML']);
        }

        // Has inner blocks - preserve the existing innerContent structure
        // The innerContent array contains HTML fragments and nulls for inner blocks
        // We need to update any HTML fragments that contain image tags
        $inner_content = isset($block['innerContent']) ? $block['innerContent'] : array();

        if (empty($inner_content)) {
            // Build innerContent from scratch for pdm/section and pdm/media-and-content blocks
            if ($block['blockName'] === 'pdm/section') {
                $html = $block['innerHTML'];
                // Find the content-wrapper div opening and the final closing divs
                if (preg_match('/^(.+<div[^>]*class="[^"]*content-wrapper[^"]*"[^>]*>)\s*(.*)(<\/div><\/div><\/div>)$/s', $html, $matches)) {
                    $opening = $matches[1];  // Strips trailing whitespace
                    $closing = $matches[3];

                    // Build innerContent array: opening, nulls for each inner block, closing
                    $inner_content = array($opening);
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    $inner_content[] = $closing;

                    return $inner_content;
                }

                // Regex didn't match - try a more lenient approach
                // Just split on where innerBlocks should go (after content-wrapper opening)
                if (preg_match('/^(.*<div[^>]*content-wrapper[^>]*>)(.*)$/s', $html, $matches)) {
                    $opening = rtrim($matches[1]); // Force strip whitespace
                    $rest = $matches[2];

                    // Build innerContent array
                    $inner_content = array($opening);
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    // Add the closing HTML if there is any
                    if (!empty($rest)) {
                        $inner_content[] = $rest;
                    }

                    return $inner_content;
                }

                // Last resort: create simple structure with rtrimmed innerHTML
                $inner_content = array(rtrim($html));
                foreach ($block['innerBlocks'] as $inner_block) {
                    $inner_content[] = null;
                }
                return $inner_content;
            }

            // Handle pdm/media-and-content blocks
            if ($block['blockName'] === 'pdm/media-and-content') {
                $html = $block['innerHTML'];
                // Find the mc-content div opening and the final closing divs
                // Pattern: <div class="mc-content">
                if (preg_match('/^(.+<div[^>]*class="[^"]*mc-content[^"]*"[^>]*>)(.*)(<\/div><\/div>)$/s', $html, $matches)) {
                    $opening = rtrim($matches[1]);  // Strip trailing whitespace
                    $closing = $matches[3];

                    // Build innerContent array: opening, nulls for each inner block, closing
                    $inner_content = array($opening);
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    $inner_content[] = $closing;

                    return $inner_content;
                }

                // Fallback: try more lenient pattern
                if (preg_match('/^(.*<div[^>]*mc-content[^>]*>)(.*)$/s', $html, $matches)) {
                    $opening = rtrim($matches[1]);
                    $rest = $matches[2];

                    $inner_content = array($opening);
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    if (!empty($rest)) {
                        $inner_content[] = $rest;
                    }

                    return $inner_content;
                }
            }

            // Fallback: if we can't parse it, return current state or empty
            return $inner_content;
        }

        // Update HTML fragments in innerContent that contain images
        foreach ($inner_content as $index => $content) {
            if ($content !== null && is_string($content)) {
                // Update image tags if present
                if (strpos($content, '<img') !== false) {
                    // This fragment contains an image, update it with the same replacements
                    // Extract the image tag from the updated innerHTML
                    if (preg_match('/<img[^>]+>/', $block['innerHTML'], $img_matches)) {
                        $new_img_tag = $img_matches[0];
                        // Replace the img tag in this fragment
                        $inner_content[$index] = preg_replace('/<img[^>]+>/', $new_img_tag, $content);
                    }
                }

                // For pdm/section and pdm/media-and-content blocks, strip trailing whitespace
                if (in_array($block['blockName'], array('pdm/section', 'pdm/media-and-content'))) {
                    $inner_content[$index] = rtrim($inner_content[$index]);
                }
            }
        }

        return $inner_content;
    }

    /**
     * Sync HTML alt attribute with customAlt or defaultAlt attribute if present
     * PDM blocks use customAlt (user override) and defaultAlt (from media library)
     * Priority: customAlt > defaultAlt > empty
     *
     * @param array $block Block data.
     * @return array Modified block.
     */
    private function sync_alt_attribute($block)
    {
        // Only process blocks that might have images with alt attributes
        if (!in_array($block['blockName'], array('pdm/section', 'pdm/media-and-content'))) {
            return $block;
        }

        $needs_rebuild = false;

        // Check if block has innerHTML with an img tag for alt syncing
        if (!empty($block['innerHTML']) && strpos($block['innerHTML'], '<img') !== false) {
            // Determine which alt text to use (customAlt takes precedence over defaultAlt)
            $alt_text = '';
            if (isset($block['attrs']['customAlt']) && !empty($block['attrs']['customAlt'])) {
                $alt_text = $block['attrs']['customAlt'];
            } elseif (isset($block['attrs']['defaultAlt']) && !empty($block['attrs']['defaultAlt'])) {
                $alt_text = $block['attrs']['defaultAlt'];
            }

            // If we have alt text and HTML has empty alt attribute, update it
            if (!empty($alt_text) && preg_match('/alt=""/', $block['innerHTML'])) {
                // Decode JSON unicode escapes (u0026 -> &) to convert to proper HTML entities
                $alt_text = str_replace('u0026', '&', $alt_text);
                // Update innerHTML
                $block['innerHTML'] = preg_replace('/alt=""/', 'alt="' . $alt_text . '"', $block['innerHTML']);
                $needs_rebuild = true;
            }
        }

        // Only rebuild innerContent if we changed something OR if it has problematic whitespace
        if ($needs_rebuild) {
            $block['innerContent'] = $this->rebuild_inner_content($block);
        } elseif (in_array($block['blockName'], array('pdm/section', 'pdm/media-and-content')) && isset($block['innerContent']) && !empty($block['innerContent'])) {
            // Check if existing innerContent has trailing whitespace that needs fixing
            $has_whitespace = false;
            foreach ($block['innerContent'] as $content) {
                if ($content !== null && is_string($content) && $content !== rtrim($content)) {
                    $has_whitespace = true;
                    break;
                }
            }

            // Only rebuild if we found whitespace
            if ($has_whitespace) {
                $block['innerContent'] = $this->rebuild_inner_content($block);
            }
        }

        return $block;
    }
}
