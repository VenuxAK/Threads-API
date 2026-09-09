<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for bookmarked posts stored in MySQL.
 * Links MySQL user identities to MongoDB post document identifiers.
 * Query results are hydrated using DataLoader batch lookups on the MongoDB posts collection.
 */
class SavedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
    ];

    /**
     * The user who saved this post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
