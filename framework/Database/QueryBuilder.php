<?php

declare(strict_types=1);

namespace Framework\Database;

use InvalidArgumentException;

/**
 * Chainable SQL builder. Values are always bound with placeholders;
 * identifiers (columns, directions) are validated and never come from input.
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array<string, mixed>> */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $bindings = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var class-string<Model>|null */
    private ?string $model = null;

    public function __construct(private Connection $connection, private string $table)
    {
    }

    /**
     * Tell the builder which model to hydrate rows into.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function select($columns = ['*']): self
    {
        $this->columns = is_array($columns) ? array_values($columns) : func_get_args();

        return $this;
    }

    public function where(string $column, $operator = null, $value = null, string $boolean = 'and'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper((string) $operator);

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Unsupported operator [$operator].");
        }

        if ($value === null && ! in_array($operator, ['=', '!=', '<>'], true)) {
            throw new InvalidArgumentException("Operator [$operator] cannot be used with a null value.");
        }

        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => strtoupper($boolean) === 'OR' ? 'or' : 'and',
        ];

        if ($value !== null) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orWhere(string $column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'or');
        }

        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
            'boolean' => 'and',
        ];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->assertIdentifier($column);
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Unsupported order direction [$direction].");
        }

        $this->orders[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function limit(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Limit must be zero or greater.');
        }

        $this->limit = $value;

        return $this;
    }

    public function offset(int $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Offset must be zero or greater.');
        }

        $this->offset = $value;

        return $this;
    }

    public function get(): Collection
    {
        $rows = $this->connection->select($this->toSql(), $this->bindings);

        if ($this->model === null) {
            return new Collection($rows);
        }

        return new Collection(array_map(function (array $row) {
            $class = $this->model;

            return (new $class())->newFromBuilder($row);
        }, $rows));
    }

    /**
     * @return Model|array|null
     */
    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    public function count(): int
    {
        $query = clone $this;
        $query->columns = ['count(*) as aggregate'];
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;

        $rows = $query->connection->select($query->toSql(), $query->bindings);

        return (int) ($rows[0]['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $attributes): bool
    {
        return $this->connection->execute($this->compileInsert($attributes), array_values($attributes)) > 0;
    }

    public function insertGetId(array $attributes): string
    {
        $this->connection->execute($this->compileInsert($attributes), array_values($attributes));

        return $this->connection->lastInsertId();
    }

    public function update(array $values): int
    {
        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $this->assertIdentifier($column);
            $sets[] = $column . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'update ' . $this->table . ' set ' . implode(', ', $sets);

        if ($this->wheres !== []) {
            $sql .= ' where ' . $this->compileWheres();
        }

        return $this->connection->execute($sql, array_merge($bindings, $this->bindings));
    }

    public function delete(): int
    {
        $sql = 'delete from ' . $this->table;

        if ($this->wheres !== []) {
            $sql .= ' where ' . $this->compileWheres();
        }

        return $this->connection->execute($sql, $this->bindings);
    }

    public function pluck(string $column): Collection
    {
        $this->assertIdentifier($column);

        $query = clone $this;
        $query->columns = [$column];

        return $query->get()->pluck($column);
    }

    public function toSql(): string
    {
        $sql = 'select ' . implode(', ', $this->columns) . ' from ' . $this->table;

        if ($this->wheres !== []) {
            $sql .= ' where ' . $this->compileWheres();
        }

        if ($this->orders !== []) {
            $sql .= ' order by ' . implode(', ', array_map(static function (array $order): string {
                return $order['column'] . ' ' . $order['direction'];
            }, $this->orders));
        }

        if ($this->limit !== null) {
            $sql .= ' limit ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' offset ' . $this->offset;
        }

        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function compileInsert(array $attributes): string
    {
        if ($attributes === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = array_keys($attributes);

        foreach ($columns as $column) {
            $this->assertIdentifier((string) $column);
        }

        return 'insert into ' . $this->table
            . ' (' . implode(', ', $columns) . ')'
            . ' values (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    }

    private function compileWheres(): string
    {
        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : ' ' . $where['boolean'] . ' ';

            if ($where['type'] === 'in') {
                $values = $where['values'];

                if ($values === []) {
                    $sql .= $prefix . '1 = 0';
                    continue;
                }

                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $sql .= $prefix . $where['column'] . ' in (' . $placeholders . ')';
                continue;
            }

            if ($where['value'] === null && $where['operator'] === '=') {
                $sql .= $prefix . $where['column'] . ' is null';
                continue;
            }

            if ($where['value'] === null && in_array($where['operator'], ['!=', '<>'], true)) {
                $sql .= $prefix . $where['column'] . ' is not null';
                continue;
            }

            $sql .= $prefix . $where['column'] . ' ' . $where['operator'] . ' ?';
        }

        return $sql;
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid column identifier [$identifier].");
        }
    }
}
