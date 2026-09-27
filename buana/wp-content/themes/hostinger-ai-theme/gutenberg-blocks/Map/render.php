<?php
/**
 * @var array $attributes Block attributes.
 */

namespace Hostinger\AiTheme\GutenbergBlocks\Map;

$src = isset( $attributes['src'] ) ? (string) $attributes['src'] : '';

if ( $src === '' ) {
    return;
}

$height = isset( $attributes['height'] ) ? (int) $attributes['height'] : 450;
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <iframe
        src="<?php echo esc_url( $src ); ?>"
        height="<?php echo esc_attr( (string) $height ); ?>"
        style="width: 100%; min-width: 100%; border:0;"
        allowfullscreen=""
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
    ></iframe>
</div>
