<?php

namespace Hostinger\Reach\Jobs;

use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Integrations\ImportManager;

class ImportJob extends AbstractBatchedJob {

    public const JOB_NAME = 'import';

    public ImportManager $import_manager;

    public function __construct( ActionScheduler $action_scheduler, ReachApiHandler $reach_api_handler, ImportManager $import_manager ) {
        parent::__construct( $action_scheduler, $reach_api_handler );
        $this->import_manager = $import_manager;
    }

    public function get_name(): string {
        return self::JOB_NAME;
    }

    protected function get_batch_size(): int {
        return 100;
    }

    protected function get_batch_limit_per_job(): int {
        return 100;
    }

    protected function get_batch( int $batch_number, array $args ): array {
        $limit       = $this->get_batch_size();
        $integration = $args['integration'];
        $form_id     = $args['form_id'] ?? null;
        return $this->import_manager->get_contacts( $integration, $form_id, $limit, ( $batch_number - 1 ) * $limit );
    }

    protected function process_items( array $args = array() ): void {
        $items = $args['items'] ?? array();

        if ( empty( $items ) ) {
            return;
        }

        $this->reach_api_handler->post_import_contacts( $items );
    }

    protected function handle_complete( int $final_batch_number, array $args ): void {
        parent::handle_complete( $final_batch_number, $args );
        $integration = $args['integration'];
        $form_id     = $args['form_id'] ?? null;
        $this->import_manager->set_completed_import( $integration, array( $form_id ) );
    }
}
