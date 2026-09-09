<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'posts';

    protected $fillable = ['content', 'tags', 'user_id'];

    public function metadata()
    {
        return $this->hasOne(PostMetaData::class, 'post_id', 'id');
    }
}
