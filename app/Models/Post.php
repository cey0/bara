<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Database\Model;
use Framework\Database\Relations\BelongsTo;
use Framework\Database\Relations\HasMany;

final class Post extends Model
{
    protected array $fillable = ['user_id', 'title', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
