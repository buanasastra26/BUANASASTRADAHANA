<?php

namespace Hostinger\AiTheme\Constants;

defined( 'ABSPATH' ) || exit;

class GenerationConstant {
    public const META_KEY = '_hostinger_ai_generated';

    public const GENERATED_POST_TYPES = array( 'page', 'post', 'product', 'attachment', 'wp_navigation' );

    public const STATE_OPTION = 'hostinger_ai_generation_state';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_COMPLETE = 'complete';

    public const TEMPLATE_PARTS_READY_OPTION = 'hostinger_ai_template_parts_ready';

    public const WOO_PAGE_OPTIONS = array(
        'woocommerce_shop_page_id',
        'woocommerce_cart_page_id',
        'woocommerce_checkout_page_id',
        'woocommerce_myaccount_page_id',
    );

    public const MAX_PAGES = 8;

    public const MAX_PAGES_LEGACY = 10;
}
