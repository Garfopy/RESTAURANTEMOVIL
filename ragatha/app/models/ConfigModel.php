<?php
class ConfigModel extends BaseModel
{
    protected string $table = 'global_settings';

    public function get(string $clave, string $default = ''): string
    {
        $row = $this->queryOne('SELECT valor FROM global_settings WHERE clave = ?', [$clave]);
        return $row ? (string)$row['valor'] : $default;
    }

    public function set(string $clave, string $valor): bool
    {
        return $this->execute(
            'INSERT INTO global_settings (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor]
        );
    }

    public function getGrupo(string $grupo): array
    {
        return $this->query(
            'SELECT * FROM global_settings WHERE grupo = ? ORDER BY clave',
            [$grupo]
        );
    }

    public function guardarGrupo(string $grupo, array $data): bool
    {
        foreach ($data as $clave => $valor) {
            $this->set($clave, (string)$valor);
        }
        return true;
    }

    public function getAll(): array
    {
        $rows  = $this->query('SELECT * FROM global_settings ORDER BY grupo, clave');
        $result = [];
        foreach ($rows as $row) {
            $result[$row['clave']] = $row['valor'];
        }
        return $result;
    }
}
