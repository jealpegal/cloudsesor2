<?php
/**
 * Controlador de reglas de alerta: listar por sensor, crear, actualizar, eliminar.
 */

require_once __DIR__ . '/../models/AlertRule.php';
require_once __DIR__ . '/../models/Sensor.php';

class AlertRulesController
{
    private AlertRuleModel $alertRuleModel;
    private SensorModel $sensorModel;

    public function __construct(PDO $pdo)
    {
        $this->alertRuleModel = new AlertRuleModel($pdo);
        $this->sensorModel = new SensorModel($pdo);
    }

    /** GET /api/sensors/:sensorId/alert-rules */
    public function index(int $sensorId): void
    {
        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $rules = $this->alertRuleModel->getBySensorId($sensorId);
        $this->json($rules);
    }

    /** POST /api/alert-rules -> crear */
    public function store(): void
    {
        $input = $this->getJsonInput();
        $sensorId = isset($input['sensor_id']) ? (int) $input['sensor_id'] : 0;
        $variableId = isset($input['variable_id']) ? (int) $input['variable_id'] : 0;
        $operator = trim($input['operator'] ?? '');
        $thresholdValue = isset($input['threshold_value']) ? (float) $input['threshold_value'] : 0.0;
        $description = trim($input['description'] ?? '');

        $allowedOps = ['>', '<', '>=', '<=', '='];
        if ($sensorId < 1 || $variableId < 1 || !in_array($operator, $allowedOps, true)) {
            $this->json(['error' => 'Faltan o inválidos: sensor_id, variable_id, operator (>, <, >=, <=, =), threshold_value'], 400);
            return;
        }

        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }

        $id = $this->alertRuleModel->create($sensorId, $variableId, $operator, $thresholdValue, $description ?: null);
        $rule = $this->alertRuleModel->getById($id);
        $this->json($rule ?? ['id' => $id, 'sensor_id' => $sensorId, 'variable_id' => $variableId, 'operator' => $operator, 'threshold_value' => $thresholdValue, 'description' => $description], 201);
    }

    /** PUT /api/alert-rules/:id */
    public function update(int $id): void
    {
        $rules = null; // no tenemos getById en AlertRuleModel, se puede añadir
        $input = $this->getJsonInput();
        $operator = trim($input['operator'] ?? '');
        $thresholdValue = isset($input['threshold_value']) ? (float) $input['threshold_value'] : null;
        $description = trim($input['description'] ?? '');
        $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : null;

        $allowedOps = ['>', '<', '>=', '<=', '='];
        if ($operator !== '' && !in_array($operator, $allowedOps, true)) {
            $this->json(['error' => 'operator debe ser uno de: >, <, >=, <=, ='], 400);
            return;
        }

        // Necesitamos al menos un valor; si no hay getById, usamos los que vengan
        if ($operator === '' && $thresholdValue === null && $description === '' && $isActive === null) {
            $this->json(['error' => 'Nada que actualizar'], 400);
            return;
        }
        $operator = $operator ?: '>';
        $thresholdValue = $thresholdValue !== null ? $thresholdValue : 0;
        $this->alertRuleModel->update($id, $operator, $thresholdValue, $description ?: null, $isActive);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** DELETE /api/alert-rules/:id */
    public function delete(int $id): void
    {
        $this->alertRuleModel->delete($id);
        $this->json(['success' => true], 204);
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($status !== 204) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
}
