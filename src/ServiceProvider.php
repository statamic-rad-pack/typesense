<?php

namespace StatamicRadPack\Typesense;

use Illuminate\Foundation\Application;
use Statamic\Facades\Search;
use Statamic\Providers\AddonServiceProvider;
use Typesense\Client;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        Search::extend('typesense', function (Application $app, array $config, $name, $locale = null) {
            $client = new Client($config['client'] ?? []);

            return $app->makeWith(Typesense\Index::class, [
                'client' => $client,
                'name' => $name,
                'config' => $config,
                'locale' => $locale,
            ]);
        });
    }
}
