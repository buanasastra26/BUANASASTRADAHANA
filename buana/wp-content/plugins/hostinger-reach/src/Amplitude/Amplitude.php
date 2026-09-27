<?php

namespace Hostinger\Reach\Amplitude;

use Hostinger\Amplitude\AmplitudeManager;


class Amplitude {

    private AmplitudeManager $amplitude_manager;

    public function __construct( AmplitudeManager $amplitude_manager ) {
        $this->amplitude_manager = $amplitude_manager;
    }

    public function send_event( array $params ): void {
        $this->amplitude_manager->sendRequest( $this->amplitude_manager::AMPLITUDE_ENDPOINT, $params );
    }
}
