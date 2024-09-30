<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use MongoDB\Laravel\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $connection = "mongodb";

    protected $colletion = "posts";

    protected $fillable = ["content", "tags"];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Log::debug($model);
            if (empty($model->user_id)) {
                $model->user_id = Auth::id();
            }
        });

        static::created(function ($model) {
            PostMetaData::create(["post_id" => $model->id]); // "user_id" => Auth::id()
        });

        static::deleting(function ($model) {
            // Log::debug($model);
            PostMetaData::where("post_id", $model->id)->delete();
        });
    }
}
