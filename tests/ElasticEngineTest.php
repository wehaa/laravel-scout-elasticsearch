<?php

namespace Wehaa\Search\Test;

use Mockery;
use Laravel\Scout\Builder;
use Wehaa\Search\Engines\ElasticEngine;
use Illuminate\Database\Eloquent\Collection;
class ElasticEngineTest extends \PHPUnit\Framework\TestCase
{
    public function test_add()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $params = [
            'body' => [
                [
                    'update' => [
                        '_index' => 'test-engine-elastic',
                        '_type' => 'test-engine-elastic',
                        '_id' => 1
                    ]
                ],
                [
                    'doc' => ['id' => 1, 'name' => 'abcdef'],
                    'doc_as_upsert' => true
                ]
            ]
        ];
        $client->shouldReceive('bulk')->with($params);
        $engine = new ElasticEngine($client);
        $engine->update(Collection::make([new ElasticEngineTestModel]));
    }
    
    public function test_update()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $client->shouldReceive('bulk')->with([
            'body' => [
                [
                    'update' => [
                        '_id' => 1,
                        '_index' => 'test-engine-elastic',
                        '_type' => 'test-engine-elastic',
                    ]
                ],
                [
                    'doc' => ['id' => 1, 'name' => 'abcdef'],
                    'doc_as_upsert' => true
                ]
            ]
        ]);
        $engine = new ElasticEngine($client);
        $engine->update(Collection::make([new ElasticEngineTestModel]));
    }
    
    public function test_delete()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $client->shouldReceive('bulk')->with([
            'body' => [
                [
                    'delete' => [
                        '_id' => 1,
                        '_index' => 'test-engine-elastic',
                        '_type' => 'test-engine-elastic',
                    ]
                ],
            ]
        ]);
        $engine = new ElasticEngine($client);
        $engine->delete(Collection::make([new ElasticEngineTestModel]));
    }
    
    public function test_search()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $client->shouldReceive('search')->with([
            'index' => 'test-engine-elastic',
            'type' => 'test-engine-elastic',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['query_string' => ['query' => '*zonda*']],
                            ['match_phrase' => ['foo' => 1]],
                            ['terms' => ['bar' => [1, 3]]],
                        ]
                    ]
                ],
                'sort' => [
                    ['id' => 'desc']
                ]
            ]
        ]);
        $engine = new ElasticEngine($client);
        $builder = new Builder(new ElasticEngineTestModel, 'zonda');
        $builder->where('foo', 1);
        $builder->where('bar', [1, 3]);
        $builder->orderBy('id', 'desc');
        $engine->search($builder);
    }
    
    public function test_mapIds()
    {
        $result = [
            'hits' => [
                'total' => '1',
                'hits' => [
                    [
                        '_id' => '1'
                    ]
                ]
            ]
        ];
        $client = Mockery::mock('Elasticsearch\Client');
        $engine = new ElasticEngine($client, 'scout');
        $ids = $engine->mapIds($result);
        $this->assertEquals([1], $ids->toArray());
    }
    public function test_map()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $engine = new ElasticEngine($client, 'scout');
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getScoutKey')->andReturn('1');
        $model->shouldReceive('getScoutModelsByIds')->once()->with($builder, ['1'])->andReturn(Collection::make([$model]));
        $results = $engine->map($builder, [
            'hits' => [
                'total' => '1',
                'hits' => [
                    [
                        '_id' => '1'
                    ]
                ]
            ]
        ], $model);
        $this->assertEquals(1, count($results));
    }
    public function test_getTotalCount()
    {
        $client = Mockery::mock('Elasticsearch\Client');
        $engine = new ElasticEngine($client, 'scout');
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getScoutKey')->andReturn('1');
        $model->shouldReceive('getScoutModelsByIds')->once()->with($builder, ['1'])->andReturn(Collection::make([$model]));
        $result = [
            'hits' => [
                'total' => '1',
                'hits' => [
                    [
                        '_id' => '1'
                    ]
                ]
            ]
        ];
        $results = $engine->map($builder, $result, $model);
        $this->assertEquals(1, $engine->getTotalCount($result));
    }
    public function tearDown()
    {
        Mockery::close();
    }
}