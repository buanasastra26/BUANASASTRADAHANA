<?php

namespace Hostinger\AiTheme\Cli;

use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\SiteApplier;
use InvalidArgumentException;
use Throwable;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

class ApplySiteCommand {

    private SiteApplier $applier;

    public function __construct( SiteApplier $applier ) {
        $this->applier = $applier;
    }

    /**
     * Applies a captured website envelope to this install.
     *
     * ## OPTIONS
     *
     * --file=<path>
     * : Path to the envelope JSON file (as captured from POST /wordpress/generate-website).
     *
     * ## EXAMPLES
     *
     *     # Apply a Gutenberg sample envelope.
     *     $ wp hostinger-ai apply-site --file=path/to/envelope.json
     *
     * @when after_wp_load
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $file = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';

        if ( $file === '' ) {
            WP_CLI::error( 'Pass --file=<path> to an envelope JSON file.' );
            return;
        }

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            WP_CLI::error( sprintf( 'Envelope file not found or unreadable: %s', $file ) );
            return;
        }

        $raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( $raw === false ) {
            WP_CLI::error( 'Failed to read the envelope file.' );
            return;
        }

        $envelope = json_decode( $raw, true );
        if ( ! is_array( $envelope ) ) {
            WP_CLI::error( 'Envelope file is not valid JSON.' );
            return;
        }

        if ( ! $this->ensure_current_user() ) {
            WP_CLI::error( 'No WordPress user is available to own the generated content.' );
            return;
        }

        try {
            $result = $this->applier->apply( $envelope );
        } catch ( InvalidArgumentException $e ) {
            WP_CLI::error( sprintf( 'Unsupported envelope: %s', $e->getMessage() ) );
            return;
        } catch ( Throwable $e ) {
            WP_CLI::error( sprintf( 'Apply failed: %s', $e->getMessage() ) );
            return;
        }

        foreach ( $result['warnings'] ?? array() as $warning ) {
            WP_CLI::warning( (string) $warning );
        }

        WP_CLI::success(
            sprintf(
                'Applied envelope: %d pages, front #%d, menu #%d, %d products, %d posts, %d assets sideloaded.',
                (int) $result['pages_created'],
                (int) $result['front_page_id'],
                (int) $result['menu_id'],
                (int) $result['products_created'],
                (int) $result['posts_created'],
                (int) $result['assets_sideloaded']
            )
        );
    }

    private function ensure_current_user(): bool {
        if ( get_current_user_id() > 0 ) {
            return true;
        }

        $administrator_id = Helper::oldest_administrator_id();

        if ( $administrator_id < 1 ) {
            return false;
        }

        wp_set_current_user( $administrator_id );

        return true;
    }
}
