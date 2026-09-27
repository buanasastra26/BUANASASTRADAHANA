<?php

namespace Hostinger\AiTheme\Rest;

use Hostinger\AiTheme\Admin\GutenbergPreferences;
use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Builder\GenerationClient;
use Hostinger\AiTheme\Builder\GenerationJobStore;
use Hostinger\AiTheme\Builder\GenerationState;
use Hostinger\AiTheme\Builder\Helper;
use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Builder\RequestDetacher;
use Hostinger\AiTheme\Builder\SiteApplier;
use Hostinger\AiTheme\Builder\SoftwareIdTrait;
use Hostinger\AiTheme\Builder\WooBuilder;
use Hostinger\AiTheme\Constants\ApiRoutes;
use Hostinger\AiTheme\Constants\GenerationConstant;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\AiTheme\Versions\VersionManager;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use InvalidArgumentException;
use Throwable;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class GenerationRoutes {
    use SoftwareIdTrait;

    public const APPLY_LOCK_OPTION = 'hostinger_ai_generation_apply_lock';

    private const LOG_MESSAGE_LIMIT = 200;

    private const UPSTREAM_IN_PROGRESS = 'in-progress';

    private const UPSTREAM_COMPLETED = 'completed';

    private const UPSTREAM_FAILED = 'failed';

    private const EDITORS = array( 'gutenberg', 'elementor' );

    // Unknown types degrade to `default` instead of failing the request.
    private const WEBSITE_TYPES = array(
        'business',
        'online-store',
        'booking',
        'blog',
        'portfolio',
        'landing-page',
        'affiliate-marketing',
        'other',
        'default',
    );

    private const DEFAULT_WEBSITE_TYPE = 'default';

    // Abandoned claims after this long without a heartbeat; a start does not clear a claim still in flight.
    private const APPLY_LOCK_TTL = 180;

    private const GENERATION_FAILED_REASON = 'The website generation failed upstream.';

    private const JOB_TIMEOUT_REASON = 'Website generation timed out.';

    private const APPLY_UNFINISHED_REASON = 'Applying the generated website did not finish (last step: %s).';

    private const CLAIM_TAKEN = 'taken';

    private const CLAIM_HELD = 'held';

    private const CLAIM_ERROR = 'error';

    private const AMPLITUDE_EVENT_CREATED = 'wordpress.ai_builder.created';

    private GenerationClient $generation_client;

    private SiteApplier $applier;

    private RequestClient $wh_api_client;

    private ElementorBuilder $elementor_builder;

    private WooBuilder $woo_builder;

    private AffiliateBuilder $affiliate_builder;

    private GenerationJobStore $jobs;

    private RequestDetacher $request_detacher;

    public function __construct(
        GenerationClient $generation_client,
        SiteApplier $applier,
        RequestClient $wh_api_client,
        ?ElementorBuilder $elementor_builder = null,
        ?WooBuilder $woo_builder = null,
        ?AffiliateBuilder $affiliate_builder = null,
        ?GenerationJobStore $jobs = null,
        ?RequestDetacher $request_detacher = null
    ) {
        $this->generation_client = $generation_client;
        $this->applier           = $applier;
        $this->wh_api_client     = $wh_api_client;
        $this->elementor_builder = $elementor_builder ?? new ElementorBuilder();
        $this->woo_builder       = $woo_builder ?? new WooBuilder( new ImageManager() );
        $this->affiliate_builder = $affiliate_builder ?? new AffiliateBuilder();
        $this->jobs              = $jobs ?? new GenerationJobStore();
        $this->request_detacher  = $request_detacher ?? new RequestDetacher();
    }

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'v2/generate-website',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'generate_website' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'v2/generation-status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'generation_status' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'v2/suggest-pages',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'suggest_pages' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_WEBSITES_REST_API_BASE,
            'v2/generation-cancel',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'cancel_generation' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    public function cancel_generation( WP_REST_Request $request ): WP_REST_Response {
        $job   = $this->jobs->get();
        $phase = (string) ( $job['phase'] ?? '' );

        $apply_in_flight = GenerationJobStore::PHASE_APPLYING === $phase && ! $this->apply_claim_abandoned();
        $start_in_flight = GenerationJobStore::PHASE_STARTING === $phase && ! $this->jobs->start_claim_abandoned();

        if ( $apply_in_flight || $start_in_flight ) {
            $job['cancel_requested'] = true;
            $this->jobs->save( $job );

            return $this->status_response( 'cancelled', $job );
        }

        $this->jobs->delete();
        $this->jobs->force_release_start();
        delete_option( self::APPLY_LOCK_OPTION );
        GenerationState::reset();

        return $this->status_response( 'cancelled', $job );
    }

    public function generate_website( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $description = $this->get_description( $request );
        $brand_name  = $this->resolve_brand_name( $request );

        if ( $description === '' || $brand_name === '' ) {
            return new WP_Error(
                'data_invalid',
                __( 'Description and brand name are required.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::BAD_REQUEST )
            );
        }

        $pages = $this->resolve_pages( $request );

        if ( is_wp_error( $pages ) ) {
            return $pages;
        }

        if ( ! $this->apply_claim_abandoned() ) {
            return new WP_Error(
                'generation_apply_in_progress',
                __( 'The generated website is currently being applied.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::CONFLICT )
            );
        }

        if ( $this->jobs->is_live( $this->jobs->get() ) ) {
            return $this->in_flight_response();
        }

        $request_key = wp_generate_uuid4();
        $claim       = $this->jobs->claim_start( $request_key );

        if ( GenerationJobStore::CLAIM_ERROR === $claim ) {
            return $this->service_unavailable( 'Could not claim the generation start' );
        }

        if ( GenerationJobStore::CLAIM_HELD === $claim ) {
            return $this->in_flight_response();
        }

        $this->jobs->save(
            array(
                'request_key' => $request_key,
                'phase'       => GenerationJobStore::PHASE_STARTING,
                'started_at'  => time(),
            )
        );

        try {
            return $this->start_claimed_generation( $request, $request_key, $brand_name, $description, $pages );
        } finally {
            $this->jobs->release_start( $request_key );
        }
    }

    private function still_owns_start_claim( string $request_key ): bool {
        return ( $this->jobs->start_claim()['request_key'] ?? null ) === $request_key;
    }

    private function start_claimed_generation(
        WP_REST_Request $request,
        string $request_key,
        string $brand_name,
        string $description,
        ?array $pages
    ): WP_REST_Response|WP_Error {
        $software_id = $this->get_software_id();

        if ( empty( $software_id ) ) {
            $this->jobs->delete();

            return $this->service_unavailable( 'Software ID not available' );
        }

        $editor        = $this->resolve_editor( $request );
        $website_types = $this->resolve_website_types( $request );

        $this->save_generation_options( $brand_name, $website_types, $description, $this->resolve_language( $request ) );

        $plugin_error = $this->enable_required_plugins( $website_types );

        if ( is_wp_error( $plugin_error ) ) {
            if ( ! $this->still_owns_start_claim( $request_key ) ) {
                return $this->in_flight_response();
            }

            $this->jobs->delete();

            return $plugin_error;
        }

        $this->jobs->touch_start_claim( $request_key );

        // Legacy enable-plugins skips Elementor until builder_type is saved, which v2 only writes at apply.
        if ( $editor === 'elementor' ) {
            $enable_result = $this->elementor_builder->enable_plugin();

            if ( is_wp_error( $enable_result ) ) {
                if ( ! $this->still_owns_start_claim( $request_key ) ) {
                    return $this->in_flight_response();
                }

                $this->jobs->delete();

                return $this->service_unavailable( 'Elementor could not be enabled: ' . $enable_result->get_error_message() );
            }

            $this->jobs->touch_start_claim( $request_key );
        }

        $payload = array(
            'brandName'   => $brand_name,
            'description' => $this->append_contact_availability( $description ),
            'editor'      => $editor,
            'websiteType' => $website_types,
            'language'    => $this->resolve_language( $request ),
        );

        if ( $pages !== null ) {
            $payload['pages'] = $pages;
        }

        $this->jobs->touch_start_claim( $request_key );

        $response      = $this->generation_client->start_generation( $software_id, $payload );
        $generation_id = $this->resolve_generation_id( $response );

        if ( $generation_id === '' ) {
            if ( ! $this->still_owns_start_claim( $request_key ) ) {
                return $this->in_flight_response();
            }

            $this->jobs->delete();

            return $this->service_unavailable( 'Generation was not queued, proxy responded with ' . $response['code'] );
        }

        if ( ! $this->still_owns_start_claim( $request_key ) ) {
            return $this->in_flight_response();
        }

        if ( $this->is_cancel_requested() ) {
            $this->jobs->delete();

            return $this->status_response( 'cancelled', array( 'request_key' => $request_key ) );
        }

        // Do not clear a claim still held by an apply in flight.
        $job = array(
            'request_key' => $request_key,
            'job_id'      => $generation_id,
            'phase'       => GenerationJobStore::PHASE_PENDING,
            'started_at'  => time(),
        );

        $this->jobs->save( $job );

        GenerationState::start();

        return $this->status_response( GenerationJobStore::PHASE_PENDING, $job );
    }

    private function resolve_generation_id( array $response ): string {
        $nested = $this->as_string( $response['body']['data']['generationId'] ?? null );

        if ( $nested !== '' ) {
            return $nested;
        }

        return $this->as_string( $response['body']['generationId'] ?? null );
    }

    private function public_status( string $phase ): string {
        if ( GenerationJobStore::PHASE_STARTING === $phase ) {
            return GenerationJobStore::PHASE_PENDING;
        }

        if ( GenerationJobStore::PHASE_APPLIED === $phase ) {
            return 'done';
        }

        return $phase;
    }

    private function in_flight_response(): WP_REST_Response {
        $job = $this->jobs->get_fresh();

        if ( array() === $job ) {
            $job = array( 'request_key' => (string) ( $this->jobs->start_claim()['request_key'] ?? '' ) );
        }

        $phase = (string) ( $job['phase'] ?? GenerationJobStore::PHASE_STARTING );

        if ( ! $this->jobs->is_live( $job ) && ! $this->jobs->start_claim_abandoned() ) {
            $phase = GenerationJobStore::PHASE_PENDING;
        }

        return $this->status_response( $this->public_status( $phase ), $job );
    }

    public function generation_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job = $this->jobs->get();

        if ( array() === $job ) {
            return new WP_Error(
                'no_generation',
                __( 'No website generation has been started.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::NOT_FOUND )
            );
        }

        $phase = (string) ( $job['phase'] ?? GenerationJobStore::PHASE_PENDING );

        if ( GenerationJobStore::PHASE_STARTING === $phase ) {
            if ( $this->jobs->start_claim_abandoned() ) {
                return $this->fail_job( $job, 'The generation start did not complete.' );
            }

            return $this->status_response( GenerationJobStore::PHASE_PENDING, $job );
        }

        $job_id = (string) ( $job['job_id'] ?? '' );

        if ( $phase === GenerationJobStore::PHASE_APPLIED ) {
            return $this->status_response( 'done', $job, array( 'result' => $job['result'] ?? array() ) );
        }

        if ( $phase === GenerationJobStore::PHASE_FAILED ) {
            return $this->status_response(
                'failed',
                $job,
                $this->failure_details(
                    (string) ( $job['reason'] ?? self::GENERATION_FAILED_REASON ),
                    (string) ( $job['detail'] ?? '' )
                )
            );
        }

        $job       = $this->jobs->ensure_started_at( $job );
        $timed_out = $this->jobs->timed_out( $job );

        if ( $phase === GenerationJobStore::PHASE_APPLYING && ! $this->apply_claim_abandoned() ) {
            return $this->status_response( 'applying', $job );
        }

        if ( $timed_out && $phase === GenerationJobStore::PHASE_APPLYING ) {
            return $this->fail_unfinished_apply( $job );
        }

        $software_id = $this->get_software_id();

        if ( empty( $software_id ) ) {
            return $this->timeout_or_unavailable( $timed_out, $job, 'Software ID not available' );
        }

        $response = $this->generation_client->get_generation( $software_id, $job_id );
        $status   = $this->as_string( $response['body']['data']['status'] ?? null );

        if ( $status === '' ) {
            return $this->timeout_or_unavailable(
                $timed_out,
                $job,
                'Generation status unavailable, proxy responded with ' . $response['code']
            );
        }

        if ( $status === self::UPSTREAM_IN_PROGRESS ) {
            if ( $timed_out ) {
                return $this->fail_timed_out_job( $job, 'Generation still in progress' );
            }

            return $this->status_response( 'pending', $job );
        }

        if ( $status === self::UPSTREAM_FAILED ) {
            Helper::log( 'Website generation failed upstream: ' . $this->describe_upstream_failure( $response ) );

            return $this->fail_job( $job, self::GENERATION_FAILED_REASON );
        }

        if ( $status !== self::UPSTREAM_COMPLETED ) {
            return $this->timeout_or_unavailable(
                $timed_out,
                $job,
                'Unexpected generation status ' . $status
            );
        }

        $envelope = $response['body']['data']['result'] ?? null;

        if ( ! is_array( $envelope ) || empty( $envelope ) ) {
            return $this->fail_job( $job, 'The generation completed without a website envelope.' );
        }

        $claim = $this->claim_apply( $job_id );

        if ( self::CLAIM_HELD === $claim ) {
            return $this->status_response( 'applying', $job );
        }

        if ( self::CLAIM_ERROR === $claim ) {
            return $this->service_unavailable( 'The apply path could not be claimed' );
        }

        if ( $this->request_detacher->can_detach() ) {
            $this->request_detacher->run_after_response(
                function () use ( $job, $job_id, $envelope ): void {
                    $this->apply_envelope( $job, $job_id, $envelope );
                }
            );

            return $this->status_response( GenerationJobStore::PHASE_APPLYING, $job );
        }

        return $this->apply_envelope( $job, $job_id, $envelope );
    }

    private function apply_envelope( array $job, string $job_id, array $envelope ): WP_REST_Response {
        $this->guard_apply_against_fatals( $job_id );

        try {
            $result = $this->applier->apply(
                $envelope,
                fn( string $step ) => $this->record_apply_step( $job_id, $step )
            );
        } catch ( InvalidArgumentException $e ) {
            return $this->fail_owned_apply( $job, $job_id, 'The generated website envelope is not supported: ' . $e->getMessage() );
        } catch ( Throwable $e ) {
            Helper::log(
                sprintf(
                    'SiteApplier failed: %s at %s:%d: %s',
                    get_class( $e ),
                    basename( $e->getFile() ),
                    $e->getLine(),
                    $this->truncate_for_log( $e->getMessage() )
                )
            );

            return $this->fail_owned_apply( $job, $job_id, 'Applying the generated website failed.' );
        }

        if ( ! $this->owns_apply_claim( $job_id ) ) {
            Helper::log( 'Applying the generated website finished after its claim was lost: ' . $job_id );

            return $this->current_job_response();
        }

        $this->record_applied_site( $job_id, $envelope );

        $response = $this->finish_applied_job( $job, $job_id, $result );

        $this->send_created_event();

        // Next apply deletes these pages, so a snapshot has to exist first.
        ( new VersionManager() )->capture_after_generation();

        return $response;
    }

    private function record_applied_site( string $job_id, array $envelope ): void {
        update_option( Helper::HOSTINGER_AI_THEME_GENERATED_ONCE_OPTION, true );
        update_option( 'hostinger_ai_version', time() );
        GenerationState::complete();

        $editor = sanitize_key( (string) ( $envelope['editor'] ?? '' ) );
        if ( $editor === 'gutenberg' ) {
            ( new GutenbergPreferences() )->disable_welcome_guide();
        }

        $this->record_apply_step( $job_id, 'cache' );

        Helper::activate_litespeed_cache();
        $this->purge_cdn_cache( (string) $this->get_software_id() );
    }

    private function finish_applied_job( array $job, string $job_id, array $result ): WP_REST_Response {
        delete_option( self::APPLY_LOCK_OPTION );

        // A cancel that landed while this apply was running deliberately left
        // the lock alone, so honour it now: the content did land, but the job
        // record is dropped rather than resurrected as `applied` for a job the
        // user cancelled.
        if ( $this->is_cancel_requested() ) {
            $this->jobs->delete();

            return $this->status_response( 'cancelled', $job );
        }

        $job = array(
            'request_key' => $this->jobs->request_key( $job ),
            'job_id'      => $job_id,
            'phase'       => GenerationJobStore::PHASE_APPLIED,
            'result'      => $result,
        );

        $this->jobs->save( $job );

        return $this->status_response( 'done', $job, array( 'result' => $result ) );
    }

    public function suggest_pages( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $description = $this->get_description( $request );
        $brand_name  = $this->resolve_brand_name( $request );

        if ( $description === '' || $brand_name === '' ) {
            return new WP_Error(
                'data_invalid',
                __( 'Description and brand name are required.', 'hostinger-ai-theme' ),
                array( 'status' => WP_Http::BAD_REQUEST )
            );
        }

        $software_id = $this->get_software_id();

        if ( empty( $software_id ) ) {
            return $this->service_unavailable( 'Software ID not available' );
        }

        $response = $this->generation_client->suggest_pages(
            $software_id,
            array(
                'brandName'   => $brand_name,
                'description' => $description,
                'websiteType' => $this->resolve_website_types( $request ),
                'language'    => $this->resolve_language( $request ),
            )
        );

        $pages = $response['body']['data']['pages'] ?? null;

        if ( ! is_array( $pages ) ) {
            return $this->service_unavailable( 'Page suggestions unavailable, proxy responded with ' . $response['code'] );
        }

        return $this->rest_response( array( 'pages' => $pages ) );
    }

    public function permission_check(): bool {
        if ( has_action( 'litespeed_control_set_nocache' ) ) {
            do_action(
                'litespeed_control_set_nocache',
                'Custom Rest API endpoint, not cacheable.'
            );
        }

        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    private function get_description( WP_REST_Request $request ): string {
        return sanitize_textarea_field( $this->get_string_param( $request, 'description' ) );
    }

    // Name which contact fields exist, never the values — otherwise the model authors a column that then empties.
    private function append_contact_availability( string $description ): string {
        $contact = get_option( 'hostinger_ai_contact', array() );

        if ( ! is_array( $contact ) ) {
            return $description;
        }

        $labels = array(
            'email'   => 'email',
            'phone'   => 'phone number',
            'address' => 'address',
        );

        $supplied = array();

        foreach ( $labels as $key => $label ) {
            if ( trim( (string) ( $contact[ $key ] ?? '' ) ) !== '' ) {
                $supplied[] = $label;
            }
        }

        if ( $supplied === array() ) {
            return $description;
        }

        return $description . "\n\n" . sprintf(
            'The owner supplied these contact details for the contact page: %s. Their values are filled in automatically -- never write them out.',
            implode( ', ', $supplied )
        );
    }

    // v2 posts camelCase; snake_case is kept for older callers.
    private function resolve_brand_name( WP_REST_Request $request ): string {
        $brand_name = $this->get_string_param( $request, 'brandName' );

        if ( $brand_name === '' ) {
            $brand_name = $this->get_string_param( $request, 'brand_name' );
        }

        if ( $brand_name === '' ) {
            $brand_name = (string) get_option( 'hostinger_ai_brand_name', '' );
        }

        return sanitize_text_field( $brand_name );
    }

    private function get_string_param( WP_REST_Request $request, string $name ): string {
        return $this->as_string( $request->get_param( $name ) );
    }

    private function as_string( $value ): string {
        return is_string( $value ) ? $value : '';
    }

    private function resolve_editor( WP_REST_Request $request ): string {
        $editor = strtolower( sanitize_text_field( $this->get_string_param( $request, 'editor' ) ) );

        if ( ! in_array( $editor, self::EDITORS, true ) ) {
            $editor = strtolower( (string) get_option( 'hostinger_ai_builder_type', '' ) );
        }

        return in_array( $editor, self::EDITORS, true ) ? $editor : self::EDITORS[0];
    }

    private function resolve_website_types( WP_REST_Request $request ): array {
        $requested = $request->get_param( 'websiteType' );

        if ( null === $requested ) {
            $requested = $request->get_param( 'website_type' );
        }

        $requested     = is_array( $requested ) ? $requested : array( $requested );
        $website_types = $this->normalize_website_types( $requested );

        if ( ! empty( $website_types ) ) {
            return $website_types;
        }

        $website_types = $this->normalize_website_types( WebsiteTypeHelper::get_website_types() );

        return ! empty( $website_types ) ? $website_types : array( self::DEFAULT_WEBSITE_TYPE );
    }

    private function normalize_website_types( array $candidates ): array {
        $website_types = array();

        foreach ( $candidates as $candidate ) {
            if ( ! is_string( $candidate ) || $candidate === '' ) {
                continue;
            }

            $website_type = str_replace( ' ', '-', WebsiteTypeHelper::normalize( $candidate ) );

            if ( ! in_array( $website_type, self::WEBSITE_TYPES, true ) || in_array( $website_type, $website_types, true ) ) {
                continue;
            }

            $website_types[] = $website_type;
        }

        return $website_types;
    }

    private function save_generation_options( string $brand_name, array $website_types, string $description, string $language ): void {
        $enable_affiliate = in_array( 'affiliate-marketing', $website_types, true );

        update_option( 'hostinger_ai_website_type', $website_types );
        update_option( 'hostinger_ai_brand_name', $brand_name );
        update_option( 'hostinger_ai_description', $description );
        update_option( 'hostinger_ai_woo', $this->needs_woocommerce( $website_types ) );
        update_option( 'hostinger_ai_affiliate', $enable_affiliate );
        update_option( 'hostinger_ai_selected_language', $language );

        HostingerEcommerceHelper::maybe_apply_country();
        HostingerEcommerceHelper::maybe_update_prompt( $description );
    }

    private function needs_woocommerce( array $website_types ): bool {
        if ( HostingerEcommerceHelper::is_active() ) {
            return false;
        }

        return in_array( 'online-store', $website_types, true )
            || in_array( 'booking', $website_types, true );
    }

    private function enable_required_plugins( array $website_types ): ?WP_Error {
        if ( $this->needs_woocommerce( $website_types ) ) {
            $result = $this->woo_builder->boot();

            if ( is_wp_error( $result ) ) {
                return $this->service_unavailable( 'WooCommerce could not be enabled: ' . $result->get_error_message() );
            }
        } else {
            // Regenerating a non-store type would otherwise leave WooCommerce running.
            $this->woo_builder->disable_plugin();
        }

        if ( ! in_array( 'affiliate-marketing', $website_types, true ) ) {
            return null;
        }

        $result = $this->affiliate_builder->boot();

        if ( is_wp_error( $result ) ) {
            return $this->service_unavailable( 'Hostinger Affiliate Plugin could not be enabled: ' . $result->get_error_message() );
        }

        return null;
    }

    private function resolve_language( WP_REST_Request $request ): string {
        if ( HostingerEcommerceHelper::is_active() ) {
            $ecommerce_language = HostingerEcommerceHelper::get_language();
            if ( $ecommerce_language !== '' ) {
                return $ecommerce_language;
            }
        }

        $language = sanitize_text_field( $this->get_string_param( $request, 'language' ) );

        if ( $language !== '' ) {
            return $language;
        }

        $stored = (string) get_option( 'hostinger_ai_selected_language', 'en_US' );

        return $stored !== '' ? $stored : 'en_US';
    }

    // Same event as the legacy path. A failed send must not roll back the apply.
    private function send_created_event(): void {
        try {
            $helper = new Utils();
            $config = new Config();
            $client = new Client(
                $config->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
                array(
                    Config::TOKEN_HEADER  => $helper::getApiToken(),
                    Config::DOMAIN_HEADER => $helper->getHostInfo(),
                )
            );

            $website_types = WebsiteTypeHelper::get_website_types();

            ( new AmplitudeManager( $helper, $config, $client ) )->sendRequest(
                Endpoints::AMPLITUDE_ENDPOINT,
                array(
                    'action'             => self::AMPLITUDE_EVENT_CREATED,
                    'builder_type'       => (string) get_option( 'hostinger_ai_builder_type', '' ),
                    'website_type'       => implode( ', ', $website_types ),
                    'language'           => get_locale(),
                    'generation_version' => 'v2',
                )
            );
        } catch ( Throwable $e ) {
            Helper::log( 'AI builder created event failed: ' . $e->getMessage() );
        }
    }

    private function purge_cdn_cache( string $software_id ): void {
        if ( $software_id === '' ) {
            return;
        }

        try {
            $this->wh_api_client->delete( ApiRoutes::INSTALLATIONS_BASE . $software_id . '/cache/cdn', array(), array(), 10 );
        } catch ( Throwable $e ) {
            Helper::log( 'CDN cache purge failed: ' . $this->truncate_for_log( $e->getMessage() ) );
        }
    }

    private function resolve_pages( WP_REST_Request $request ): array|WP_Error|null {
        if ( ! $request->has_param( 'pages' ) ) {
            return null;
        }

        $pages = $request->get_param( 'pages' );

        if ( ! is_array( $pages ) || $pages === array() || count( $pages ) > GenerationConstant::MAX_PAGES ) {
            return $this->invalid_pages();
        }

        $validated = array();
        $slugs     = array();

        foreach ( $pages as $index => $page ) {
            $has_required_fields = is_array( $page )
                && array_key_exists( 'slug', $page )
                && array_key_exists( 'name', $page );

            if ( ! $has_required_fields ) {
                return $this->invalid_pages();
            }

            $name     = sanitize_text_field( $this->as_string( $page['name'] ?? null ) );
            $raw_slug = $this->as_string( $page['slug'] ?? null );
            $slug     = sanitize_title( $raw_slug );

            if ( $name === '' || ( $index === 0 && $raw_slug !== '' ) || ( $index > 0 && $slug === '' ) ) {
                return $this->invalid_pages();
            }

            if ( $slug !== '' && in_array( $slug, $slugs, true ) ) {
                return $this->invalid_pages();
            }

            $slugs[]     = $slug;
            $validated[] = array(
                'slug' => $slug,
                'name' => $name,
            );
        }

        return $validated;
    }

    private function invalid_pages(): WP_Error {
        return new WP_Error(
            'invalid_pages',
            __( 'Pages must contain a home page and up to eight unique page slugs with names.', 'hostinger-ai-theme' ),
            array( 'status' => WP_Http::BAD_REQUEST )
        );
    }

    private function timeout_or_unavailable( bool $timed_out, array $job, string $error ): WP_REST_Response|WP_Error {
        if ( $timed_out ) {
            return $this->fail_timed_out_job( $job, $error );
        }

        return $this->service_unavailable( $error );
    }

    private function fail_timed_out_job( array $job, string $error ): WP_REST_Response {
        Helper::log( 'Website generation timed out: ' . $error );

        return $this->fail_job( $job, self::JOB_TIMEOUT_REASON, $error );
    }

    private function fail_unfinished_apply( array $job ): WP_REST_Response {
        $claim = get_option( self::APPLY_LOCK_OPTION, array() );
        $step  = is_array( $claim ) ? $this->as_string( $claim['step'] ?? null ) : '';
        $step  = $step !== '' ? $step : 'start';

        Helper::log( 'Applying the generated website did not finish, last step: ' . $step );

        return $this->fail_job( $job, sprintf( self::APPLY_UNFINISHED_REASON, $step ) );
    }

    private function is_cancel_requested(): bool {
        return ! empty( $this->jobs->get_fresh()['cancel_requested'] );
    }

    // Failure bodies echo the submitted brief back, so only status and error identifiers are logged.
    private function describe_upstream_failure( array $response ): string {
        $body  = is_array( $response['body'] ?? null ) ? $response['body'] : array();
        $error = is_array( $body['data']['error'] ?? null ) ? $body['data']['error'] : array();

        $parts   = array( 'HTTP ' . (int) ( $response['code'] ?? 0 ) );
        $code    = $this->as_string( $error['code'] ?? ( $body['code'] ?? null ) );
        $message = $this->as_string( $error['message'] ?? ( $body['message'] ?? null ) );

        if ( $code !== '' ) {
            $parts[] = 'code ' . $this->truncate_for_log( $code );
        }

        if ( $message !== '' ) {
            $parts[] = $this->truncate_for_log( $message );
        }

        return implode( ', ', $parts );
    }

    private function truncate_for_log( string $message ): string {
        $message = trim( preg_replace( '/\s+/', ' ', $message ) ?? $message );

        if ( mb_strlen( $message ) <= self::LOG_MESSAGE_LIMIT ) {
            return $message;
        }

        return mb_substr( $message, 0, self::LOG_MESSAGE_LIMIT ) . '…';
    }

    // INSERT IGNORE so two polls cannot both apply. add_option() is not a mutex.
    private function claim_apply( string $job_id ): string {
        global $wpdb;

        if ( $this->apply_claim_abandoned() ) {
            delete_option( self::APPLY_LOCK_OPTION );
        }

        $claim = maybe_serialize(
            array(
                'job_id'     => $job_id,
                'claimed_at' => time(),
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'off') /* LOCK */",
                self::APPLY_LOCK_OPTION,
                $claim
            )
        );

        // 0 rows = another poll holds the claim; false = the query failed.
        if ( false === $claimed ) {
            Helper::log( 'Failed to claim the apply path: ' . $this->truncate_for_log( (string) $wpdb->last_error ) );

            return self::CLAIM_ERROR;
        }

        if ( ! $claimed ) {
            return self::CLAIM_HELD;
        }

        // Insert bypassed the option API, so drop both caches.
        wp_cache_delete( self::APPLY_LOCK_OPTION, 'options' );
        wp_cache_delete( 'notoptions', 'options' );

        $this->jobs->save(
            array(
                'request_key' => $this->jobs->request_key( $this->jobs->get() ),
                'job_id'      => $job_id,
                'phase'       => GenerationJobStore::PHASE_APPLYING,
            )
        );

        return self::CLAIM_TAKEN;
    }

    private function apply_claim_abandoned(): bool {
        $claim = get_option( self::APPLY_LOCK_OPTION, array() );

        if ( ! is_array( $claim ) ) {
            return true;
        }

        $last_seen_at = max( (int) ( $claim['claimed_at'] ?? 0 ), (int) ( $claim['heartbeat_at'] ?? 0 ) );

        return ( time() - $last_seen_at ) > self::APPLY_LOCK_TTL;
    }

    private function owns_apply_claim( string $job_id ): bool {
        wp_cache_delete( self::APPLY_LOCK_OPTION, 'options' );

        $claim = get_option( self::APPLY_LOCK_OPTION, array() );

        return is_array( $claim ) && ( $claim['job_id'] ?? null ) === $job_id;
    }

    private function fail_owned_apply( array $job, string $job_id, string $reason ): WP_REST_Response {
        if ( ! $this->owns_apply_claim( $job_id ) ) {
            Helper::log( 'Applying the generated website failed after its claim was lost: ' . $job_id );

            return $this->current_job_response();
        }

        return $this->fail_job( $job, $reason );
    }

    private function current_job_response(): WP_REST_Response {
        $job   = $this->jobs->get_fresh();
        $phase = (string) ( $job['phase'] ?? GenerationJobStore::PHASE_FAILED );

        if ( $phase === GenerationJobStore::PHASE_FAILED ) {
            return $this->status_response( 'failed', $job, array( 'reason' => (string) ( $job['reason'] ?? self::GENERATION_FAILED_REASON ) ) );
        }

        if ( $phase === GenerationJobStore::PHASE_APPLIED ) {
            return $this->status_response( 'done', $job, array( 'result' => $job['result'] ?? array() ) );
        }

        return $this->status_response( $this->public_status( $phase ), $job );
    }

    private function record_apply_step( string $job_id, string $step ): void {
        if ( ! $this->owns_apply_claim( $job_id ) ) {
            return;
        }

        $claim = get_option( self::APPLY_LOCK_OPTION, array() );

        $claim['heartbeat_at'] = time();
        $claim['step']         = $step;

        update_option( self::APPLY_LOCK_OPTION, $claim, false );
    }

    // Fatals skip the catch; shutdown is the only hook that still runs.
    private function guard_apply_against_fatals( string $job_id ): void {
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 300 );
        }

        wp_raise_memory_limit( 'admin' );
        ignore_user_abort( true );

        register_shutdown_function(
            function () use ( $job_id ): void {
                $error = error_get_last();

                $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR );

                if ( null === $error || ! in_array( $error['type'], $fatal_types, true ) ) {
                    return;
                }

                $job = $this->jobs->get();

                if ( ( $job['job_id'] ?? '' ) !== $job_id || ( $job['phase'] ?? '' ) !== GenerationJobStore::PHASE_APPLYING ) {
                    return;
                }

                $this->fail_job( $job, 'Applying the generated website failed: ' . $error['message'] );
            }
        );
    }

    private function fail_job( array $job, string $reason, string $detail = '' ): WP_REST_Response {
        delete_option( self::APPLY_LOCK_OPTION );
        $this->jobs->release_start( $this->jobs->request_key( $job ) );
        GenerationState::reset();

        $this->jobs->save(
            array(
                'request_key' => $this->jobs->request_key( $job ),
                'job_id'      => (string) ( $job['job_id'] ?? '' ),
                'phase'       => GenerationJobStore::PHASE_FAILED,
                'reason'      => $reason,
                'detail'      => $detail,
            )
        );

        return $this->status_response( GenerationJobStore::PHASE_FAILED, $job, $this->failure_details( $reason, $detail ) );
    }

    private function failure_details( string $reason, string $detail ): array {
        if ( $detail === '' ) {
            return array( 'reason' => $reason );
        }

        return array(
            'reason' => $reason,
            'detail' => $detail,
        );
    }

    private function status_response( string $status, array $job, array $extra = array() ): WP_REST_Response {
        return $this->rest_response(
            array_merge(
                array(
                    'status' => $status,
                    'job_id' => $this->jobs->request_key( $job ),
                ),
                $extra
            )
        );
    }

    private function rest_response( array $data ): WP_REST_Response {
        $response = new WP_REST_Response( array( 'data' => $data ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    private function service_unavailable( string $error ): WP_Error {
        return new WP_Error(
            'generation_service_unavailable',
            __( 'The website generation service is temporarily unavailable.', 'hostinger-ai-theme' ),
            array(
                'status' => WP_Http::SERVICE_UNAVAILABLE,
                'error'  => $error,
            )
        );
    }
}
