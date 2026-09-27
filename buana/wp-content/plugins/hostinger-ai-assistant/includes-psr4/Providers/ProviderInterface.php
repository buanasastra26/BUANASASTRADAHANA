<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;

interface ProviderInterface {
    public function register( Container $container ): void;
}
