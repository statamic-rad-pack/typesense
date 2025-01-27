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

        $schemaFields = $this->index->getTypesenseSchemaFields();

        foreach ($this->wheres as $where) {
            if ($filterBy != '') {
                $filterBy .= $where['boolean'] == 'and' ? ' && ' : ' || ';
            }

            if ($where['type'] == 'Nested') {
                $filterBy .= ' ( '.$this->wheresToFilter($where->query['wheres']).' ) ';

                continue;
            }

            // if its not in our typesense schema, we cant filter on it
            if (! $schemaType = $schemaFields->get($where['column'])) {
                continue;
            }

            $filterBy .= ' ( ';

            switch ($where['type']) {
                case 'JsonContains':
                case 'JsonOverlaps':
                case 'WhereIn':
                    $filterBy .= $where['column'].':'.$this->transformArrayOfValuesForTypeSense($schemaType, $where['values']);
                    break;

                case 'JsonDoesnContain':
                case 'JsonDoesntOverlap':
                case 'WhereNotIn':
                    $filterBy .= $where['column'].':!='.$this->transformArrayOfValuesForTypeSense($schemaType, $where['values']);
                    break;

                default:
                    if ($where['operator'] == 'like') {
                        $where['value'] = str_replace(['%"', '"%'], '', $where['value']);
                    }

                    $filterBy .= $where['column'].':'.(! in_array($where['operator'], ['like']) ? $where['operator'] : '').$this->transformValueForTypeSense($schemaType, $where['value']);
                    break;
            }

            $filterBy .= ' ) ';

        }

        return $filterBy;
    }

    private function transformArrayOfValuesForTypeSense(string $schemaType, array $values): string
    {
        return json_encode(
            collect($values)
                ->map(fn ($value) => $this->transformValueForTypeSense($schemaType, $value))
                ->values()
                ->all()
        );
    }

    private function transformValueForTypeSense(string $schemaType, mixed $value): mixed
    {
        return match (str_replace('[]', '', $schemaType)) {
            'int32', 'int64' => (int) $value,
            'float' => (float) $value,
            'bool' => $value ? 'true' : 'false',
            default => '`'.$value.'`'
        };
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
