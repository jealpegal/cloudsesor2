<?php
/**
 * Modelo SensorVariable: variables de medida o calculadas por sensor.
 */

class SensorVariableModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Listar variables de un sensor */
    public function getBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, type, unit, created_at FROM sensor_variables WHERE sensor_id = ? ORDER BY name"
        );
        $stmt->execute([$sensorId]);
        return $stmt->fetchAll();
    }

    /** Obtener variable por ID */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, type, unit FROM sensor_variables WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Obtener variable por sensor_id y nombre (para resolver nombres en fórmulas) */
    public function getByNameAndSensor(int $sensorId, string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, type, unit FROM sensor_variables WHERE sensor_id = ? AND name = ?"
        );
        $stmt->execute([$sensorId, $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Crear variable */
    public function create(int $sensorId, string $name, string $type = 'measure', ?string $unit = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sensor_variables (sensor_id, name, type, unit) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$sensorId, $name, $type, $unit ?? '']);
        return (int) $this->pdo->lastInsertId();
    }

    /** Actualizar variable */
    public function update(int $id, string $name, ?string $unit = null): bool
    {
        $stmt = $this->pdo->prepare("UPDATE sensor_variables SET name = ?, unit = ? WHERE id = ?");
        return $stmt->execute([$name, $unit ?? '', $id]);
    }

    /** Eliminar variable */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sensor_variables WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
