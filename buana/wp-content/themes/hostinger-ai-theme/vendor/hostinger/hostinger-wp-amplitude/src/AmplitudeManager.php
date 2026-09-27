<?php

namespace Hostinger\Amplitude;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

class AmplitudeManager
{
    public const AMPLITUDE_ENDPOINT = '/v3/wordpress/plugin/trigger-event';
    public const CACHE_THREE_HOURS = 10800;
    public const CACHE_ONE_HOUR = 3600;
    public const ERROR_LOG_THROTTLE_SECONDS = 300;
    private const LOGIN_DATA = 'hostinger_login_data';
    private const OPTION_PREFIX = 'amplitude_event_';
    private const OPTION_COUNT_PREFIX = 'amplitude_count_';
    private const CLEANUP_PREFIX = 'amplitude_cleanup_';
    private const ERROR_LOG_PREFIX = 'amplitude_error_';

    private Config $configHandler;
    private Client $client;
    private Helper $helper;

    public function __construct(
        Helper $helper,
        Config $configHandler,
        Client $client
    ) {
        $this->helper        = $helper;
        $this->configHandler = $configHandler;
        $this->client        = $client;
    }

    public function sendRequest( string $endpoint, array $params, array $headers = [] ): array {
        try {
            if ( ! $this->helper->checkTransientEligibility('check_transients', self::CACHE_THREE_HOURS)) {
                return [];
            }

            if ( ! $this->shouldSendAmplitudeEvent( $params ) ) {
                return [];
            }

            $params  = $this->addImpersonationData( $params );
            $params  = $this->addDomainAndDirectory( $params );

            $headers = $this->extractCorrelationIdHeader( $headers );

            $response = $this->client->post( $endpoint, [ 'params' => $params ], $headers );

            if ( is_wp_error( $response ) ) {
                $this->logErrorThrottled(
                    'Hostinger WP Amplitude package: ' . $response->get_error_message(),
                    'wp_error_' . $response->get_error_code()
                );
                return [];
            }

            return $response;
        } catch ( \Exception $exception ) {
            $this->logErrorThrottled(
                'Error sending request: ' . $exception->getMessage(),
                'exception_' . get_class( $exception )
            );

            return [ 'status' => 'error', 'message' => 'An error occurred while sending the request.' ];
        }
    }

    public function addDomainAndDirectory( array $params ) : array {
        if ( $siteUrl = get_site_url() ) {
            $params['siteurl'] = $siteUrl;

            $websiteDir          = $this->getSubdirectoryName( $siteUrl );
            $params['directory'] = $websiteDir;
        }

        return $params;
    }

    public function getSubdirectoryName( string $siteUrl ) : string {
        $sitePath = parse_url( $siteUrl, PHP_URL_PATH ) ?? '';

        return trim( $sitePath, '/' ) ? : '';
    }

    public function addImpersonationData( array $params ) : array {
        $login_data = get_transient( self::LOGIN_DATA );

        if ( $login_data === false ) {
            return $params;
        }

        if ( ! empty( $login_data['acting_client_id'] ) ) {
            $params['is_impersonated']        = true;
            $params['impersonated_client_id'] = sanitize_text_field( $login_data['acting_client_id'] );
        }

        if ( ! empty( $login_data['client_id'] ) ) {
            $params['client_id'] = sanitize_text_field( $login_data['client_id'] );
        }

        return $params;
    }

    public static function getSingleAmplitudeEvents() : array {
        return apply_filters( 'hostinger_once_per_day_events', [] );
    }

    public static function getLimitedPerDayEvents() : array {
        return apply_filters( 'hostinger_limited_per_day_events', [] );
    }

    public function shouldSendAmplitudeEvent( array $params ): bool {
        if ( empty( $params['action'] ) ) {
            return false;
        }

        $eventAction       = sanitize_text_field( $params['action'] );
        $oneTimePerDay     = self::getSingleAmplitudeEvents();
        $limitedPerDay     = self::getLimitedPerDayEvents();
        $today             = wp_date( 'Y-m-d' );

        if ( in_array( $eventAction, $oneTimePerDay, true ) ) {
            return $this->checkOncePerDayLimit( $eventAction, $today );
        }

        if ( isset( $limitedPerDay[ $eventAction ] ) ) {
            $maxCount = (int) $limitedPerDay[ $eventAction ];

            return $this->checkCountLimit( $eventAction, $today, $maxCount );
        }

        return true;
    }

    private function checkOncePerDayLimit( string $eventAction, string $today ): bool {
        $optionKey = self::OPTION_PREFIX . $eventAction . '_' . $today;

        $existingValue = $this->getOptionDirect( $optionKey );

        if ( $existingValue !== null ) {
            return false;
        }

        $added = $this->addOptionAtomic( $optionKey, time() );

        if ( $added === false ) {
            $existingValue = $this->getOptionDirect( $optionKey );

            if ( $existingValue !== null ) {
                return false;
            }

            $this->logErrorThrottled(
                'Amplitude rate limiter storage error for: ' . $optionKey . '. Blocking event to prevent flooding.',
                'rate_limiter_' . $eventAction
            );

            return false;
        }

        $this->scheduleCleanup( $eventAction, $today, self::OPTION_PREFIX );

        return true;
    }

    private function checkCountLimit( string $eventAction, string $today, int $maxCount ): bool {
        $optionKey = self::OPTION_COUNT_PREFIX . $eventAction . '_' . $today;

        $currentCount = $this->getOptionDirect( $optionKey );

        if ( $currentCount !== null && (int) $currentCount >= $maxCount ) {
            return false;
        }

        $newCount = $this->incrementCounter( $optionKey );

        if ( $newCount === false ) {
            $this->logErrorThrottled(
                'Amplitude counter increment error for: ' . $optionKey . '. Blocking event to prevent flooding.',
                'counter_increment_' . $eventAction
            );

            return false;
        }

        if ( $newCount > $maxCount ) {
            return false;
        }

        $this->scheduleCleanup( $eventAction, $today, self::OPTION_COUNT_PREFIX );

        return true;
    }

    private function incrementCounter( string $optionKey ): int|false {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, 1, 'no')
                 ON DUPLICATE KEY UPDATE option_value = option_value + 1",
                $optionKey
            )
        );

        if ( $result === false ) {
            return false;
        }

        $newValue = $this->getOptionDirect( $optionKey );

        return $newValue !== null ? (int) $newValue : false;
    }

    private function getOptionDirect( string $optionKey ): ?string {
        global $wpdb;

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optionKey
            )
        );

        return $value;
    }

    private function addOptionAtomic( string $optionKey, int $value ): bool {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $optionKey,
                $value
            )
        );

        if ( $result === false ) {
            return false;
        }

        return $result > 0;
    }

    private function scheduleCleanup( string $eventAction, string $today, string $optionPrefix ): void {
        $cleanupKey = self::CLEANUP_PREFIX . $optionPrefix . $eventAction;

        if ( get_transient( $cleanupKey ) ) {
            return;
        }

        set_transient( $cleanupKey, true, self::CACHE_ONE_HOUR );

        $this->cleanupOldEventOptions( $eventAction, $today, $optionPrefix );
    }

    private function cleanupOldEventOptions( string $eventAction, string $today, string $optionPrefix ): void {
        global $wpdb;

        $todayOption = $optionPrefix . $eventAction . '_' . $today;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_name != %s",
                $wpdb->esc_like( $optionPrefix . $eventAction . '_' ) . '%',
                $todayOption
            )
        );

        if ( $result === false ) {
            $this->logErrorThrottled(
                'Failed to cleanup old amplitude event options for: ' . $eventAction,
                'cleanup_' . $eventAction
            );
        }
    }

    public function extractCorrelationIdHeader( array $headers = [] ): array {
        if ( empty( $headers ) ) {
            return [];
        }

        foreach ( $headers as $key => $value ) {
            if ( strtolower( $key ) === 'x-correlation-id' || strtolower( $key ) === 'x_correlation_id' ) {
                $idValue = is_array( $value ) && ! empty( $value ) ? $value[0] : $value;

                return [ 'X-Correlation-ID' => sanitize_text_field( (string) $idValue ) ];
            }
        }

        return [];
    }

    private function logErrorThrottled( string $message, string $errorKey ): void {
        $throttleKey = self::ERROR_LOG_PREFIX . md5( $errorKey );

        if ( get_transient( $throttleKey ) ) {
            return;
        }

        set_transient( $throttleKey, true, self::ERROR_LOG_THROTTLE_SECONDS );
        $this->helper->errorLog( $message );
    }
}
