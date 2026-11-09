<?php

declare(strict_types=1);

namespace Framework\Database\Relations;

use Framework\Database\Model;

/**
 * A relation knows the parent model, the related class, and the keys that
 * join them. getResults() performs the query when the relation is accessed.
 */
abstract class Relation
{
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $ownerKey
    ) {
    }

    /**
     * @return mixed A model, a collection, or null
     */
    abstract public function getResults();
}
