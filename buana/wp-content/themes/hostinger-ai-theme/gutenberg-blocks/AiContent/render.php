<?php
/**
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

namespace Hostinger\AiTheme\GutenbergBlocks\AiContent;

// Just output the generated content directly, allowing HTML and images
echo wp_kses_post($attributes['generatedContent'] ?? '');