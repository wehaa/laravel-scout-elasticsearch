<?php

namespace Wehaa\Search;

use Elasticsearch\ClientBuilder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Wehaa\Search\Engines\ElasticEngine;

class ElasticServiceProvider extends ServiceProvider
{

    public function boot()
    {
        resolve(EngineManager::class)->extend('elasticsearch', function () {
            $es_config = config::get('scout.elasticsearch');
            return new ElasticEngine(ClientBuilder::create()->setHosts($es_config['hosts'])
                ->setRetries($es_config['retries'])
                ->build());
        });
    }
    
    public function register()
    {
        //
    }
}
