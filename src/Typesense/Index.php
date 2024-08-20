<?php

namespace StatamicRadPack\Typesense\Typesense;

use Illuminate\Support\Collection;
use Statamic\Contracts\Search\Searchable;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Statamic\Support\Arr;
use Typesense\Client;
use Typesense\Exceptions\TypesenseClientError;

class Index extends BaseIndex
{
    protected $client;

    public function __construct(Client $client, $name, array $config, ?string $locale = null)
    {
        $this->client = $client;

        parent::__construct($name, $config, $locale);
    }

    public function search($query)
    {
        return (new Query($this))->query($query);
    }

    public function insert($document)
    {
        return $this->insertMultiple(collect([$document]));
    }

    public function insertMultiple($documents)
    {
        $documents
            ->chunk(config('statamic-typesense.insert_chunk_size', 100))
            ->each(function ($documents, $index) {
                $documents = $documents
                    ->filter()
                    ->map(fn ($document) => array_merge(
                        $this->searchables()->fields($document),
                        $this->getDefaultFields($document),
                    ))
                    ->values()
                    ->toArray();

                $this->insertDocuments(new Documents($documents));
            });

        return $this;
    }

    public function delete($document)
    {
        $this->getOrCreateIndex()->documents[$document->getSearchReference()]?->delete();
    }

    public function exists()
    {
        try {
            $this->getOrCreateIndex();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function insertDocuments(Documents $documents)
    {
        $this->getOrCreateIndex()->documents->import($documents->all(), ['action' => 'upsert']);
    }

    protected function deleteIndex()
    {
        $this->getOrCreateIndex()->delete();
    }

    public function update()
    {
        $this->deleteIndex();

        $this->getOrCreateIndex();

        $this->searchables()->lazy()->each(fn ($searchables) => $this->insertMultiple($searchables));

        return $this;
    }

    public function searchUsingApi($query, array $options = []): Collection
    {
        $options['q'] = $query;

        if (! isset($options['query_by'])) {
            $schema = Arr::get($this->config, 'settings.schema', []);

            // if we have fields in our schema use any strings, otherwise *
            $options['query_by'] = collect($schema['fields'] ?? [])
                ->filter(fn ($field) => $field['type'] == 'string')
                ->map(fn ($field) => $field['name'])
                ->values()
                ->join(',') ?: '*';
        }

        foreach (Arr::get($this->config, 'settings.search_options', []) as $handle => $value) {
            if (in_array($handle, ['query_by', 'q'])) {
                continue;
            }

            if (! isset($options[$handle])) {
                $options[$handle] = $value;
            }
        }

        $searchResults = $this->getOrCreateIndex()->documents->search($options);

        return collect($searchResults['hits'] ?? [])
            ->map(function ($result, $i) {
                $result['document']['reference'] = $result['document']['id'];
                $result['document']['search_score'] = (int) ($result['text_match'] ?? 0);

                return $result['document'];
            });
    }

    public function getOrCreateIndex()
    {
        $collection = $this->client->getCollections()->{$this->name};

        // Determine if the collection exists in Typesense...
        try {
            $collection->retrieve();

            // No error means this collection exists on the server...
            $collection->setExists(true);

            return $collection;
        } catch (TypesenseClientError $e) {

        }

        $schema = Arr::get($this->config, 'settings.schema', []);
        $schema['name'] = $this->name;

        if (! isset($schema['fields'])) {
            $schema['fields'] = [
                ['name' => '.*', 'type' => 'auto'],
            ];
        }

        $this->client->getCollections()->create($schema);

        $collection->setExists(true);

        return $collection;
    }

    private function getDefaultFields(Searchable $entry): array
    {
        return [
            'id' => $entry->getSearchReference(),
        ];
    }

    public function getCount()
    {
        return $this->getOrCreateIndex()->retrieve()['num_documents'] ?? 0;
    }

    public function client()
    {
        return $this->client;
    }
}
