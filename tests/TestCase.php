<?php

namespace StatamicRadPack\Typesense\Tests;

use Statamic\Testing\AddonTestCase;
use StatamicRadPack\Typesense\ServiceProvider;

class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected $shouldFakeVersion = true;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // add typesense driver
        $app['config']->set('statamic.search.drivers.typesense', [
            'client' => [
                'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
                'nodes' => [
                    [
                        'host' => env('TYPESENSE_HOST', 'localhost'),
                        'port' => env('TYPESENSE_PORT', '8108'),
                        'path' => env('TYPESENSE_PATH', ''),
                        'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                    ],
                ],
                'nearest_node' => [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
                'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
                'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
                'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
                'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
            ],
        ]);

        // add typesense index
        $app['config']->set('statamic.search.indexes.typesense_index', [
            'driver' => 'typesense',
            'searchables' => ['collection:pages'],
        ]);
    }
}
