<?php

namespace StatamicRadPack\Typesense\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades;
use Statamic\Query\OrderBy;
use StatamicRadPack\Typesense\Typesense\Query;
use StatamicRadPack\Typesense\Tests\TestCase;

class QueryTest extends TestCase
{
    #[Test]
    public function it_returns_simple_wheres_in_the_correct_format()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);
        $query->where('title', 'test');

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'type' => 'string',
            ],
            [
                'name' => 'other',
                'type' => 'string',
            ],
            [
                'name' => 'final',
                'type' => 'int32',
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('wheresToFilter');
        $method->setAccessible(true);

        $property = $reflection->getProperty('wheres');
        $property->setAccessible(true);

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:=`test` ) ');

        $query->orWhere('other', 'value');

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:=`test` )  ||  ( other:=`value` ) ');

        $query->where('final', 'value');

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:=`test` )  ||  ( other:=`value` )  &&  ( final:=0 ) ');
    }

    #[Test]
    public function it_handles_where_ins()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);
        $query->whereIn('title', ['test', 'two', 'three']);

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'type' => 'string[]',
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('wheresToFilter');
        $method->setAccessible(true);

        $property = $reflection->getProperty('wheres');
        $property->setAccessible(true);

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:["`test`","`two`","`three`"] ) ');
    }

    #[Test]
    public function it_handles_where_like()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);
        $query->where('title', 'like', 'test');

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'type' => 'string',
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('wheresToFilter');
        $method->setAccessible(true);

        $property = $reflection->getProperty('wheres');
        $property->setAccessible(true);

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:`test` ) ');
    }

    #[Test]
    public function it_ignores_wheres_not_found_in_the_typesense_schema()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);
        $query->where('title', 'test');
        $query->orWhere('other', 'value');
        $query->where('final', 'value');

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'type' => 'string',
            ],
            [
                'name' => 'other',
                'type' => 'string',
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('wheresToFilter');
        $method->setAccessible(true);

        $property = $reflection->getProperty('wheres');
        $property->setAccessible(true);

        $result = $method->invoke($query, $property->getValue($query));

        $this->assertSame($result, ' ( title:=`test` )  ||  ( other:=`value` ) ');
    }

    #[Test]
    public function it_returns_sort_by_in_the_correct_format()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'sort' => true,
            ],
            [
                'name' => 'other',
                'sort' => true,
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('ordersToSortBy');
        $method->setAccessible(true);

        $orderBys = [
            new OrderBy('title', 'desc'),
            new OrderBy('other', 'asc'),
        ];

        $result = $method->invoke($query, $orderBys);

        $this->assertSame($result, 'title:desc,other:asc');
    }

    #[Test]
    public function it_ignores_sorts_that_arent_found_in_the_typesense_schema()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'sort' => true,
            ],
            [
                'name' => 'other',
                'sort' => false,
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('ordersToSortBy');
        $method->setAccessible(true);

        $orderBys = [
            new OrderBy('title', 'desc'),
            new OrderBy('other', 'asc'),
        ];

        $result = $method->invoke($query, $orderBys);

        $this->assertSame($result, 'title:desc');
    }

    #[Test]
    public function it_ignores_sorts_that_arent_sortable_in_the_typesense_schema()
    {
        $index = Facades\Search::index('typesense_index');
        $query = new Query($index);

        Facades\Blink::put('statamic-typesense::schema::typesense_index', collect([
            [
                'name' => 'title',
                'sort' => true,
            ],
            [
                'name' => 'other',
                'sort' => false,
            ],
        ]));

        $reflection = new \ReflectionObject($query);
        $method = $reflection->getMethod('ordersToSortBy');
        $method->setAccessible(true);

        $orderBys = [
            new OrderBy('title', 'desc'),
            new OrderBy('other', 'asc'),
        ];

        $result = $method->invoke($query, $orderBys);

        $this->assertSame($result, 'title:desc');
    }
}
