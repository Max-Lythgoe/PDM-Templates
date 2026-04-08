<?php

/**
 * Page Generator from Documents
 *
 * @package PDM\Templates
 */

namespace PDM\Templates;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Page Generator Class
 */
class Page_Generator
{

    /**
     * Generate a page from document data
     *
     * @param int    $template_id   Template post ID.
     * @param array  $document      Document data with title, sections, etc.
     * @param string $post_type     Post type to create.
     * @param string $post_status   Post status.
     * @param array  $image_mapping Optional. Image replacement mapping.
     * @return int|\WP_Error Post ID on success, WP_Error on failure.
     */
    public function generate_page($template_id, $document, $post_type = 'post', $post_status = 'draft', $image_mapping = array())
    {
        // Get template
        $template = get_post($template_id);
        if (! $template) {
            return new \WP_Error('invalid_template', __('Template not found', 'pdm-templates'));
        }

        // Parse template blocks
        $template_blocks = parse_blocks($template->post_content);

        // Apply image replacements if provided
        if (!empty($image_mapping)) {
            $image_replacer = new Image_Replacer();
            $template_blocks = $image_replacer->replace_images_in_blocks($template_blocks, $image_mapping);
        }

        // Process document sections and map to blocks
        $content_blocks = $this->process_template_with_document($template_blocks, $document);

        // Serialize blocks
        $content = serialize_blocks($content_blocks);

        // Create the post
        $post_data = array(
            'post_title'   => isset($document['title']) ? sanitize_text_field($document['title']) : __('Untitled', 'pdm-templates'),
            'post_content' => $content,
            'post_type'    => $post_type,
            'post_status'  => $post_status,
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store original document data as meta
        update_post_meta($post_id, '_pdm_source_document', $document['fileName'] ?? '');
        update_post_meta($post_id, '_pdm_source_template', $template_id);

        return $post_id;
    }

    /**
     * Process template with document data
     *
     * @param array $blocks   Template blocks.
     * @param array $document Document data.
     * @return array Processed blocks.
     */
    private function process_template_with_document($blocks, $document)
    {
        $sections = $document['sections'] ?? array();

        // First, fill the first H1 in the template with the document title
        $this->fill_first_h1_with_title($blocks, $document['title'] ?? '');

        // Find all section blocks (or other container blocks)
        $section_blocks = $this->find_section_blocks($blocks);

        if (empty($section_blocks)) {
            // No section blocks found, return original blocks
            // Fill what we can find
            return $this->fill_simple_blocks($blocks, $sections);
        }

        $processed_blocks = array();
        $section_index = 0;
        $block_index = 0;

        foreach ($blocks as $block) {
            // Check if this is a section block
            if ($this->is_section_block($block)) {
                $is_empty = $this->is_empty_section_block($block);

                if ($is_empty && $section_index < count($sections)) {
                    // Empty section block - fill with document section
                    $processed_blocks[] = $this->fill_section_block($block, $sections[$section_index]);
                    $section_index++;
                } elseif (!$is_empty) {
                    // Section has content - preserve it as-is
                    $processed_blocks[] = $block;
                } else {
                    // Empty section but no more document sections - remove it
                    continue;
                }
            } else {
                // Not a section block, keep as-is
                $processed_blocks[] = $block;
            }
        }

        // If we have more sections than template blocks, add more section blocks
        if ($section_index < count($sections)) {
            // Find a template section to clone (skip hero if present)
            $template_section = $this->find_clonable_section($section_blocks);

            if ($template_section) {
                // Add remaining sections
                while ($section_index < count($sections)) {
                    $section = $sections[$section_index];

                    // Check if this section was already added (prevent FAQ duplication)
                    $already_added = false;
                    foreach ($processed_blocks as $processed) {
                        if ($this->is_section_block($processed) && !empty($processed['innerBlocks'])) {
                            // Check if this block has the same heading as our FAQ
                            foreach ($processed['innerBlocks'] as $inner) {
                                if ($inner['blockName'] === 'core/heading' && isset($section['heading'])) {
                                    $heading_text = strip_tags($inner['innerHTML'] ?? '');
                                    if (trim($heading_text) === trim($section['heading'])) {
                                        $already_added = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }

                    if (!$already_added) {
                        $new_block = $this->clone_section_block($template_section);
                        $processed_blocks[] = $this->fill_section_block($new_block, $section);
                    }
                    $section_index++;
                }
            }
        }

        return $processed_blocks;
    }

    /**
     * Find all section blocks in the template
     *
     * @param array $blocks Blocks to search.
     * @return array Section blocks.
     */
    private function find_section_blocks($blocks)
    {
        $section_blocks = array();

        foreach ($blocks as $block) {
            if ($this->is_section_block($block)) {
                $section_blocks[] = $block;
            }
        }

        return $section_blocks;
    }

    /**
     * Fill the first H1 in the template with the document title
     *
     * @param array  $blocks Blocks to search (passed by reference).
     * @param string $title  Document title.
     * @return bool True if H1 was found and filled.
     */
    private function fill_first_h1_with_title(&$blocks, $title)
    {
        foreach ($blocks as &$block) {
            // Check if this is an H1 heading
            if ($block['blockName'] === 'core/heading' && ($block['attrs']['level'] ?? 2) === 1) {
                // Check if it's empty
                $existing_content = strip_tags($block['innerHTML'] ?? '');
                if (empty(trim($existing_content))) {
                    // Fill it with the title
                    $classes = array('wp-block-heading');

                    // Preserve existing classes
                    if (isset($block['attrs']['className'])) {
                        $classes[] = $block['attrs']['className'];
                    }
                    if (isset($block['attrs']['textAlign'])) {
                        $classes[] = 'has-text-align-' . esc_attr($block['attrs']['textAlign']);
                    }
                    if (isset($block['attrs']['fontSize'])) {
                        $classes[] = 'has-' . esc_attr($block['attrs']['fontSize']) . '-font-size';
                    }
                    if (isset($block['attrs']['textColor'])) {
                        $classes[] = 'has-' . esc_attr($block['attrs']['textColor']) . '-color';
                        $classes[] = 'has-text-color';
                    }
                    if (isset($block['attrs']['textBalance']) && $block['attrs']['textBalance']) {
                        $classes[] = 'has-text-balance';
                    }

                    // Build H1 with title
                    $class_attr = ' class="' . implode(' ', array_map('esc_attr', $classes)) . '"';

                    // Add text-wrap style if textBalance is set
                    $style_attr = '';
                    if (isset($block['attrs']['textBalance']) && $block['attrs']['textBalance']) {
                        $style_attr = ' style="text-wrap:balance"';
                    }

                    $block['innerHTML'] = '<h1' . $class_attr . $style_attr . '>' . esc_html($title) . '</h1>';
                    $block['innerContent'] = array($block['innerHTML']);

                    return true; // Found and filled
                }
            }

            // Recursively search innerBlocks
            if (!empty($block['innerBlocks'])) {
                if ($this->fill_first_h1_with_title($block['innerBlocks'], $title)) {
                    // H1 was found in a child - add metadata to parent if it's a section
                    if ($this->is_section_block($block)) {
                        if (!isset($block['attrs'])) {
                            $block['attrs'] = array();
                        }
                        if (!isset($block['attrs']['metadata'])) {
                            $block['attrs']['metadata'] = array();
                        }
                        $block['attrs']['metadata']['name'] = $title;
                    }

                    // Regenerate parent's innerContent array
                    $block['innerContent'] = $this->rebuild_parent_inner_content($block);
                    return true; // Found in nested blocks
                }
            }
        }

        return false; // Not found
    }

    /**
     * Rebuild innerContent array for a parent block with innerBlocks
     * 
     * @param array $block Block with innerBlocks.
     * @return array Rebuilt innerContent array.
     */
    private function rebuild_parent_inner_content($block)
    {
        if (empty($block['innerBlocks'])) {
            return array($block['innerHTML']);
        }

        // For pdm/section blocks, we should clean up the existing innerContent
        // rather than rebuilding from innerHTML (which might have old content)
        if ($block['blockName'] === 'pdm/section' && isset($block['innerContent']) && !empty($block['innerContent'])) {
            $inner_content = $block['innerContent'];

            // Strip trailing whitespace from all HTML fragments
            // This ensures no newlines appear before inner blocks
            foreach ($inner_content as $index => $content) {
                if ($content !== null && is_string($content)) {
                    $inner_content[$index] = rtrim($content);
                }
            }

            return $inner_content;
        }

        // For blocks with inner blocks, we need to split the innerHTML
        // For pdm/section blocks, the structure is typically:
        // <outer divs><content-wrapper>[inner blocks here]</content-wrapper></outer divs>

        $html = $block['innerHTML'];

        // Try to find where inner blocks should be inserted
        // For pdm/section, look for content-wrapper closing
        if ($block['blockName'] === 'pdm/section') {
            // Find the content-wrapper div opening and the final closing divs
            // The innerHTML should be like: <div...><div...><div class="content-wrapper"></div></div></div>
            if (preg_match('/^(.+<div[^>]*class="[^"]*content-wrapper[^"]*"[^>]*>)\s*(.*)(<\/div><\/div><\/div>)$/s', $html, $matches)) {
                $opening = $matches[1];  // No trailing whitespace
                $closing = $matches[3];

                // Build innerContent array: opening, nulls for each inner block, closing
                $inner_content = array($opening);
                foreach ($block['innerBlocks'] as $inner_block) {
                    $inner_content[] = null;
                }
                $inner_content[] = $closing;

                return $inner_content;
            }
        }

        // Fallback: preserve existing structure if we can't parse it
        if (isset($block['innerContent']) && !empty($block['innerContent'])) {
            return $block['innerContent'];
        }

        // Last resort: basic structure
        $inner_content = array($html);
        foreach ($block['innerBlocks'] as $inner_block) {
            $inner_content[] = null;
        }
        return $inner_content;
    }

    /**
     * Check if a block is a section block
     *
     * @param array $block Block to check.
     * @return bool True if section block.
     */
    private function is_section_block($block)
    {
        $section_block_types = array(
            'pdm/section',
            'core/group',
            'core/cover',
        );

        return in_array($block['blockName'], $section_block_types, true);
    }

    /**
     * Check if a section block is a hero section
     *
     * @param array $block Block to check.
     * @return bool True if hero section.
     */
    private function is_hero_section($block)
    {
        // Check for hero indicators:
        // - Has background image
        // - Has min-height
        // - Contains H1 heading

        if (isset($block['attrs']['imageURL']) || isset($block['attrs']['url'])) {
            return true;
        }

        if (isset($block['attrs']['useMinHeight']) && $block['attrs']['useMinHeight']) {
            return true;
        }

        // Check for H1 in inner blocks
        if (! empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                if ($inner_block['blockName'] === 'core/heading' && ($inner_block['attrs']['level'] ?? 2) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a section block is empty (only has empty placeholders)
     *
     * @param array $block Block to check.
     * @return bool True if empty.
     */
    private function is_empty_section_block($block)
    {
        if (empty($block['innerBlocks'])) {
            return true;
        }

        foreach ($block['innerBlocks'] as $inner_block) {
            // Check heading blocks
            if ($inner_block['blockName'] === 'core/heading') {
                $content = strip_tags($inner_block['innerHTML'] ?? '');
                if (! empty(trim($content))) {
                    return false; // Has content
                }
            }
            // Check paragraph blocks
            elseif ($inner_block['blockName'] === 'core/paragraph') {
                $content = strip_tags($inner_block['innerHTML'] ?? '');
                if (! empty(trim($content))) {
                    return false; // Has content
                }
            }
            // Recursively check nested blocks (for containers like media-and-content)
            elseif (! empty($inner_block['innerBlocks'])) {
                if (! $this->is_empty_section_block($inner_block)) {
                    return false;
                }
            }
            // If it has other block types with no innerBlocks, consider it not empty
            elseif (! in_array($inner_block['blockName'], array('core/heading', 'core/paragraph', ''), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find a section block to clone (prefer non-hero sections)
     *
     * @param array $section_blocks Section blocks to choose from.
     * @return array|null Section block to clone.
     */
    private function find_clonable_section($section_blocks)
    {
        // Prefer non-hero sections
        foreach ($section_blocks as $block) {
            if (! $this->is_hero_section($block)) {
                return $block;
            }
        }

        // Fall back to first section
        return $section_blocks[0] ?? null;
    }

    /**
     * Clone a section block and clear its content
     *
     * @param array $block Block to clone.
     * @return array Cloned block.
     */
    private function clone_section_block($block)
    {
        // Deep clone the block
        $cloned = unserialize(serialize($block));

        // Clear content from inner blocks
        if (! empty($cloned['innerBlocks'])) {
            $cloned['innerBlocks'] = $this->clear_block_content($cloned['innerBlocks']);
        }

        return $cloned;
    }

    /**
     * Clear content from blocks
     *
     * @param array $blocks Blocks to clear.
     * @return array Cleared blocks.
     */
    private function clear_block_content($blocks)
    {
        $cleared = array();

        foreach ($blocks as $block) {
            $cleared_block = $block;

            if ($block['blockName'] === 'core/heading' || $block['blockName'] === 'core/paragraph') {
                $cleared_block['innerHTML'] = '';
                $cleared_block['innerContent'] = array('');
            }

            if (! empty($block['innerBlocks'])) {
                $cleared_block['innerBlocks'] = $this->clear_block_content($block['innerBlocks']);
            }

            $cleared[] = $cleared_block;
        }

        return $cleared;
    }

    /**
     * Fill a section block with document data
     *
     * @param array $block   Section block.
     * @param array $section Section data.
     * @return array Filled block.
     */
    private function fill_section_block($block, $section)
    {
        if (empty($block['innerBlocks'])) {
            return $block;
        }

        // Check if this is an FAQ section with accordions
        if (isset($section['isFAQ']) && $section['isFAQ'] && !empty($section['accordions'])) {
            // FAQ section: Fill heading with FAQ title (text-align-center), then add accordions
            $filled_blocks = array();
            $heading_added = false;

            foreach ($block['innerBlocks'] as $inner_block) {
                // Fill the first empty heading with FAQ title (with text-align-center)
                if ($inner_block['blockName'] === 'core/heading' && !$heading_added) {
                    $existing_content = strip_tags($inner_block['innerHTML'] ?? '');
                    if (empty(trim($existing_content))) {
                        $level = $inner_block['attrs']['level'] ?? 2;
                        $heading_text = esc_html($section['heading'] ?? '');

                        // Build classes with text-align-center
                        $classes = array('wp-block-heading', 'has-text-align-center');

                        // Preserve existing classes
                        if (isset($inner_block['attrs']['className'])) {
                            $classes[] = $inner_block['attrs']['className'];
                        }
                        if (isset($inner_block['attrs']['fontSize'])) {
                            $classes[] = 'has-' . esc_attr($inner_block['attrs']['fontSize']) . '-font-size';
                        }

                        // Add textAlign to attrs
                        $inner_block['attrs']['textAlign'] = 'center';
                        $inner_block['innerHTML'] = '<h' . $level . ' class="' . implode(' ', array_map('esc_attr', $classes)) . '">' . $heading_text . '</h' . $level . '>';
                        $inner_block['innerContent'] = array($inner_block['innerHTML']);
                        $heading_added = true;
                    }
                    $filled_blocks[] = $inner_block;
                }
                // Skip empty paragraph blocks in FAQ sections
                elseif ($inner_block['blockName'] === 'core/paragraph') {
                    $existing_content = strip_tags($inner_block['innerHTML'] ?? '');
                    if (!empty(trim($existing_content))) {
                        // Keep non-empty paragraphs
                        $filled_blocks[] = $inner_block;
                    }
                    // Otherwise skip (don't add empty paragraphs)
                } else {
                    // Keep other blocks as-is
                    $filled_blocks[] = $inner_block;
                }
            }

            // Append accordion blocks after existing blocks
            $accordion_blocks = $this->create_accordion_blocks($section['accordions']);
            $block['innerBlocks'] = array_merge($filled_blocks, $accordion_blocks);

            // CRITICAL: Update innerContent to match innerBlocks
            // For section blocks, we need to preserve wrapper HTML
            $this->update_section_inner_content($block);
        } else {
            // Regular section filling
            $block['innerBlocks'] = $this->fill_inner_blocks($block['innerBlocks'], $section);

            // CRITICAL: Update innerContent to match innerBlocks
            $this->update_section_inner_content($block);
        }

        return $block;
    }

    /**
     * Update section block's innerContent to match innerBlocks count
     * Preserves the section wrapper HTML structure
     *
     * @param array $block Block to update (passed by reference)
     */
    private function update_section_inner_content(&$block)
    {
        if (empty($block['innerBlocks'])) {
            return;
        }

        // Get the section's innerHTML (full wrapper HTML)
        $html = $block['innerHTML'] ?? '';

        // For pdm/section blocks, the structure is:
        // <div class="wp-block-pdm-section..."><div class="section-flex-container..."><div class="content-wrapper">
        // We need to split it to insert innerBlocks inside content-wrapper

        // Find the content-wrapper div and split there
        if (preg_match('/(<div class="content-wrapper">)/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $split_pos = $matches[0][1] + strlen($matches[0][0]);
            $opening_html = substr($html, 0, $split_pos);
            $closing_html = substr($html, $split_pos);

            // Build innerContent: opening HTML + nulls for each innerBlock + closing HTML
            $block['innerContent'] = array($opening_html);
            foreach ($block['innerBlocks'] as $inner) {
                $block['innerContent'][] = null;
            }
            $block['innerContent'][] = $closing_html;
        } else {
            // Fallback: just use nulls (might not render properly but won't break)
            $block['innerContent'] = array();
            foreach ($block['innerBlocks'] as $inner) {
                $block['innerContent'][] = null;
            }
        }
    }

    /**
     * Update media-and-content block's innerContent to match innerBlocks count
     * Preserves the media-and-content wrapper HTML structure
     *
     * @param array $block Block to update (passed by reference)
     */
    private function update_media_content_inner_content(&$block)
    {
        if (empty($block['innerBlocks'])) {
            return;
        }

        // Get the block's innerHTML
        $html = $block['innerHTML'] ?? '';

        // For pdm/media-and-content blocks, the structure is:
        // <div class="wp-block-pdm-media-and-content..."><div class="mc-media">...</div><div class="mc-content">
        // We need to split at mc-content to insert innerBlocks there

        if (preg_match('/(<div class="mc-content">)/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $split_pos = $matches[0][1] + strlen($matches[0][0]);
            $opening_html = substr($html, 0, $split_pos);
            $closing_html = substr($html, $split_pos);

            // Build innerContent: opening HTML + nulls for each innerBlock + closing HTML
            $block['innerContent'] = array($opening_html);
            foreach ($block['innerBlocks'] as $inner) {
                $block['innerContent'][] = null;
            }
            $block['innerContent'][] = $closing_html;
        } else {
            // Fallback: just use nulls
            $block['innerContent'] = array();
            foreach ($block['innerBlocks'] as $inner) {
                $block['innerContent'][] = null;
            }
        }
    }

    /**
     * Fill inner blocks with section data
     *
     * @param array $blocks  Inner blocks.
     * @param array $section Section data.
     * @return array Filled blocks.
     */
    private function fill_inner_blocks($blocks, $section)
    {
        $filled = array();
        $heading_filled = false;
        $content_filled = false;

        foreach ($blocks as $block) {
            $filled_block = $block;
            $skip_block = false; // Flag to track if we should skip adding this block

            // Fill heading - only if it's empty
            if ($block['blockName'] === 'core/heading' && ! $heading_filled) {
                // Check if heading already has content
                $existing_content = strip_tags($block['innerHTML'] ?? '');
                $is_empty = empty(trim($existing_content));

                if ($is_empty) {
                    $heading_text = $section['heading'] ?? '';
                    // If section has empty heading (intro section), skip this heading block
                    if (empty($heading_text)) {
                        $heading_filled = true;
                        $skip_block = true; // Mark to skip this block
                    }

                    if (! empty($heading_text)) {
                        $level = $block['attrs']['level'] ?? 2;

                        // Build classes array
                        $classes = array('wp-block-heading');

                        // Add custom className if present
                        if (isset($block['attrs']['className'])) {
                            $classes[] = $block['attrs']['className'];
                        }

                        // Add text alignment
                        if (isset($block['attrs']['textAlign'])) {
                            $classes[] = 'has-text-align-' . esc_attr($block['attrs']['textAlign']);
                        }

                        // Add font size
                        if (isset($block['attrs']['fontSize'])) {
                            $classes[] = 'has-' . esc_attr($block['attrs']['fontSize']) . '-font-size';
                        }

                        // Add text color
                        if (isset($block['attrs']['textColor'])) {
                            $classes[] = 'has-' . esc_attr($block['attrs']['textColor']) . '-color';
                            $classes[] = 'has-text-color';
                        }

                        $class_attr = ' class="' . implode(' ', array_map('esc_attr', $classes)) . '"';
                        $filled_block['innerHTML'] = '<h' . $level . $class_attr . '>' . esc_html($heading_text) . '</h' . $level . '>';
                        $filled_block['innerContent'] = array($filled_block['innerHTML']);
                        $heading_filled = true;
                    }
                } else {
                    // Heading has content, keep it and mark as filled
                    $heading_filled = true;
                }
            }
            // Fill paragraph - only if it's empty
            elseif ($block['blockName'] === 'core/paragraph' && ! $content_filled) {
                // Check if paragraph already has content
                $existing_content = strip_tags($block['innerHTML'] ?? '');
                $is_empty = empty(trim($existing_content));

                if ($is_empty) {
                    $content = $section['content'] ?? '';
                    if (! empty($content)) {
                        // Clean up the content
                        $content = wp_kses_post($content);

                        // Parse all HTML blocks from content (paragraphs, lists, etc.)
                        $content_blocks = $this->parse_html_content_blocks($content);

                        if (!empty($content_blocks)) {
                            // Fill first paragraph block
                            if ($content_blocks[0]['blockName'] === 'core/paragraph') {
                                $filled_block['innerHTML'] = $content_blocks[0]['innerHTML'];
                                $filled_block['innerContent'] = array($content_blocks[0]['innerHTML']);
                                $filled[] = $filled_block;
                            } else {
                                // First block is not a paragraph (e.g., a list), add it as-is
                                $filled[] = $content_blocks[0];
                            }

                            // Add remaining blocks
                            for ($i = 1; $i < count($content_blocks); $i++) {
                                $filled[] = $content_blocks[$i];
                            }

                            $content_filled = true;
                            continue; // We already added filled_block, skip the normal add
                        } else {
                            // No blocks found, treat as single paragraph
                            if (strip_tags($content) !== $content) {
                                $filled_block['innerHTML'] = $content;
                            } else {
                                $filled_block['innerHTML'] = '<p>' . $content . '</p>';
                            }
                            $filled_block['innerContent'] = array($filled_block['innerHTML']);
                            $content_filled = true;
                        }
                    }
                } else {
                    // Paragraph has content, keep it and mark as filled
                    $content_filled = true;
                }
            }
            // Recursively fill nested blocks (for pdm/media-and-content, etc.)
            elseif (! empty($block['innerBlocks'])) {
                $filled_block['innerBlocks'] = $this->fill_inner_blocks($block['innerBlocks'], $section);

                // Update innerContent for blocks with nested content (like media-and-content)
                if ($filled_block['blockName'] === 'pdm/media-and-content') {
                    $this->update_media_content_inner_content($filled_block);
                }
            }

            // Only add block if not marked to skip
            if (!$skip_block) {
                $filled[] = $filled_block;
            }
        }

        return $filled;
    }

    /**
     * Parse HTML content into WordPress blocks (paragraphs, lists, etc.)
     *
     * @param string $html HTML content to parse.
     * @return array Array of WordPress blocks.
     */
    private function parse_html_content_blocks($html)
    {
        $blocks = array();

        // Parse HTML using DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);

        // Add UTF-8 meta tag to handle encoding correctly
        $html_with_meta = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($html_with_meta);
        libxml_clear_errors();

        // Get body node
        $body = $dom->getElementsByTagName('body')->item(0);

        if (!$body) {
            return $blocks;
        }

        // Process each top-level child node
        foreach ($body->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag_name = strtolower($node->nodeName);

            // Handle paragraphs
            if ($tag_name === 'p') {
                $innerHTML = $dom->saveHTML($node);
                $blocks[] = array(
                    'blockName' => 'core/paragraph',
                    'attrs' => array(),
                    'innerBlocks' => array(),
                    'innerHTML' => $innerHTML,
                    'innerContent' => array($innerHTML),
                );
            }
            // Handle unordered lists
            elseif ($tag_name === 'ul') {
                $innerHTML = $dom->saveHTML($node);
                $blocks[] = array(
                    'blockName' => 'core/list',
                    'attrs' => array('ordered' => false),
                    'innerBlocks' => array(),
                    'innerHTML' => $innerHTML,
                    'innerContent' => array($innerHTML),
                );
            }
            // Handle ordered lists
            elseif ($tag_name === 'ol') {
                $innerHTML = $dom->saveHTML($node);
                $blocks[] = array(
                    'blockName' => 'core/list',
                    'attrs' => array('ordered' => true),
                    'innerBlocks' => array(),
                    'innerHTML' => $innerHTML,
                    'innerContent' => array($innerHTML),
                );
            }
            // Handle headings (h3, h4, h5, h6) - though these shouldn't appear in section content
            elseif (in_array($tag_name, array('h3', 'h4', 'h5', 'h6'))) {
                $level = intval(substr($tag_name, 1));
                $innerHTML = $dom->saveHTML($node);
                $blocks[] = array(
                    'blockName' => 'core/heading',
                    'attrs' => array('level' => $level),
                    'innerBlocks' => array(),
                    'innerHTML' => $innerHTML,
                    'innerContent' => array($innerHTML),
                );
            }
            // Handle other block-level elements as paragraphs
            else {
                $innerHTML = $dom->saveHTML($node);
                if (trim(strip_tags($innerHTML))) {
                    $blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $innerHTML,
                        'innerContent' => array($innerHTML),
                    );
                }
            }
        }


        return $blocks;
    }

    /**
     * Fill simple blocks when no section blocks found
     *
     * @param array $blocks   Blocks to fill.
     * @param array $sections Sections data.
     * @return array Filled blocks.
     */
    private function fill_simple_blocks($blocks, $sections)
    {
        // Simple fallback - just fill what we can find sequentially
        $section_index = 0;

        foreach ($blocks as &$block) {
            if ($section_index >= count($sections)) {
                break;
            }

            if ($block['blockName'] === 'core/heading' && empty(strip_tags($block['innerHTML'] ?? ''))) {
                $level = $block['attrs']['level'] ?? 2;
                $block['innerHTML'] = '<h' . $level . '>' . esc_html($sections[$section_index]['heading'] ?? '') . '</h' . $level . '>';
                $block['innerContent'] = array($block['innerHTML']);
                $section_index++;
            } elseif (! empty($block['innerBlocks'])) {
                $block['innerBlocks'] = $this->fill_simple_blocks($block['innerBlocks'], array_slice($sections, $section_index));
            }
        }

        return $blocks;
    }

    /**
     * Create accordion blocks from FAQ data
     *
     * @param array $accordions Accordion items with title and content.
     * @return array Accordion blocks.
     */
    private function create_accordion_blocks($accordions)
    {
        $accordion_html_parts = array();

        foreach ($accordions as $accordion) {
            $title = $accordion['title'] ?? '';
            $content = wp_kses_post($accordion['content'] ?? '');

            // Content already has HTML tags (like <p>), don't wrap again
            // Just strip the outer <p> tags if they exist to avoid double wrapping
            $content = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($content));

            // Build complete accordion block comment + HTML
            // Use proper JSON escaping for the iconColor attribute (need extra backslashes for PHP string processing)
            $icon_color_json = 'var(\\\\u002d\\\\u002dwp\\\\u002d\\\\u002dpreset\\\\u002d\\\\u002dcolor\\\\u002d\\\\u002dbase)';
            $title_json = addcslashes($title, '"\\');

            $accordion_serialized = '<!-- wp:pdm/accordion {"accordionTitle":"' . $title_json . '","iconColor":"' . $icon_color_json . '"} -->' . "\n" .
                '<details style="--pdm-icon-size:25px;--pdm-icon-color:var(--wp--preset--color--base)" class="wp-block-pdm-accordion" name="pdm-accordion">' .
                '<summary class="accord-title icon-position-right">' .
                '<span class="accord-title-text">' . esc_html($title) . '</span>' .
                '<span class="icon-open "><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="currentColor" d="M352 128C352 110.3 337.7 96 320 96C302.3 96 288 110.3 288 128L288 288L128 288C110.3 288 96 302.3 96 320C96 337.7 110.3 352 128 352L288 352L288 512C288 529.7 302.3 544 320 544C337.7 544 352 529.7 352 512L352 352L512 352C529.7 352 544 337.7 544 320C544 302.3 529.7 288 512 288L352 288L352 128z"></path></svg></span>' .
                '<span class="icon-close "><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="currentColor" d="M96 320C96 302.3 110.3 288 128 288L512 288C529.7 288 544 302.3 544 320C544 337.7 529.7 352 512 352L128 352C110.3 352 96 337.7 96 320z"></path></svg></span>' .
                '</summary>' .
                '<div class="accord-panel"><!-- wp:paragraph {"placeholder":"Accordion Content"} -->' . "\n" .
                '<p>' . $content . '</p>' . "\n" .
                '<!-- /wp:paragraph --></div></details>' . "\n" .
                '<!-- /wp:pdm/accordion -->';

            $accordion_html_parts[] = $accordion_serialized;
        }

        // Join all accordion HTML with blank lines
        $all_accordions_html = implode("\n\n", $accordion_html_parts);

        // Build complete accordions container HTML
        $opening_html = '<div class="accordion-container"><div class="wp-block-pdm-accordions pdm-accordions accord-columns-1" style="--pdm-toggle-bg:var(--wp--preset--color--primary);--pdm-toggle-color:var(--wp--preset--color--base);--pdm-icon-color:var(--wp--preset--color--base);--pdm-panel-bg:var(--wp--preset--color--base);--pdm-panel-color:var(--wp--preset--color--contrast);--accordion-max-width:800px;--accordion-radius:20px 20px 20px 20px;--pdm-bottom-border:none;--pdm-shadow:var(--shadow)">';
        $closing_html = '</div></div>';

        $full_html = $opening_html . $all_accordions_html . $closing_html;

        // Create accordions container with NO innerBlocks (everything pre-serialized)
        $accordions_block = array(
            'blockName' => 'pdm/accordions',
            'attrs' => array(),
            'innerBlocks' => array(),
            'innerHTML' => $full_html,
            'innerContent' => array($full_html),
        );

        return array($accordions_block);
    }
}
