<?php
return [
    '*' => [
        'cloudProvider' => 's3',
        //...
        'useQueue' => false,
        'keepLocal' => false,
        'prune' => true,
        'pruneHourlyCount' => 6,
        'pruneDailyCount' => 14,
        'pruneWeeklyCount' => 4,
        'pruneMonthlyCount' => 6,
        'pruneYearlyCount' => 3,
    ],
    'dev' => [],
    'staging' => [],
    'production' => [],
];
