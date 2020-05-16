<?php
return [
    '*' => [
        'enabled' => false
    ],
    'dev' => [],
    'staging' => [],
    'production' => [
        'enabled' => true,
        'cloudProvider' => 's3',
        // Provider specific configuration
        'useQueue' => true,
        'keepLocal' => false,
        'prune' => true,
        'pruneHourlyCount' => 6,    // Last 6 hours
        'pruneDailyCount' => 14,    // Last 14 days
        'pruneWeeklyCount' => 4,    // Last 4 weeks
        'pruneMonthlyCount' => 6,   // Last 6 months
        'pruneYearlyCount' => 3,    // Last 3 years
    ],
];
