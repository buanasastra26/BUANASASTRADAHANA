<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class FontDebugSwitcher {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'render_panel' ] );
    }

    public function render_panel(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $theme_fonts    = wp_get_global_settings()['typography']['fontFamilies']['theme'] ?? [];
        $heading_font   = get_option( 'hostinger_ai_font', '' );
        $body_override  = get_option( 'hostinger_ai_body_font_override', '' );
        $endpoint       = esc_url( rest_url( HOSTINGER_AI_WEBSITES_REST_API_BASE . '/set-fonts' ) );
        $nonce          = wp_create_nonce( 'wp_rest' );

        ?>
        <div id="hostinger-font-debug" style="position:fixed;bottom:20px;right:20px;z-index:99999;background:#fff;border:1px solid #ddd;padding:16px;border-radius:8px;font-family:sans-serif;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:260px;line-height:1.4">
            <strong style="display:block;margin-bottom:12px;font-size:14px">Font Debugger</strong>

            <label style="display:block;margin-bottom:8px">
                Heading font<br>
                <select id="hfd-heading" style="width:100%;margin-top:4px;padding:4px 6px;font-size:13px">
                    <?php foreach ( $theme_fonts as $font ) : ?>
                        <option value="<?php echo esc_attr( $font['fontFamily'] ); ?>"<?php selected( $font['fontFamily'], $heading_font ); ?>>
                            <?php echo esc_html( $font['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label style="display:block;margin-bottom:12px">
                Body font<br>
                <select id="hfd-body" style="width:100%;margin-top:4px;padding:4px 6px;font-size:13px">
                    <option value="">Auto (from combination)</option>
                    <?php foreach ( $theme_fonts as $font ) : ?>
                        <option value="<?php echo esc_attr( $font['fontFamily'] ); ?>"<?php selected( $font['fontFamily'], $body_override ); ?>>
                            <?php echo esc_html( $font['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button onclick="hfdApply()" style="width:100%;padding:7px 0;background:#673de6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:600">Apply &amp; Reload</button>
            <div id="hfd-status" style="margin-top:8px;font-size:12px;color:#c00;min-height:16px"></div>
        </div>

        <script>
        function hfdApply() {
            var heading = document.getElementById('hfd-heading').value;
            var body    = document.getElementById('hfd-body').value;
            var status  = document.getElementById('hfd-status');

            var data = { heading_font: heading };
            if ( body ) {
                data.body_font = body;
            }

            status.textContent = 'Applying\u2026';

            fetch( <?php echo wp_json_encode( $endpoint ); ?>, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   <?php echo wp_json_encode( $nonce ); ?>
                },
                body: JSON.stringify( data )
            } )
            .then( function( r ) { return r.json(); } )
            .then( function( d ) {
                if ( d.success ) {
                    location.reload();
                } else {
                    status.textContent = d.message || 'Error applying fonts.';
                }
            } )
            .catch( function() {
                status.textContent = 'Request failed.';
            } );
        }
        </script>
        <?php
    }

    private function is_enabled(): bool {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        return isset( $_GET['font_debug'] ) && $_GET['font_debug'] === '1';
    }
}
