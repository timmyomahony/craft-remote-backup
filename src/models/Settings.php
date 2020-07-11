<?php

namespace weareferal\remotebackup\models;

use weareferal\remotecore\models\Settings as BaseSettings;


class Settings extends BaseSettings
{
    public $keepLocal = false;
    public $prune = true;
    public $pruneHourlyCount = 6;
    public $pruneDailyCount = 14;
    public $pruneWeeklyCount = 4;
    public $pruneMonthlyCount = 6;
    public $pruneYearlyCount = 3;

    public function rules(): array
    {
        $rules = parent::rules();
        return $rules + [
            [
                [
                    'pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount',
                    'pruneYearlyCount'
                ],
                'required',
                'when' => function ($model) {
                    return $model->prune == 1 & $model->enabled == 1;
                }
            ],
            [
                [
                    'keepLocal', 'prune'
                ],
                'boolean'
            ],
            [
                [
                    'pruneHourlyCount', 'pruneDailyCount', 'pruneWeeklyCount',
                    'pruneMonthlyCount', 'pruneYearlyCount'
                ],
                'number'
            ]
        ];
    }
}
