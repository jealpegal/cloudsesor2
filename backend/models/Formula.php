<?php
/**
 * Modelo Formula: fórmulas que generan variables calculadas.
 */

class FormulaModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Listar fórmulas de un sensor */
    public function getBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.id, f.sensor_id, f.name, f.expression, f.result_variable_id, f.parameters, f.created_at,
                    v.name AS result_variable_name
             FROM formulas f
             JOIN sensor_variables v ON v.id = f.result_variable_id
             WHERE f.sensor_id = ? ORDER BY f.name"
        );
        $stmt->execute([$sensorId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['parameters'] = json_decode($r['parameters'], true) ?: [];
        }
        return $rows;
    }

    /** Obtener todas las fórmulas (para evaluar al recibir datos) */
    public function getActiveBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, expression, result_variable_id, parameters
             FROM formulas WHERE sensor_id = ?"
        );
        $stmt->execute([$sensorId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['parameters'] = json_decode($r['parameters'], true) ?: [];
        }
        return $rows;
    }

    /** Obtener fórmula por ID */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, expression, result_variable_id, parameters FROM formulas WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['parameters'] = json_decode($row['parameters'], true) ?: [];
        return $row;
    }

    /** Crear fórmula */
    public function create(int $sensorId, string $name, string $expression, int $resultVariableId, array $parameters = []): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO formulas (sensor_id, name, expression, result_variable_id, parameters) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $sensorId,
            $name,
            $expression,
            $resultVariableId,
            json_encode($parameters)
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Actualizar fórmula (incluyendo parámetros) */
    public function update(int $id, string $name, string $expression, array $parameters = []): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE formulas SET name = ?, expression = ?, parameters = ? WHERE id = ?"
        );
        return $stmt->execute([$name, $expression, json_encode($parameters), $id]);
    }

    /** Eliminar fórmula */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM formulas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
