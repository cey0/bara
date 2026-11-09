<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Database\Model;
use Framework\Database\Relations\BelongsTo;

final class Comment extends Model
{
    protected array $fillable = ['post_id', 'user_id', 'body'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
