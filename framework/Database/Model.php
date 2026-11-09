<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Application;
use Framework\Database\Relations\BelongsTo;
use Framework\Database\Relations\HasMany;
use Framework\Database\Relations\Relation;
use Framework\Exceptions\ModelNotFoundException;
use JsonSerializable;
use LogicException;
use ReflectionClass;

/**
 * Active Record base model. Each model owns its attributes and knows how to
 * persist itself; queries still go through QueryBuilder so they stay bound.
 *
 * @implements JsonSerializable
 */
abstract class Model implements JsonSerializable
{
    /** Set to override the inferred table name. */
    protected ?string $table = null;

    protected string $primaryKey = 'id';

    protected bool $timestamps = true;

    /**
     * Columns allowed in fill()/create(). Empty means mass assignment is
     * blocked outright rather than silently trusting any input.
     *
     * @var list<string>
     */
    protected array $fillable = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    /** @var array<string, mixed> */
    protected array $relations = [];

    protected bool $exists = false;

    /** Name of the relation currently being lazily loaded, used to guess keys. */
    private ?string $loadingRelation = null;

    private static ?Connection $connection = null;

    public function __construct(array $attributes = [])
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }
    }

    /**
     * Override the connection, mainly for tests.
     */
    public static function setConnection(?Connection $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * The schema is derived from the class name: Post => posts.
     */
    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->className())) . 's';
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey()
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function newQuery(): QueryBuilder
    {
        return (new QueryBuilder(static::connection(), $this->getTable()))->setModel(static::class);
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    /**
     * Forward static calls to a fresh query: Post::where(...)->get().
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return (new static())->newQuery()->{$method}(...$arguments);
    }

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function find($id): ?static
    {
        $model = new static();

        return $model->newQuery()->where($model->getKeyName(), '=', $id)->first();
    }

    public static function findOrFail($id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new ModelNotFoundException(static::class . ' dengan kunci [' . $id . '] tidak ditemukan.');
        }

        return $model;
    }

    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    public function fill(array $attributes): static
    {
        if ($this->fillable === []) {
            throw new LogicException(static::class . ' harus mendefinisikan properti $fillable untuk mass assignment.');
        }

        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function setAttribute(string $key, $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Read an attribute, or lazily load a relation defined as a method.
     */
    public function __get(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $this->loadingRelation = $key;

            try {
                $relation = $this->{$key}();
            } finally {
                $this->loadingRelation = null;
            }

            if ($relation instanceof Relation) {
                return $this->relations[$key] = $relation->getResults();
            }
        }

        return null;
    }

    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Resolve through __get so `isset($model->relation)` and the `??` operator
     * trigger lazy loading instead of silently seeing "not set".
     */
    public function __isset(string $key): bool
    {
        return $this->__get($key) !== null;
    }

    public function save(): bool
    {
        $query = $this->newQuery();
        $key = $this->getKeyName();

        if ($this->exists) {
            $dirty = $this->getDirty();

            if ($dirty === []) {
                return true;
            }

            if ($this->timestamps) {
                $this->attributes['updated_at'] = $this->freshTimestamp();
                $dirty['updated_at'] = $this->attributes['updated_at'];
            }

            $query->where($key, '=', $this->getKey())->update($dirty);
            $this->syncOriginal();

            return true;
        }

        if ($this->timestamps) {
            $now = $this->freshTimestamp();
            $this->attributes['created_at'] = $this->attributes['created_at'] ?? $now;
            $this->attributes['updated_at'] = $this->attributes['updated_at'] ?? $now;
        }

        $id = $query->insertGetId($this->attributes);

        if ($this->getKey() === null) {
            $this->attributes[$key] = ctype_digit($id) ? (int) $id : $id;
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        $this->newQuery()->where($this->getKeyName(), '=', $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original) || $this->original[$key] != $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->relations as $key => $value) {
            if ($value instanceof self) {
                $attributes[$key] = $value->toArray();
            } elseif ($value instanceof Collection) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Build a model from a raw database row.
     */
    public function newFromBuilder(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;
        $model->exists = true;

        return $model;
    }

    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        return new HasMany(
            $this,
            $related,
            $foreignKey ?? $this->getForeignKey(),
            $localKey ?? $this->getKeyName()
        );
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        // When loaded as $post->user, `user` is the relation name and thus the
        // default foreign key (user_id). Explicit keys are always allowed.
        $foreignKey = $foreignKey ?? ($this->loadingRelation !== null
            ? $this->loadingRelation . '_id'
            : $this->getForeignKey());

        $ownerKey = $ownerKey ?? (new $related())->getKeyName();

        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    private function getForeignKey(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->className())) . '_id';
    }

    private function className(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    private static function connection(): Connection
    {
        return self::$connection ??= Application::getInstance()->container()->make(Connection::class);
    }
}
