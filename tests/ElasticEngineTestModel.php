<?php

namespace Wehaa\ScoutElasticsearch\Test;

use Laravel\Scout\Searchable;
use \Illuminate\Database\Eloquent\Model;

class ElasticEngineTestModel extends Model
{
    use Searchable;
    
    public $id = 1;
    
    public function getIdAttribute()
    {
        return 1;
    }
    
    public function searchableAs()
    {
        return 'test-engine-elastic';
    }
    
    public function getKey()
    {
        return '1';
    }
    
    public function getScoutKey()
    {
        return $this->id;
    }
    
    public function toSearchableArray()
    {
        return ['id' => 1, 'name' => 'abcdef'];
    }
    
    public function shouldBeSearchable()
    {
        return $this->isPublished();
    }
}