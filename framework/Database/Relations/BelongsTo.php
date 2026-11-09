<?php

declare(strict_types=1);

namespace Framework\Database\Relations;

use Framework\Database\Model;

/**
 * Inverse of hasMany: the parent holds the foreign key.
 * Comment belongsTo User => comment.user_id = user.id
 */
final class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        $value = $this->parent->getAttribute($this->foreignKey);

        if ($value === null) {
            return null;
        }

        /** @var class-string<\Framework\Database\Model> $related */
        $related = $this->related;

        return $related::query()
            ->where($this->ownerKey, '=', $value)
            ->first();
    }
}
