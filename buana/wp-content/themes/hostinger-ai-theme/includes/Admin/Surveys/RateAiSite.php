<?php

namespace Hostinger\AiTheme\Admin\Surveys;

use Hostinger\Surveys\SurveyManager;

class RateAiSite
{
    private SurveyManager $surveyManager;

    public function __construct(SurveyManager $surveyManager)
    {
        $this->surveyManager = $surveyManager;
    }

    public function isSurveyEnabled(): bool
    {
        if (defined('DOING_AJAX') && \DOING_AJAX) {
            return false;
        }

        return $this->surveyManager->isClientEligible();
    }
}
