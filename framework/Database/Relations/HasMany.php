<?php

declare(strict_types=1);

namespace Framework\Database\Relations;

use Framework\Database\Collection;

/**
 * One-to-many: the related table holds the foreign key.
 * Post hasMany Comment => comments.post_id = post.id
 */
final class HasMany extends Relation
{
    public function getResults(): Collection
    {
        /** @var class-string<\Framework\Database\Model> $related */
        $related = $this->related;

        return $related::query()
            ->where($this->foreignKey, '=', $this->parent->getAttribute($this->ownerKey))
            ->get();
    }
}
