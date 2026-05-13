<?php
/**
 * Modelo Alert: alertas disparadas cuando se cumple una regla.
 */

class AlertModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Registrar una alerta disparada */
    public function create(int $alertRuleId, int $sensorId, int $variableId, float $value, float $thresholdValue, string $operator, ?string $message = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO alerts (alert_rule_id, sensor_id, variable_id, value, threshold_value, operator, message) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$alertRuleId, $sensorId, $variableId, $value, $thresholdValue, $operator, $message ?? '']);
        return (int) $this->pdo->lastInsertId();
    }

    /** Listar alertas (últimas primero, opcionalmente no leídas) */
    public function getList(?int $sensorId = null, ?bool $unreadOnly = false, int $limit = 100): array
    {
        $where = ["1=1"];
        $params = [];
        if ($sensorId !== null) {
            $where[] = "a.sensor_id = ?";
            $params[] = $sensorId;
        }
        if ($unreadOnly) {
            $where[] = "a.read_at IS NULL";
        }
        $params[] = $limit;
        $sql = "SELECT a.id, a.alert_rule_id, a.sensor_id, a.variable_id, a.value, a.threshold_value, a.operator, a.message, a.triggered_at, a.read_at,
                       s.name AS sensor_name, v.name AS variable_name
                FROM alerts a
                JOIN sensors s ON s.id = a.sensor_id
                JOIN sensor_variables v ON v.id = a.variable_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.triggered_at DESC
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Marcar alerta como leída */
    public function markAsRead(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE alerts SET read_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
