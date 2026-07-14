<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades;
use StatamicRadPack\Typesense\Exceptions\ImportFailedException;
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

    #[Test]
    public function it_sorts_by_specified_order()
    {
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

        $results = Facades\Search::index('typesense_index')->searchUsingApi('*', ['sort_by' => 'title:asc']);

        $this->assertSame(['Entry 1', 'Entry 2'], collect($results['results'])->pluck('title')->all());

        $results = Facades\Search::index('typesense_index')->searchUsingApi('*', ['sort_by' => 'title:desc']);

        $this->assertSame(['Entry 2', 'Entry 1'], collect($results['results'])->pluck('title')->all());
    }

    #[Test]
    public function it_logs_a_warning_when_documents_are_rejected_during_import()
    {
        $this->configureFailingIndex();

        Log::spy();

        Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        Facades\Entry::make()
            ->id('test-1')
            ->collection('pages')
            ->data(['title' => 'Entry 1'])
            ->save();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'typesense_failing_index')
                    && str_contains($message, '1 document(s)')
                    && $context['failures'][0]['id'] === 'entry::test-1'
                    && ! empty($context['failures'][0]['error']);
            });
    }

    #[Test]
    public function it_throws_when_documents_are_rejected_during_import_and_strict_mode_is_enabled()
    {
        config()->set('statamic-typesense.throw_on_import_failure', true);

        $this->configureFailingIndex();

        Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        try {
            Facades\Entry::make()
                ->id('test-1')
                ->collection('pages')
                ->data(['title' => 'Entry 1'])
                ->save();

            $this->fail('ImportFailedException was not thrown.');
        } catch (ImportFailedException $e) {
            $this->assertSame('typesense_failing_index', $e->index());
            $this->assertCount(1, $e->failures());
            $this->assertNotEmpty($e->failures()[0]['error']);
        }
    }

    #[Test]
    public function it_does_not_log_or_throw_when_all_documents_import_successfully()
    {
        config()->set('statamic-typesense.throw_on_import_failure', true);

        Log::spy();

        Facades\Collection::make()
            ->handle('pages')
            ->title('Pages')
            ->save();

        Facades\Entry::make()
            ->id('test-1')
            ->collection('pages')
            ->data(['title' => 'Entry 1'])
            ->save();

        Log::shouldNotHaveReceived('warning');

        $export = collect(json_decode('['.str_replace("\n", ',', Facades\Search::index('typesense_index')->getOrCreateIndex()->documents->export()).']'))->pluck('id');

        $this->assertContains('entry::test-1', $export);
    }

    private function configureFailingIndex()
    {
        // Typesense will reject documents whose title is a string,
        // since the schema declares it as an int32.
        config()->set('statamic.search.indexes.typesense_failing_index', [
            'driver' => 'typesense',
            'searchables' => ['collection:pages'],
            'settings' => [
                'schema' => [
                    'fields' => [
                        [
                            'type' => 'int32',
                            'name' => 'title',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
