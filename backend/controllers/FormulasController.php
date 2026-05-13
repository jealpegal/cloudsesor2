<?php
/**
 * Controlador de fórmulas: listar por sensor, crear, actualizar, eliminar.
 */

require_once __DIR__ . '/../models/Formula.php';
require_once __DIR__ . '/../models/Sensor.php';
require_once __DIR__ . '/../models/SensorVariable.php';

class FormulasController
{
    private FormulaModel $formulaModel;
    private SensorModel $sensorModel;
    private SensorVariableModel $variableModel;

    public function __construct(PDO $pdo)
    {
        $this->formulaModel = new FormulaModel($pdo);
        $this->sensorModel = new SensorModel($pdo);
        $this->variableModel = new SensorVariableModel($pdo);
    }

    /** GET /api/sensors/:sensorId/formulas */
    public function index(int $sensorId): void
    {
        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $formulas = $this->formulaModel->getBySensorId($sensorId);
        $this->json($formulas);
    }

    /** POST /api/formulas -> crear */
    public function store(): void
    {
        $input = $this->getJsonInput();
        $sensorId = isset($input['sensor_id']) ? (int) $input['sensor_id'] : 0;
        $name = trim($input['name'] ?? '');
        $expression = trim($input['expression'] ?? '');
        $resultVariableId = isset($input['result_variable_id']) ? (int) $input['result_variable_id'] : 0;
        $parameters = isset($input['parameters']) && is_array($input['parameters']) ? $input['parameters'] : [];

        if ($sensorId < 1 || $name === '' || $expression === '' || $resultVariableId < 1) {
            $this->json(['error' => 'Faltan: sensor_id, name, expression, result_variable_id'], 400);
            return;
        }

        if (!$this->sensorModel->getById($sensorId)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }

        $resultVar = $this->variableModel->getById($resultVariableId);
        if (!$resultVar || (int) $resultVar['sensor_id'] !== $sensorId) {
            $this->json(['error' => 'result_variable_id no pertenece al sensor'], 400);
            return;
        }

        $id = $this->formulaModel->create($sensorId, $name, $expression, $resultVariableId, $parameters);
        $formula = $this->formulaModel->getById($id);
        $this->json($formula, 201);
    }

    /** PUT /api/formulas/:id */
    public function update(int $id): void
    {
        $formula = $this->formulaModel->getById($id);
        if (!$formula) {
            $this->json(['error' => 'Fórmula no encontrada'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? $formula['name']);
        $expression = trim($input['expression'] ?? $formula['expression']);
        $parameters = isset($input['parameters']) && is_array($input['parameters']) ? $input['parameters'] : $formula['parameters'];

        $this->formulaModel->update($id, $name, $expression, $parameters);
        $this->json($this->formulaModel->getById($id));
    }

    /** DELETE /api/formulas/:id */
    public function delete(int $id): void
    {
        if (!$this->formulaModel->getById($id)) {
            $this->json(['error' => 'Fórmula no encontrada'], 404);
            return;
        }
        $this->formulaModel->delete($id);
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
