<?php

namespace Hostinger\Reach\Jobs;

use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Repositories\CartRepository;

class CleanupCartsJob extends AbstractBatchedJob implements RecurringJobInterface {

    public const JOB_NAME             = 'cleanup_carts';
    public const JOB_INTERVAL_SECONDS = DAY_IN_SECONDS;
    protected CartRepository $cart_repository;

    public function __construct( ActionScheduler $action_scheduler, ReachApiHandler $reach_api_handler, CartRepository $cart_repository ) {
        parent::__construct( $action_scheduler, $reach_api_handler );
        $this->cart_repository = $cart_repository;
    }

    public function get_name(): string {
        return self::JOB_NAME;
    }

    public function schedule( array $args = array() ): void {
        // $this->schedule_create_batch_action( 1, $args );
    }

    public function can_schedule( array $args = array() ): bool {
        return ! $this->is_running( $args );
    }

    public function get_interval(): int {
        return self::JOB_INTERVAL_SECONDS;
    }

    protected function get_batch_size(): int {
        return 20;
    }

    protected function get_batch( int $batch_number, array $args ): array {
        return $this->cart_repository->old_carts( $this->get_batch_size(), array( 'hash' ) );
    }

    protected function process_items( array $args = array() ): void {
        $items = $args['items'] ?? array();

        if ( empty( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            $this->cart_repository->delete( $item['hash'] );
        }
    }
}
