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
        $this->mergeConfigFrom(__DIR__.'/../config/statamic-typesense.php', 'statamic-typesense');

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/statamic-typesense.php' => config_path('statamic-typesense.php'),
            ], 'statamic-typesense-config');

        }

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
