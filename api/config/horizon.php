<?php

use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;

Horizon::routeSmsNotificationsTo('');
Horizon::routeMailNotificationsTo(env('ADMIN_EMAIL', 'admin@dijitul.co.uk'));
Horizon::routeSlackNotificationsTo(env('HORIZON_SLACK_WEBHOOK'), '#postduk-alerts');

return [

    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'horizon',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'postduk'), '_').'_horizon:'
    ),

    'middleware' => ['web', 'auth'],

    'waits' => [
        'redis:critical' => 3,
        'redis:posting' => 30,
        'redis:generation' => 60,
        'redis:scraping' => 300,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 180,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 256,

    'defaults' => [

        'supervisor-critical' => [
            'connection' => 'redis',
            'queue' => ['critical'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'sleep' => 3,
            'minProcesses' => 3,
            'balanceCooldown' => 3,
            'restartSignal' => 0,
        ],

        'supervisor-posting' => [
            'connection' => 'redis',
            'queue' => ['posting'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 90,
            'sleep' => 3,
            'minProcesses' => 3,
            'balanceCooldown' => 3,
        ],

        'supervisor-generation' => [
            'connection' => 'redis',
            'queue' => ['generation'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 120,
            'sleep' => 5,
            'minProcesses' => 2,
            'balanceCooldown' => 5,
        ],

        'supervisor-scraping' => [
            'connection' => 'redis',
            'queue' => ['scraping'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'sleep' => 10,
            'minProcesses' => 1,
        ],

    ],

    'environments' => [

        'production' => [
            'supervisor-critical' => [
                'minProcesses' => 3,
                'maxProcesses' => 5,
                'balanceCooldown' => 3,
            ],
            'supervisor-posting' => [
                'minProcesses' => 3,
                'maxProcesses' => 5,
                'balanceCooldown' => 3,
            ],
            'supervisor-generation' => [
                'minProcesses' => 2,
                'maxProcesses' => 4,
                'balanceCooldown' => 5,
            ],
            'supervisor-scraping' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
        ],

        'local' => [
            'supervisor-critical' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-posting' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-generation' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-scraping' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],

    ],

];
