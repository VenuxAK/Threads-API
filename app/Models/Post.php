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

    protected $collection = "posts";

    protected $fillable = ["content", "tags"];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = Auth::id();
            }
        });

        static::created(function ($model) {
            try {
                // Create PostMetaData in MySQL
                PostMetaData::create([
                    "post_id" => $model->id,
                    "user_id" => $model->user_id
                ]);
            } catch (\Exception $e) {
                // Log the error
                Log::error('Failed to create PostMetaData for post: ' . $model->id, [
                    'error' => $e->getMessage(),
                    'post_id' => $model->id,
                    'user_id' => $model->user_id
                ]);

                // Re-throw the exception to fail the entire operation
                throw $e;
            }
        });

        static::deleting(function ($model) {
            try {
                // Delete PostMetaData from MySQL
                PostMetaData::where("post_id", $model->id)->delete();
            } catch (\Exception $e) {
                Log::error('Failed to delete PostMetaData for post: ' . $model->id, [
                    'error' => $e->getMessage(),
                    'post_id' => $model->id
                ]);

                // Don't throw here to allow post deletion to proceed
                // The metadata will be orphaned but that's better than not deleting the post
            }
        });
    }

    /**
     * Get the post metadata
     */
    public function metadata()
    {
        return $this->hasOne(PostMetaData::class, 'post_id', 'id');
    }

    /**
     * Get the user who created the post
     * Note: This is a cross-database relationship (MongoDB to MySQL)
     * We'll handle this manually in the transformer
     */
    public function user()
    {
        // Since this is cross-database, we can't use Eloquent relationships directly
        // We'll handle user loading in the transformer
        return null;
    }
}
