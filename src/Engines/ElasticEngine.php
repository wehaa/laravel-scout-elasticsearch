<?php

namespace Wehaa\ScoutElasticsearch\Engines;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Elasticsearch\Client as Elastic;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElasticEngine extends Engine
{

    /**
     * The Elasticsearch client.
     *
     * @var \Elasticsearch\Client
     */
    protected $elastic;

    /**
     * Create a new engine instance.
     *
     * @param  \Elasticsearch\Client $elastic
     * @return void
     */
    public function __construct(Elastic $elastic)
    {
        $this->elastic = $elastic;
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @throws \Elasticsearch\Common\Exceptions\ElasticsearchException
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }
        $params = [];
        $index = $this->initIndex($models->first());

        if ($this->usesSoftDelete($models->first()) && config('scout.soft_delete', false)) {
            $models->each->pushSoftDeleteMetadata();
        }
        
        $models->map(
            function ($model) use (&$params, $index) {
                $array = array_merge($model->toSearchableArray(), $model->scoutMetadata());

                if (!empty($array)) {
                    $index['_id'] = $model->getScoutKey();
                    $params['body'][] = [
                    'update' => $index
                    ];
                    $params['body'][] = [
                    'doc' => $array,
                    'doc_as_upsert' => true,
                    ];
                }
            }
        );
        if (! empty($params)) {
            $this->elastic->bulk($params);
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function delete($models)
    {
        $index = $this->initIndex($models->first());
        $params = [];
        $models->map(
            function ($model) use (&$params, $index) {
                $index['_id'] = $model->getScoutKey();
                $params['body'][] = [
                'delete' => $index
                ];
            }
        );
        if (! empty($params)) {
            $this->elastic->bulk($params);
        }
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch(
            $builder, array_filter(
                [
                'numericFilters' => $this->filters($builder),
                'hitsPerPage' => $builder->limit,
                ]
            )
        );
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  int                    $perPage
     * @param  int                    $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch(
            $builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
            ]
        );
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder              $builder
     * @param  mixed                               $results
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }
        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();
        return $model->getScoutModelsByIds(
            $builder, $keys
        )->filter(
            function ($model) use ($keys) {
                    return in_array($model->getScoutKey(), $keys);
            }
        );
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function flush($model)
    {
        $index = $this->initIndex($model);
        $params = [
            'index' => $index['_index'],
            'type'  => $index['_type'],
            'body' => [
                'query' => [
                    'match_all' => []
                ]
            ]
        ];
        return $this->elastic->deleteByQuery($params);
    }

    /**
     * Drop index from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function drop($model)
    {
        $index = $this->initIndex($model);
        $params = [
            'index' => $index['_index'],
            'type'  => $index['_type']
        ];
        return $this->elastic->indices()->delete($params);
    }
    
    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Config index and type with model
     *
     * @param string $index
     */
    protected function initIndex(Model $model)
    {
        $index = $model->searchableAs();
        $params = [
            '_index' => $index,
            '_type' => $index,
        ];
        return $params;
    }
    
    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(
            function ($value, $key) {
                if (is_array($value)) {
                    return ['terms' => [$key => $value]];
                }
                return ['match_phrase' => [$key => $value]];
            }
        )->values()->all();
    }
    
    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  array                  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $index = $this->initIndex($builder->model);
        $params = [
            'index' => $index['_index'],
            'type' => $index['_type'],
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'query_string' => [
                                    'query' => "*{$builder->query}*"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }
        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }
        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }
        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }
        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elastic,
                $builder->query,
                $params
            );
        }
        return $this->elastic->search($params);
    }
    
    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }
        return collect($builder->orders)->map(
            function ($order) {
                return [$order['column'] => $order['direction']];
            }
        )->toArray();
    }
}