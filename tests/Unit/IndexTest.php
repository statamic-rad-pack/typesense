<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades;
use StatamicRadPack\Typesense\Tests\TestCase;
use Typesense\Client;

class IndexTest extends TestCase
{
    #[Test]
    public function it_sets_up_the_client_correctly()
    {
        $index = Facades\Search::index('typesense_index');

        $this->assertInstanceOf(Client::class, $index->client());
    }

    #[Test]
    public function it_adds_documents_to_the_index()
    {
        $collection = Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        $entry1 = Facades\Entry::make()
            ->id('test-2')
            ->collection('pages')
            ->data(['title' => 'Entry 1'])
            ->save();

        $entry2 = Facades\Entry::make()
            ->id('test-1')
            ->collection('pages')
            ->data(['title' => 'Entry 2'])
            ->save();

        $index = Facades\Search::index('typesense_index');

        $export = collect(json_decode('['.str_replace("\n", ',', $index->getOrCreateIndex()->documents->export()).']'))->pluck('id');

        $this->assertContains('entry::test-1', $export);
        $this->assertContains('entry::test-2', $export);
    }

    #[Test]
    public function it_updates_documents_to_the_index()
    {
        $collection = Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        $entry1 = Facades\Entry::make()
            ->id('test-2')
            ->collection('pages')
            ->data(['title' => 'Entry 1'])
            ->save();

        $entry2 = tap(Facades\Entry::make()
            ->id('test-1')
            ->collection('pages')
            ->data(['title' => 'Entry 2']))
            ->save();

        $index = Facades\Search::index('typesense_index');

        $export = collect(json_decode('['.str_replace("\n", ',', $index->getOrCreateIndex()->documents->export()).']'))->pluck('title');

        $this->assertContains('Entry 1', $export);
        $this->assertContains('Entry 2', $export);

        $entry2->merge(['title' => 'Entry 2 Updated'])->save();

        $export = collect(json_decode('['.str_replace("\n", ',', $index->getOrCreateIndex()->documents->export()).']'))->pluck('title');

        $this->assertContains('Entry 2 Updated', $export);
    }

    #[Test]
    public function it_removes_documents_from_the_index()
    {
        $collection = Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        $entry1 = Facades\Entry::make()
            ->id('test-2')
            ->collection('pages')
            ->data(['title' => 'Entry 1'])
            ->save();

        $entry2 = tap(Facades\Entry::make()
            ->id('test-1')
            ->collection('pages')
            ->data(['title' => 'Entry 2']))
            ->save();

        $entry2->delete();

        $index = Facades\Search::index('typesense_index');

        $export = collect(json_decode('['.str_replace("\n", ',', $index->getOrCreateIndex()->documents->export()).']'))->pluck('id');

        $this->assertNotContains('entry::test-1', $export);
        $this->assertContains('entry::test-2', $export);
    }
}
