<?php
/**
 * CarniHub — Base Model
 * All models extend this class.
 */
abstract class BaseModel
{
    protected PDO $db;
    protected string $table = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function find(int $id): ?array
    {
        return $this->queryOne("SELECT * FROM `{$this->table}` WHERE id = ?", [$id]);
    }

    public function all(): array
    {
        return $this->query("SELECT * FROM `{$this->table}`");
    }

    public function insert(array $data): int
    {
        $cols   = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $this->execute("INSERT INTO `{$this->table}` ($cols) VALUES ($places)", array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $vals = array_values($data);
        $vals[] = $id;
        return $this->execute("UPDATE `{$this->table}` SET $sets WHERE id = ?", $vals);
    }

    public function delete(int $id): bool
    {
        return $this->execute("DELETE FROM `{$this->table}` WHERE id = ?", [$id]);
    }

    public function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        if ($where) $sql .= " WHERE $where";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    protected function paginate(string $sql, array $params, int $page, int $perPage = PER_PAGE): array
    {
        $offset = ($page - 1) * $perPage;

        // Wrap in subquery for COUNT — handles subqueries inside SELECT
        $sqlNoOrder = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', $sql);
        $countSql   = "SELECT COUNT(*) FROM ({$sqlNoOrder}) AS _total";

        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $stmt2 = $this->db->prepare($sql . " LIMIT $perPage OFFSET $offset");
        $stmt2->execute($params);
        $rows = $stmt2->fetchAll();

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }
}
