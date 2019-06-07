<?php

namespace Wehaa\ScoutElasticsearch;

use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Wehaa\ScoutElasticsearch\Engines\ElasticEngine;

class ScoutElasticsearchServiceProvider extends ServiceProvider
{

    public function boot()
    {
        resolve(EngineManager::class)->extend(
            'elasticsearch', function () {

                $es_config = config('scout.elasticsearch');
                
                return new ElasticEngine(
                    ClientBuilder::create()->setHosts($es_config['hosts'])
                    ->setRetries($es_config['retries'])
                    ->build()
                );
            }
        );
    }
    
}
