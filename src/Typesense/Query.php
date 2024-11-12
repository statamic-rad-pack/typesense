<?php

namespace StatamicRadPack\Typesense\Typesense;

use Illuminate\Pagination\Paginator;
use Statamic\Search\QueryBuilder;

class Query extends QueryBuilder
{
    public $page;
    public $perPage;

    public $query;

    public function getSearchResults($query)
    {
        $this->query = $query;

        return $this;
    }

    private function getApiResults()
    {
        return $this->index->searchUsingApi($this->query ?? '', ['per_page' => $this->perPage, 'page' => $this->page]);
    }

    public function forPage($page, $perPage = null)
    {
        $this->page = $page;
        $this->perPage = $perPage;

        return $this;
    }

    public function getBaseItems()
    {
        $results = $this->getApiResults();

        return $this->transformResults($results['results']);
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->defaultPerPageSize();

        $this->forPage($page, $perPage);

        $results = $this->getApiResults();

        return $this->paginator($this->transformResults($results['results']), $results['raw']['found'], $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}
