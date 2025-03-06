<?php

namespace StatamicRadPack\Typesense\Typesense;

use Illuminate\Support\Collection;
use Statamic\Contracts\Search\Searchable;
use Statamic\Facades\Blink;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Statamic\Support\Arr;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
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
        try {
            $this->getOrCreateIndex()->documents[$document->getSearchReference()]?->delete();
        } catch (ObjectNotFound $e) {
            // do nothing, this just prevents errors bubbling up when the document doesnt exist
        }
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

    public function searchUsingApi($query, array $options = []): array
    {
        $options['q'] = $query ?? '';

        $options = array_merge(Arr::get($this->config, 'settings.search_options', []), $options);

        if (! isset($options['query_by'])) {
            $schema = Arr::get($this->config, 'settings.schema', []);

            // if we have fields in our schema use any strings, otherwise *
            $options['query_by'] = collect($schema['fields'] ?? [])
                ->filter(fn ($field) => $field['type'] == 'string')
                ->map(fn ($field) => $field['name'])
                ->values()
                ->join(',') ?: '*';
        }

        $searchResults = $this->getOrCreateIndex()->documents->search($options);

        $total = count($searchResults['hits']);

        return [
            'raw' => $searchResults,
            'results' => collect($searchResults['hits'] ?? [])
                ->map(function ($result, $i) use ($total) {
                    $result['document']['reference'] = $result['document']['id'];
                    $result['document']['search_score'] = Arr::get($this->config, 'settings.maintain_ranking', false) ? ($total - $i) : (int) ($result['text_match'] ?? 0);

                    return $result['document'];
                }),
        ];
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

    public function getTypesenseSchemaFields(): Collection
    {
        return Blink::once('statamic-typesense::schema::'.$this->name(), function () {
            return collect(Arr::get($this->getOrCreateIndex()->retrieve(), 'fields', []));
        });
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
