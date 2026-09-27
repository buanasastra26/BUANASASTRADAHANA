<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Constants\GenerationConstant;

defined( 'ABSPATH' ) || exit;

class GenerationState {
    public static function start(): void {
        update_option( GenerationConstant::STATE_OPTION, GenerationConstant::STATE_IN_PROGRESS );
    }

    public static function complete(): void {
        update_option( GenerationConstant::STATE_OPTION, GenerationConstant::STATE_COMPLETE );
        TemplatePartVisibility::mark_ready();
    }

    public static function reset(): void {
        delete_option( GenerationConstant::STATE_OPTION );
    }

    public static function is_in_progress(): bool {
        return get_option( GenerationConstant::STATE_OPTION ) === GenerationConstant::STATE_IN_PROGRESS;
    }

    public static function was_interrupted(): bool {
        return self::is_in_progress();
    }
}
