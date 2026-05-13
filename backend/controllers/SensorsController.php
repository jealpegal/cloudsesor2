<?php
/**
 * Controlador de sensores: GET list, GET one, POST create, PUT update, DELETE.
 */

require_once __DIR__ . '/../models/Sensor.php';
require_once __DIR__ . '/../models/SensorVariable.php';

class SensorsController
{
    private SensorModel $sensorModel;
    private SensorVariableModel $variableModel;

    public function __construct(PDO $pdo)
    {
        $this->sensorModel = new SensorModel($pdo);
        $this->variableModel = new SensorVariableModel($pdo);
    }

    /** GET /api/sensors -> listar todos (opcional ?with_variables=1) */
    public function index(): void
    {
        $withVariables = !empty($_GET['with_variables']);
        $sensors = $this->sensorModel->getAll($withVariables);
        $this->json($sensors);
    }

    /** GET /api/sensors/:id */
    public function show(int $id): void
    {
        $sensor = $this->sensorModel->getById($id);
        if (!$sensor) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $sensor['variables'] = $this->variableModel->getBySensorId($id);
        $this->json($sensor);
    }

    /** POST /api/sensors -> crear */
    public function store(): void
    {
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            $this->json(['error' => 'El nombre es obligatorio'], 400);
            return;
        }
        $description = trim($input['description'] ?? '');
        $apiKey = isset($input['api_key']) && $input['api_key'] !== '' ? trim((string) $input['api_key']) : null;
        $id = $this->sensorModel->create($name, $description, $apiKey);
        $sensor = $this->sensorModel->getById($id);
        $this->json($sensor, 201);
    }

    /** PUT /api/sensors/:id */
    public function update(int $id): void
    {
        $sensor = $this->sensorModel->getById($id);
        if (!$sensor) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $input = $this->getJsonInput();
        $name = trim($input['name'] ?? $sensor['name']);
        $description = trim($input['description'] ?? $sensor['description'] ?? '');
        $apiKey = array_key_exists('api_key', $input) ? (trim((string) $input['api_key']) ?: null) : $sensor['api_key'] ?? null;
        if ($name === '') {
            $this->json(['error' => 'El nombre es obligatorio'], 400);
            return;
        }
        $this->sensorModel->update($id, $name, $description, $apiKey);
        $this->json($this->sensorModel->getById($id));
    }

    /** DELETE /api/sensors/:id */
    public function delete(int $id): void
    {
        if (!$this->sensorModel->getById($id)) {
            $this->json(['error' => 'Sensor no encontrado'], 404);
            return;
        }
        $this->sensorModel->delete($id);
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
