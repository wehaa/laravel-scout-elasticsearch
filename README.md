# Laravel Scout Elasticsearch Driver

This package makes use of the [Elasticsearch](https://www.elastic.co/products/elasticsearch) driver for Laravel Scout.

Supporting Laravel and 5.7+

## Installation

You can install the package via composer:

```bash
composer require wehaa/laravel-scout-elasticsearch
```

### Setting up Elasticsearch configuration

This command will publish the scout.php configuration file to your config directory:

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```
Then add the `elasticsearch` key in the array on `config/scout.php` file.
```bach
     'elasticsearch' => [
        /*
        |--------------------------------------------------------------------------
        | Custom Elasticsearch Client Configuration
        |--------------------------------------------------------------------------
        |
        | This array will be passed to the Elasticsearch client.
        | See configuration options here:
        |
        | http://www.elasticsearch.org/guide/en/elasticsearch/client/php-api/current/_configuration.html
        */
        
        'hosts' => [
            [
                'host' => env('ELASTICSEARCH_HOST', 'localhost'),
                'port' => env('ELASTICSEARCH_PORT', '9200'),
                'scheme' => '', // http or https
                'user' => '',
                'pass' => ''
            ]
        ],
        
        /**
         * The client will retry n times
         */
        'retries' => env('ELASTICSEARCH_RETRIES', 3),
        
        /*
        |--------------------------------------------------------------------------
        | Default Index Name
        |--------------------------------------------------------------------------
        |
        | This is the index name use for all models
        */
        
        'default_index' => 'wehaa',
     ]
```


### .env file example:

```bash
SCOUT_DRIVER=elasticsearch
ELASTICSEARCH_HOST=http://127.0.0.1
ELASTICSEARCH_PORT=9200
```

## Usage:
Now you can use Laravel Scout as described in the [official documentation](https://laravel.com/docs/5.7/scout)

###Batch Import
If you are installing Scout into an existing project, you may already have database records you need to import into your search driver. Scout provides an import Artisan command that you may use to import all of your existing records into your search indexes:

```bash
php artisan scout:import "App\Post"
```

The flush command may be used to remove all of a model's records from your search indexes:

```bash
php artisan scout:flush "App\Post"
```