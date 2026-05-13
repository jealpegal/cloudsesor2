<?php
/**
 * Modelo Measurement: almacenar mediciones (valores de variables por sensor y tiempo).
 */

class MeasurementModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Insertar una medición */
    public function insert(int $sensorId, int $variableId, float $value, ?string $measuredAt = null): int
    {
        $measuredAt = $measuredAt ?: date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "INSERT INTO measurements (sensor_id, variable_id, value, measured_at) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$sensorId, $variableId, $value, $measuredAt]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Últimas mediciones de un sensor (para dashboard o histórico) */
    public function getLatestBySensor(int $sensorId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.sensor_id, m.variable_id, m.value, m.measured_at, v.name AS variable_name
             FROM measurements m
             JOIN sensor_variables v ON v.id = m.variable_id
             WHERE m.sensor_id = ?
             ORDER BY m.measured_at DESC
             LIMIT ?"
        );
        $stmt->execute([$sensorId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Mediciones de un sensor (y opcionalmente una variable) ordenadas por tiempo ASC (para gráficas).
     * variable_id = null → todas las variables; si se pasa, solo esa variable.
     */
    public function getForChart(int $sensorId, ?int $variableId = null, int $limit = 200): array
    {
        $sql = "SELECT m.id, m.sensor_id, m.variable_id, m.value, m.measured_at, v.name AS variable_name
                FROM measurements m
                JOIN sensor_variables v ON v.id = m.variable_id
                WHERE m.sensor_id = ?";
        $params = [$sensorId];
        if ($variableId !== null) {
            $sql .= " AND m.variable_id = ?";
            $params[] = $variableId;
        }
        $sql .= " ORDER BY m.measured_at ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
