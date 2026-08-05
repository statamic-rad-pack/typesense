<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Typesense\Tests\TestCase;
use StatamicRadPack\Typesense\Typesense\Index;
use Typesense\ApiCall;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Exceptions\ObjectNotFound;

class GetOrCreateIndexTest extends TestCase
{
    /**
     * Build an Index whose collection lookups go through a mocked HTTP layer, so we can
     * count the requests the driver actually makes.
     */
    private function indexWithMockedApi(ApiCall $apiCall, string $name = 'test'): Index
    {
        $client = new Client([
            'api_key' => 'xyz',
            'nodes' => [['host' => 'localhost', 'port' => '8108', 'path' => '', 'protocol' => 'http']],
        ]);

        $client->collections = new Collections($apiCall);

        return new Index($client, $name, []);
    }

    #[Test]
    public function it_only_looks_the_collection_up_once()
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/test', [])
            ->once()
            ->andReturn(['name' => 'test']);

        $index = $this->indexWithMockedApi($apiCall);

        $index->getOrCreateIndex();
        $index->getOrCreateIndex();
        $index->getOrCreateIndex();
    }

    /**
     * update() deletes the collection and then recreates it. The client keeps hold of the
     * Collection instance across both, so if deleting doesn't clear its exists flag the
     * recreate is skipped and every following import has nowhere to land.
     */
    #[Test]
    public function it_recreates_the_collection_after_deleting_it()
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/test', [])
            ->once()
            ->andReturn(['name' => 'test']);

        $apiCall->shouldReceive('delete')
            ->with('/collections/test')
            ->once()
            ->andReturn([]);

        $apiCall->shouldReceive('post')
            ->with('/collections', Mockery::type('array'), true, [])
            ->once()
            ->andReturn(['name' => 'test']);

        $this->indexWithMockedApi($apiCall)->update();
    }

    #[Test]
    public function it_creates_the_collection_when_it_is_missing()
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/test', [])
            ->once()
            ->andThrow(new ObjectNotFound);

        $apiCall->shouldReceive('post')
            ->with('/collections', Mockery::type('array'), true, [])
            ->once()
            ->andReturn(['name' => 'test']);

        $index = $this->indexWithMockedApi($apiCall);

        $index->getOrCreateIndex();
        $index->getOrCreateIndex();
    }
}
