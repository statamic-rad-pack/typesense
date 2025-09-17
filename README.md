# Statamic Typesense Driver

This addon provides a [Typesense](https://typesense.org) search driver for Statamic sites.

## Requirements

* PHP 8.2+
* Laravel 10+
* Statamic 5
* Typesense 0.2+

### Installation

```bash
composer require statamic-rad-pack/typesense
```

Add the following variables to your env file:

```txt
TYPESENSE_HOST=http://127.0.0.1
TYPESENSE_API_KEY=
```

Add the new driver to the `statamic/search.php` config file:

```php
'drivers' => [

    // other drivers

    'typesense' => [
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
    ],
],
```

You can optionally publish the config file for this package using:

```
php artisan vendor:publish --tag=statamic-typesense-config
```

### Search Settings

Any additional settings you want to define per index can be included in the `statamic/search.php` config file. The settings will be updated when the index is created.

```php
'articles' => [
    'driver' => 'typesense',
    'searchables' => ['collection:articles'],
    'fields' => ['id', 'title', 'url', 'type', 'content', 'locale'],
    'settings' => [
        'schema' => [
            /*
                Pass an optional schema, see the Typesense documentation for more info:
                https://typesense.org/docs/26.0/api/collections.html#with-pre-defined-schema
            */
            'fields' => [
                [
                  'name'  => 'company_name',
                  'type'  => 'string',
                ],
                [
                  'name'  => 'num_employees',
                  'type'  => 'int32',
                  'sort'  => true,
                ],
                [
                  'name'  => 'country',
                  'type'  => 'string',
                  'facet' => true,
                ], 
            ],
        ],
        /* 
            Pass any of the options from https://typesense.org/docs/26.0/api/search.html#search-parameters
        */
        'search_options' => [
            /* 
                eg Specify a custom sort by order, see the Typesense documentation for more info:
                https://typesense.org/docs/guide/ranking-and-relevance.html#ranking-based-on-relevance-and-popularity
            */
            'sort_by' => '_text_match(buckets: 10):desc,weighted_score:desc',
        ],
        
        /*
            Set this to true to maintain the sort score order that Typesense returns 
        */
        'maintain_rankings' => false,
    ],
],
```

### Querying for a field with value `null`

[Typesense cannot filter documents by a field thats value is `null`](https://typesense.org/docs/guide/tips-for-searching-common-types-of-data.html#searching-for-null-or-empty-values). Therefore, if you build a query with `whereNull` or similar, this addon checks if a typesense collection field `is_{handle}_null` has the value `"true"`. So, if you want for example to get all results where the `content` is `null`, you need to add a field `is_content_null` to your schema in `search.php` which is filled via a transformer.
