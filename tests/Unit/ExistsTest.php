<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Search\Documents;
use StatamicRadPack\Typesense\Tests\TestCase;
use Typesense\ApiCall;
use Typesense\Exceptions\ObjectNotFound;

class ExistsTest extends TestCase
{
    private function apiWithMissingCollection(string $name = 'test'): ApiCall
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/'.$name, [])
            ->andThrow(new ObjectNotFound);

        $apiCall->shouldNotReceive('post')->with('/collections', Mockery::any(), Mockery::any(), Mockery::any());

        return $apiCall;
    }

    #[Test]
    public function exists_is_false_when_the_collection_is_missing()
    {
        $index = $this->indexWithMockedApi($this->apiWithMissingCollection());

        $this->assertFalse($index->exists());
    }

    #[Test]
    public function exists_is_true_when_the_collection_is_present()
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/test', [])
            ->once()
            ->andReturn(['name' => 'test']);

        $this->assertTrue($this->indexWithMockedApi($apiCall)->exists());
    }

    #[Test]
    public function deleting_a_document_does_not_create_a_missing_collection()
    {
        $apiCall = $this->apiWithMissingCollection();

        $apiCall->shouldReceive('delete')
            ->with('/collections/test/documents/entry%3A%3A1', true, [])
            ->andThrow(new ObjectNotFound);

        $document = Mockery::mock();
        $document->shouldReceive('getSearchReference')->andReturn('entry::1');

        $this->indexWithMockedApi($apiCall)->delete($document);
    }

    #[Test]
    public function searching_a_missing_collection_returns_no_results_without_creating_it()
    {
        $apiCall = $this->apiWithMissingCollection();

        $apiCall->shouldNotReceive('post')->with('/multi_search', Mockery::any(), Mockery::any(), Mockery::any());

        $results = $this->indexWithMockedApi($apiCall)->searchUsingApi('*');

        $this->assertTrue($results['results']->isEmpty());
    }

    #[Test]
    public function update_creates_a_missing_collection_without_trying_to_delete_it()
    {
        $apiCall = Mockery::mock(ApiCall::class);

        $apiCall->shouldReceive('get')
            ->with('/collections/test', [])
            ->once()
            ->andThrow(new ObjectNotFound);

        $apiCall->shouldNotReceive('delete');

        $apiCall->shouldReceive('post')
            ->with('/collections', Mockery::type('array'), true, [])
            ->once()
            ->andReturn(['name' => 'test']);

        $this->indexWithMockedApi($apiCall)->update();
    }

    #[Test]
    public function count_is_zero_when_the_collection_is_missing()
    {
        $this->assertSame(0, $this->indexWithMockedApi($this->apiWithMissingCollection())->getCount());
    }

    #[Test]
    public function schema_fields_are_empty_when_the_collection_is_missing()
    {
        $fields = $this->indexWithMockedApi($this->apiWithMissingCollection())->getTypesenseSchemaFields();

        $this->assertTrue($fields->isEmpty());
    }

    #[Test]
    public function inserting_documents_creates_a_missing_collection()
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

        $apiCall->shouldReceive('post')
            ->with('/collections/test/documents/import', Mockery::any(), false, ['action' => 'upsert'])
            ->once()
            ->andReturn('{"success":true}');

        $this->indexWithMockedApi($apiCall)->insertDocuments(new Documents(['entry::1' => ['id' => 'entry::1']]));
    }
}
