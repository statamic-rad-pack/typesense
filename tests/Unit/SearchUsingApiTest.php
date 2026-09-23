<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use StatamicRadPack\Typesense\Tests\TestCase;
use StatamicRadPack\Typesense\Typesense\Index;
use Typesense\ApiCall;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Exceptions\TypesenseClientError;
use Typesense\MultiSearch;

class SearchUsingApiTest extends TestCase
{
    private function indexWithMultiSearchResult(array $result, string $name = 'test'): Index
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/'.$name, [])
            ->andReturn(['name' => $name]);

        $apiCall->shouldReceive('post')
            ->with('/multi_search', Mockery::type('array'), true, [])
            ->once()
            ->andReturn(['results' => [$result]]);

        $client = new Client([
            'api_key' => 'xyz',
            'nodes' => [['host' => 'localhost', 'port' => '8108', 'path' => '', 'protocol' => 'http']],
        ]);

        $client->collections = new Collections($apiCall);
        $client->multiSearch = new MultiSearch($apiCall);

        return new Index($client, $name, []);
    }

    #[Test]
    public function it_throws_the_error_from_a_failed_search()
    {
        $index = $this->indexWithMultiSearchResult([
            'code' => 400,
            'error' => 'Could not find a filter field named `foo` in the schema.',
        ]);

        $this->expectException(TypesenseClientError::class);
        $this->expectExceptionMessage('Could not find a filter field named `foo` in the schema.');
        $this->expectExceptionCode(400);

        $index->searchUsingApi('*');
    }

    #[Test]
    public function it_returns_the_hits_from_a_successful_search()
    {
        $index = $this->indexWithMultiSearchResult([
            'hits' => [
                ['document' => ['id' => 'entry::test-1', 'title' => 'Entry 1'], 'text_match' => 12],
            ],
        ]);

        $results = $index->searchUsingApi('*');

        $this->assertSame(['Entry 1'], $results['results']->pluck('title')->all());
    }
}
