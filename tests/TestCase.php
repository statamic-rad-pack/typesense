<?php

namespace StatamicRadPack\Typesense\Tests;

use Statamic\Testing\AddonTestCase;
use StatamicRadPack\Typesense\ServiceProvider;
use StatamicRadPack\Typesense\Typesense\Index;
use Typesense\ApiCall;
use Typesense\Client;
use Typesense\Collections;
use Typesense\MultiSearch;

class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected $shouldFakeVersion = true;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

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

        $app['config']->set('statamic.search.indexes.cp', [
            'driver' => 'null',
        ]);

        $app['config']->set('statamic.search.indexes.typesense_index', [
            'driver' => 'typesense',
            'searchables' => ['collection:pages'],
            'settings' => [
                'schema' => [
                    'fields' => [
                        [
                            'type' => 'string',
                            'name' => 'title',
                            'sort' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Build an Index whose requests go through a mocked HTTP layer, so tests can assert
     * on the requests the driver actually makes without a running Typesense server.
     */
    protected function indexWithMockedApi(ApiCall $apiCall, string $name = 'test'): Index
    {
        $client = new Client([
            'api_key' => 'xyz',
            'nodes' => [['host' => 'localhost', 'port' => '8108', 'path' => '', 'protocol' => 'http']],
        ]);

        $client->collections = new Collections($apiCall);
        $client->multiSearch = new MultiSearch($apiCall);

        return new Index($client, $name, []);
    }
}
