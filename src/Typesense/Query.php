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

    public function whereStatus($string)
    {
        return $this->where('status', $string);
    }

    private function wheresToFilter(array $wheres): string
    {
        $filterBy = '';

        foreach ($this->wheres as $where) {

            if ($filterBy != '') {
                $filterBy .= $where['boolean'] == 'and' ? ' && ' : ' || ';
            }

            $filterBy .= ' ( ';

            switch ($where['type']) {
                case 'JsonContains':
                case 'JsonOverlaps':
                case 'WhereIn':
                    $filterBy .= $where['column'].':'.json_encode($where['values']);
                    break;

                case 'JsonDoesnContain':
                case 'JsonDoesntOverlap':
                case 'WhereNotIn':
                    $filterBy .= $where['column'].':!='.json_encode($where['values']);
                    break;

                case 'Nested':
                    $filterBy .= $this->wheresToFilter($where->query['wheres']);

                default:
                    $value = ! (is_int($where['value']) || is_float($where['value'])) ? '`'.$where['value'].'`' : $where['value'];
                    $filterBy .= $where['column'].':'.($where['operator'] != '=' ? $where['operator'] : '').$value;
                    break;
            }

            $filterBy .= ' ) ';

        }

        return $filterBy;
    }

    private function getApiResults()
    {
        $options = ['per_page' => $this->perPage, 'page' => $this->page];

        $filterBy = $this->wheresToFilter($this->wheres);

        if ($filterBy) {
            $options['filter_by'] = $filterBy;
        }

        return $this->index->searchUsingApi($this->query ?? '', $options);
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
