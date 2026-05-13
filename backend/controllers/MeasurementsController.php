<?php
/**
 * Controlador de mediciones: listar últimas por sensor.
 */

require_once __DIR__ . '/../models/Measurement.php';
require_once __DIR__ . '/../models/Sensor.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

class MeasurementsController
{
    private MeasurementModel $measurementModel;
    private SensorModel $sensorModel;

    public function __construct(PDO $pdo)
    {
        $this->measurementModel = new MeasurementModel($pdo);
        $this->sensorModel = new SensorModel($pdo);
    }

    /** GET /api/sensors/:id/measurements ?limit=20&variable_id=2&chart=1 (chart=1 → orden por tiempo ASC, para gráficas) */
    public function index(int $sensorId): void
    {
        if (!$this->sensorModel->getById($sensorId)) {
            JsonResponse::error('Sensor no encontrado', 404, ['sensor_id' => $sensorId]);
            return;
        }
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        $limit = max(1, min(500, $limit));
        $forChart = !empty($_GET['chart']);
        $variableId = isset($_GET['variable_id']) ? (int) $_GET['variable_id'] : null;
        if ($variableId <= 0) {
            $variableId = null;
        }

        if ($forChart || $variableId !== null) {
            $measurements = $this->measurementModel->getForChart($sensorId, $variableId, $limit);
        } else {
            $measurements = $this->measurementModel->getLatestBySensor($sensorId, $limit);
        }
        JsonResponse::success($measurements);
    }
}
