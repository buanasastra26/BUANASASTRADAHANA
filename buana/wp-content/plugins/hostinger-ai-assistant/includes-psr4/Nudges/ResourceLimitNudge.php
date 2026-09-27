<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;
use Hostinger\AiAssistant\Hosting\PlanRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ResourceLimitNudge extends AbstractNudge {
    private const CAMPAIGN_NAME = 'wordpress-resource-limit';
    private const DEDUP_KEY     = 'resource-limit';
    private const DISK_WARN     = 90;
    private const INODES_WARN   = 90;
    private const DB_WARN       = 80;

    public function __construct(
        private PlanRepository $plan_repository,
        private HpanelUrlBuilder $url_builder
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function get_priority(): int {
        return 80;
    }

    protected function build_reach_out(): ?ReachOut {
        $plan = $this->plan_repository->get_plan_details();

        if ( $plan === null ) {
            return null;
        }

        $constrained = $this->get_constrained_resource( $plan );

        if ( $constrained === null ) {
            return null;
        }

        $original_domain    = $this->get_field( $plan, array( 'original_domain', 'originalDomain' ) );
        $domain             = is_string( $original_domain ) ? $this->url_builder->normalize_domain( $original_domain ) : '';
        $resource_usage_url = $this->url_builder->resource_usage_url( $domain );
        $upgrade_url        = $this->url_builder->upgrade_url( $domain );

        return new ReachOut(
            $this->build_message( $constrained ),
            self::DEDUP_KEY,
            $this->build_buttons( $resource_usage_url, $upgrade_url )
        );
    }

    private function get_constrained_resource( array $plan ): ?array {
        $usages = $this->get_field( $plan, array( 'currentUsages', 'current_usages' ) );
        $limits = $this->get_field( $plan, array( 'planLimits', 'plan_limits' ) );
        $db     = $this->get_field( $plan, array( 'database' ) );

        $usages = is_array( $usages ) ? $usages : array();
        $limits = is_array( $limits ) ? $limits : array();
        $db     = is_array( $db ) ? $db : array();

        $candidates = array_filter(
            array(
                $this->build_candidate( 'disk', __( 'disk space', 'hostinger-ai-assistant' ), $usages['disk'] ?? null, $limits['disk'] ?? null, self::DISK_WARN ),
                $this->build_candidate( 'inodes', __( 'files (inodes)', 'hostinger-ai-assistant' ), $usages['inodes'] ?? null, $limits['inodes'] ?? null, self::INODES_WARN ),
                $this->build_candidate( 'database', __( 'database storage', 'hostinger-ai-assistant' ), $this->get_field( $db, array( 'diskUsageMb', 'disk_usage_mb' ) ), $this->get_field( $db, array( 'maxSizeMb', 'max_size_mb' ) ), self::DB_WARN ),
            )
        );

        if ( empty( $candidates ) ) {
            return null;
        }

        usort(
            $candidates,
            static fn( array $a, array $b ): int => $b['percent'] <=> $a['percent']
        );

        return array_values( $candidates )[0];
    }

    private function build_candidate( string $key, string $label, mixed $usage, mixed $limit, int $threshold ): ?array {
        $percent = $this->percent( $this->to_float( $usage ), $this->to_float( $limit ) );

        if ( $percent < $threshold ) {
            return null;
        }

        return array(
            'key'     => $key,
            'label'   => $label,
            'percent' => $percent,
        );
    }

    private function get_field( array $source, array $keys ): mixed {
        foreach ( $keys as $key ) {
            if ( isset( $source[ $key ] ) ) {
                return $source[ $key ];
            }
        }

        return null;
    }

    private function to_float( mixed $value ): ?float {
        return is_numeric( $value ) ? (float) $value : null;
    }

    private function percent( ?float $used, ?float $limit ): ?int {
        if ( $used === null || $limit === null || $limit <= 0 ) {
            return null;
        }

        return (int) floor( $used / $limit * 100 );
    }

    private function build_message( array $hit ): string {
        return sprintf(
            /* translators: 1: resource name (e.g. disk space), 2: usage percentage. */
            __( 'Heads-up — your website is using %2$d%% of its %1$s on your current hosting plan. Upgrade your plan to avoid running out and keep everything running smoothly.', 'hostinger-ai-assistant' ),
            $hit['label'],
            $hit['percent']
        );
    }

    private function build_buttons( string $resource_usage_url, string $upgrade_url ): array {
        return array(
            array(
                'id'   => 'upgrade-hosting-plan',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Upgrade', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $upgrade_url ),
                ),
            ),
            array(
                'id'   => 'manage-resources',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Manage resources', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $resource_usage_url ),
                ),
            ),
        );
    }
}
