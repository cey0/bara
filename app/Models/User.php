<?php

declare(strict_types=1);

namespace App\Models;

use Framework\Database\Model;
use Framework\Database\Relations\HasMany;

/**
 * Table is inferred from the class name: User => users.
 */
final class User extends Model
{
    protected array $fillable = ['name', 'email'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
