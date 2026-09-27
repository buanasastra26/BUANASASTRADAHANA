<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Booking;

class Booking {
    public function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_menu', [ $this, 'maybe_add_admin_menu' ] );
    }

    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( __DIR__ . '/block.json', [
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function register_rest_routes() {
        register_rest_route( 'hostinger/v1', '/booking-submissions', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_booking_submission' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_booking_submission( $request ) {
        $data = $request->get_json_params();

        if ( empty( $data ) ) {
            return new \WP_Error( 'invalid_data', 'Invalid data provided', [ 'status' => 400 ] );
        }

        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) ) {
                $data[$key] = sanitize_text_field( $value );
            }
        }

        $submissions   = get_option( 'hostinger_booking_submissions', [] );
        $submissions[] = [
            'data'      => $data,
            'timestamp' => current_time( 'mysql' ),
        ];

        update_option( 'hostinger_booking_submissions', $submissions );

        return new \WP_REST_Response( [
            'message' => 'Booking submitted successfully',
            'status'  => 'success',
        ], 200 );
    }

    public function maybe_add_admin_menu() {
        $submissions = get_option( 'hostinger_booking_submissions', [] );

        if ( ! empty( $submissions ) ) {
            add_menu_page(
                'Bookings',
                'Bookings',
                'manage_options',
                'hostinger-bookings',
                [ $this, 'render_admin_page' ],
                'dashicons-calendar-alt',
                30
            );
        }
    }

    public function render_admin_page() {
        $submissions = get_option( 'hostinger_booking_submissions', [] ); ?>
        <div class="wrap">
            <h1><?php echo __( 'Bookings', 'hostinger-ai-theme' ); ?></h1>
            <?php
            if ( empty( $submissions ) ) : ?>
                <p><?php echo __( 'No bookings yet.', 'hostinger-ai-theme' ); ?></p>
            <?php
            else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php echo __( 'Date', 'hostinger-ai-theme' ); ?></th>
                        <?php
                        // Get all unique field names from submissions
                        $fields = [];
                        foreach ( $submissions as $submission ) {
                            foreach ( $submission['data'] as $field => $value ) {
                                $fields[$field] = true;
                            }
                        }
                        foreach ( $fields as $field => $value ) :
                            ?>
                            <th><?php echo esc_html( ucfirst( $field ) ); ?></th>
                        <?php
                        endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ( $submissions as $submission ) : ?>
                        <tr>
                            <td><?php
                                echo esc_html( $submission['timestamp'] ); ?></td>
                            <?php
                            foreach ( $fields as $field => $value ) : ?>
                                <td><?php
                                    echo esc_html( $submission['data'][$field] ?? '' ); ?></td>
                            <?php
                            endforeach; ?>
                        </tr>
                    <?php
                    endforeach; ?>
                    </tbody>
                </table>
            <?php
            endif; ?>
        </div>
        <?php
    }

    public function render_block( $attributes, $content, $block ) {
        ob_start();
        require __DIR__ . '/render.php';

        return ob_get_clean();
    }
}