<?php

namespace Hostinger\AiTheme\Rest;

defined( 'ABSPATH' ) || exit;

class Endpoints {
    public const  GENERATE_CONTENT_ENDPOINT = '/v3/wordpress/plugin/generate-content';
    public const GENERATE_IMAGE_ENDPOINT   = '/v3/wordpress/plugin/search-images';
    public const GENERATE_BLOCKS_ENDPOINT   = '/v3/wordpress/chatbot/proxy';
    public const AMPLITUDE_ENDPOINT = '/v3/wordpress/plugin/trigger-event';
}