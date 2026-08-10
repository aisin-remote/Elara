<?php

return [
    'trial_days' => 0,
    'contact_sales_url' => null,
    'plans' => [
        'unlimited' => [
            'name' => 'Unlimited',
            'description' => 'Every Orbitra capability without package quotas.',
            'monthly' => null,
            'yearly' => null,
            'display_monthly' => 0,
            'features' => ['Unlimited members', 'Unlimited projects', 'Unlimited application storage quota', 'All integrations', 'CSV and PDF exports'],
            'limits' => ['members' => null, 'active_projects' => null, 'storage_bytes' => null, 'integrations' => null, 'exports' => true],
        ],
    ],
];
