<?php

if ( ! defined( 'HOSTINGER_AI_WEBSITES_THEME_PATH' ) ) {
    define( 'HOSTINGER_AI_WEBSITES_THEME_PATH', get_stylesheet_directory()  );
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_ASSETS_URL' ) ) {
    define( 'HOSTINGER_AI_WEBSITES_ASSETS_URL', get_stylesheet_directory_uri() . '/assets' );
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_WP_CONFIG_PATH' ) ) {
    define( 'HOSTINGER_AI_WEBSITES_WP_CONFIG_PATH', ABSPATH . '.private/config.json' );
}

$hostinger_config = [];
if ( file_exists( HOSTINGER_AI_WEBSITES_WP_CONFIG_PATH ) ) {
    $config_content   = file_get_contents( HOSTINGER_AI_WEBSITES_WP_CONFIG_PATH );
    $hostinger_config = json_decode( $config_content, true ) ?: [];
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_WP_TOKEN' ) ) {
    $hostinger_dir_parts        = explode( '/', __DIR__ );
    $hostinger_server_root_path = '/' . $hostinger_dir_parts[1] . '/' . $hostinger_dir_parts[2];
    define( 'HOSTINGER_AI_WEBSITES_WP_TOKEN', $hostinger_server_root_path . '/.api_token' );
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_REST_URI' ) ) {
    $rest_uri = 'https://rest-hosting.hostinger.com';

    if ( ! empty( $hostinger_config['base_rest_uri'] ) ) {
        $rest_uri = $hostinger_config['base_rest_uri'];
    }

    define( 'HOSTINGER_AI_WEBSITES_REST_URI', $rest_uri );
}

if ( ! defined( 'HOSTINGER_WP_PROXY_API_URI' ) ) {
    define( 'HOSTINGER_WP_PROXY_API_URI', 'https://wh-wordpress-proxy-api.hostinger.io' );
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_MINIMUM_PHP_VERSION' ) ) {
    define( 'HOSTINGER_AI_WEBSITES_MINIMUM_PHP_VERSION', '8.0' );
}

if ( ! defined( 'HOSTINGER_AI_WEBSITES_REST_API_BASE' ) ) {
    define( 'HOSTINGER_AI_WEBSITES_REST_API_BASE', 'hostinger-ai-plugin/v1' );
}

if ( ! version_compare( phpversion(), HOSTINGER_AI_WEBSITES_MINIMUM_PHP_VERSION, '>=' ) ) {

    add_action( 'admin_notices', function () {
        ?>
        <div class="notice notice-error is-dismissible hts-theme-settings">
            <p>
                <?php /* translators: %s php version */ ?>
                <strong><?php echo __( 'Attention:', 'hostinger-ai-theme' ); ?></strong> <?php echo sprintf( __( 'The Hostinger Easy Onboarding plugin requires minimum PHP version of <b>%s</b>. ', 'hostinger-ai-theme' ), HOSTINGER_AI_WEBSITES_MINIMUM_PHP_VERSION ); ?>
            </p>
            <p>
                <?php /* translators: %s php version */ ?>
                <?php echo sprintf( __( 'You are running <b>%s</b> PHP version.', 'hostinger-ai-theme' ), phpversion() ); ?>
            </p>
        </div>
        <?php
    }
    );

    add_action( 'admin_head', function () { ?>
        <style>
            .notice.notice-error {
                display: none !important;
            }

            .notice.notice-error.hts-theme-settings {
                display: block !important;
            }
        </style>
    <?php } );

} else {
    $vendor_file = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if ( file_exists( $vendor_file ) ) {
        require_once $vendor_file;

        $boot = new \Hostinger\AiTheme\Boot();
        $boot->run();

    } else {
        return;
    }
}
