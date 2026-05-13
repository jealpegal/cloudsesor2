<?php
/**
 * Modelo AlertRule: reglas de alerta (variable operador valor_umbral).
 */

class AlertRuleModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Obtener regla por ID */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.sensor_id, r.variable_id, r.operator, r.threshold_value, r.description, r.is_active, r.created_at,
                    v.name AS variable_name
             FROM alert_rules r
             JOIN sensor_variables v ON v.id = r.variable_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Listar reglas de un sensor */
    public function getBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.sensor_id, r.variable_id, r.operator, r.threshold_value, r.description, r.is_active, r.created_at,
                    v.name AS variable_name
             FROM alert_rules r
             JOIN sensor_variables v ON v.id = r.variable_id
             WHERE r.sensor_id = ? ORDER BY r.id"
        );
        $stmt->execute([$sensorId]);
        return $stmt->fetchAll();
    }

    /** Listar todas las reglas activas (para evaluar al recibir datos) */
    public function getActiveBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, variable_id, operator, threshold_value, description
             FROM alert_rules WHERE sensor_id = ? AND is_active = 1"
        );
        $stmt->execute([$sensorId]);
        return $stmt->fetchAll();
    }

    /** Crear regla */
    public function create(int $sensorId, int $variableId, string $operator, float $thresholdValue, ?string $description = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO alert_rules (sensor_id, variable_id, operator, threshold_value, description) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$sensorId, $variableId, $operator, $thresholdValue, $description ?? '']);
        return (int) $this->pdo->lastInsertId();
    }

    /** Actualizar regla */
    public function update(int $id, string $operator, float $thresholdValue, ?string $description = null, ?bool $isActive = null): bool
    {
        $updates = ["operator = ?", "threshold_value = ?", "description = ?"];
        $params = [$operator, $thresholdValue, $description ?? ''];
        if ($isActive !== null) {
            $updates[] = "is_active = ?";
            $params[] = $isActive ? 1 : 0;
        }
        $params[] = $id;
        $sql = "UPDATE alert_rules SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /** Eliminar regla */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM alert_rules WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
