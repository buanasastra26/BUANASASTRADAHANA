<?php

namespace Hostinger\Reach\Providers;

use Hostinger\Reach\Admin\Surveys\SatisfactionSurvey;
use Hostinger\Reach\Admin\Surveys\Survey;
use Hostinger\Reach\Container;
use Hostinger\Surveys\SurveyManager;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SurveysProvider implements ProviderInterface {
    public const SURVEY_CLASSES = array(
        SatisfactionSurvey::class,
    );

    public function register( Container $container ): void {
        foreach ( self::SURVEY_CLASSES as $survey_class ) {
            $container->set(
                $survey_class,
                function () use ( $container, $survey_class ) {
                    return new $survey_class( $container->get( SurveyManager::class ) );
                }
            );

            /** @var Survey $survey */
            $survey = $container->get( $survey_class );
            $survey->init();
        }
    }
}
