<?php
/**
 * Controlador de variables de sensor: listar, crear, actualizar, eliminar.
 */

require_once __DIR__ . '/../models/SensorVariable.php';
require_once __DIR__ . '/../models/Sensor.php';

class SensorVariablesController
{
    private SensorVariableModel $variableModel;
    private SensorModel $sensorModel;

    public function __construct(PDO $pdo)
    {
        $this->variableModel = new SensorVariableModel($pdo);
        $this->sensorModel = new SensorModel($pdo);
    }

    /** GET /api/sensors/:sensorId/variables */
    public function index(int $sensorId): void
    {
        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $variables = $this->variableModel->getBySensorId($sensorId);
        $this->json($variables);
    }

    /** POST /api/sensors/:sensorId/variables */
    public function store(int $sensorId): void
    {
        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? '');
        $type = strtolower(trim($input['type'] ?? 'measure'));
        $unit = trim($input['unit'] ?? '');
        if ($type !== 'calculated') $type = 'measure';

        if ($name === '') {
            $this->json(['error' => 'El nombre de la variable es obligatorio'], 400);
            return;
        }

        if ($this->variableModel->getByNameAndSensor($sensorId, $name)) {
            $this->json(['error' => 'Ya existe una variable con ese nombre en este sensor'], 400);
            return;
        }

        $id = $this->variableModel->create($sensorId, $name, $type, $unit ?: null);
        $this->json($this->variableModel->getById($id), 201);
    }

    /** PUT /api/sensors/:sensorId/variables/:id */
    public function update(int $sensorId, int $id): void
    {
        $variable = $this->variableModel->getById($id);
        if (!$variable || (int) $variable['sensor_id'] !== $sensorId) {
            $this->json(['error' => 'Variable no encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? $variable['name']);
        $unit = trim($input['unit'] ?? $variable['unit'] ?? '');
        if ($name === '') {
            $this->json(['error' => 'El nombre es obligatorio'], 400);
            return;
        }
        $this->variableModel->update($id, $name, $unit ?: null);
        $this->json($this->variableModel->getById($id));
    }

    /** DELETE /api/sensors/:sensorId/variables/:id */
    public function delete(int $sensorId, int $id): void
    {
        $variable = $this->variableModel->getById($id);
        if (!$variable || (int) $variable['sensor_id'] !== $sensorId) {
            $this->json(['error' => 'Variable no encontrada'], 404);
            return;
        }
        $this->variableModel->delete($id);
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
