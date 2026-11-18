<?php

declare(strict_types=1);

namespace Yawasla\Model;

use Medoo\Medoo;
use Yawasla\Database\Connection;

final class Organization
{
    public const FIELDS = [
        'name' => ['required' => true, 'max' => 255],
        'identifier' => ['max' => 50],
        'address' => ['max' => 255],
        'zip_code' => ['max' => 20],
        'city' => ['max' => 100],
        'country' => ['max' => 100],
        'email' => ['max' => 255, 'type' => 'email'],
        'phone' => ['max' => 30],
        'fax' => ['max' => 30],
        'website' => ['max' => 255, 'type' => 'url'],
    ];

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->normalize($this->db()->get('organization', $this->columns(), ['id' => $id]));
    }

    /** @return array<string, mixed>|null */
    public function main(): ?array
    {
        return $this->normalize($this->db()->get('organization', $this->columns(), ['parent_id' => null, 'ORDER' => ['id' => 'ASC']]));
    }

    /** @return list<array<string, mixed>> */
    public function secondaries(int $mainId): array
    {
        $rows = $this->db()->select('organization', $this->columns(), ['parent_id' => $mainId, 'ORDER' => ['name' => 'ASC']]);

        return array_values(array_filter(array_map($this->normalize(...), $rows)));
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        $name = mb_strtolower($name);

        foreach ($this->db()->select('organization', ['id', 'name']) as $row) {
            if ((int) $row['id'] !== $exceptId && mb_strtolower((string) $row['name']) === $name) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $values */
    public function create(array $values, int $parentId): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db()->insert('organization', $this->row($values) + ['parent_id' => $parentId, 'created_at' => $now, 'updated_at' => $now]);

        return (int) $this->db()->id();
    }

    /** @param array<string, string> $values */
    public function update(int $id, array $values): void
    {
        $this->db()->update('organization', $this->row($values) + ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public function used(int $id): bool
    {
        $db = $this->db();

        return $db->has('user', ['organization_id' => $id])
            || $db->has('news', ['organization_id' => $id])
            || $db->has('janaza', ['organization_id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db()->delete('organization', ['id' => $id, 'parent_id[!]' => null]);
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string|null>
     */
    private function row(array $values): array
    {
        $row = [];

        foreach (array_keys(self::FIELDS) as $name) {
            $value = $values[$name] ?? '';
            $row[$name] = $value === '' ? null : $value;
        }

        return $row;
    }

    /** @return list<string> */
    private function columns(): array
    {
        return ['id', 'parent_id', ...array_keys(self::FIELDS)];
    }

    /** @return array<string, mixed>|null */
    private function normalize(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];

        return $row;
    }

    private function db(): Medoo
    {
        return Connection::getInstance()->medoo();
    }
}
