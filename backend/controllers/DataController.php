<?php
/**
 * Controlador de recepción de datos (POST /api/data).
 *
 * Flujo modular para tesis:
 * 1. Validar entrada (key o sensor_id válido, values numéricos)
 * 2. Guardar mediciones de variables medidas
 * 3. Calcular variables derivadas con fórmulas y guardarlas en measurements
 * 4. Evaluar reglas de alerta y registrar alertas si corresponde
 *
 * No usa eval(); el evaluador de fórmulas es seguro (tokenizer + RPN).
 */

require_once __DIR__ . '/../models/Sensor.php';
require_once __DIR__ . '/../models/SensorVariable.php';
require_once __DIR__ . '/../models/Formula.php';
require_once __DIR__ . '/../models/Measurement.php';
require_once __DIR__ . '/../models/AlertRule.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../utils/FormulaEvaluator.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

class DataController
{
    private PDO $pdo;
    private SensorModel $sensorModel;
    private SensorVariableModel $variableModel;
    private FormulaModel $formulaModel;
    private MeasurementModel $measurementModel;
    private AlertRuleModel $alertRuleModel;
    private AlertModel $alertModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sensorModel = new SensorModel($pdo);
        $this->variableModel = new SensorVariableModel($pdo);
        $this->formulaModel = new FormulaModel($pdo);
        $this->measurementModel = new MeasurementModel($pdo);
        $this->alertRuleModel = new AlertRuleModel($pdo);
        $this->alertModel = new AlertModel($pdo);
    }

    /**
     * GET /api/data/ingest
     * Query: ?key=API_KEY&nombre_variable1=valor1&nombre_variable2=valor2&measured_at=opcional
     * - key: llave del sensor (api_key). Identifica de qué sensor son los datos.
     * - Cualquier otro parámetro (salvo measured_at) = nombre de variable => valor numérico (se guarda en esa variable).
     */
    public function storeFromGet(): void
    {
        $key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
        if ($key === '') {
            JsonResponse::error('Falta el parámetro obligatorio: key (llave del sensor)', 400, ['field' => 'key']);
            return;
        }

        $sensor = $this->sensorModel->getByApiKey($key);
        if (!$sensor) {
            JsonResponse::error('Sensor no encontrado con esa llave', 404, ['key' => $key]);
            return;
        }

        $sensorId = (int) $sensor['id'];
        $measuredAt = isset($_GET['measured_at']) && $this->isValidDatetime(trim((string) $_GET['measured_at']))
            ? trim((string) $_GET['measured_at'])
            : date('Y-m-d H:i:s');

        $values = [];
        $reserved = ['key', 'measured_at'];
        foreach ($_GET as $param => $value) {
            if (in_array($param, $reserved, true)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '' && is_numeric($value)) {
                $values[$param] = $value;
            }
        }

        if (empty($values)) {
            JsonResponse::error('No se enviaron variables numéricas (cada variable es un parámetro GET con nombre de variable y valor numérico)', 400, ['example' => '?key=XXX&nivel=1.5&temperatura=25']);
            return;
        }

        $context = [];
        $savedMeasured = $this->saveMeasurements($sensorId, $values, $measuredAt, $context);
        $savedCalculated = $this->computeAndSaveDerivedVariables($sensorId, $measuredAt, $context);
        $alertsTriggered = $this->evaluateAlertRules($sensorId, $context);

        JsonResponse::success([
            'success' => true,
            'sensor_id' => $sensorId,
            'measured_at' => $measuredAt,
            'saved_measured' => $savedMeasured,
            'saved_calculated' => $savedCalculated,
            'alerts_triggered' => $alertsTriggered,
        ], 201);
    }

    /**
     * POST /api/data
     * Body: { "key": "api_key", "values": { ... } } o { "sensor_id": int, "values": { ... }, "measured_at": "opcional" }
     */
    public function store(): void
    {
        $input = $this->getJsonInput();
        if (isset($input['__json_error'])) {
            JsonResponse::error('El cuerpo de la petición debe ser JSON válido', 400, ['detail' => $input['__json_error']]);
            return;
        }

        // --- Validaciones ---
        $validation = $this->validateInput($input); // resuelve sensor_id desde key si aplica
        if ($validation !== null) {
            JsonResponse::error($validation['message'], $validation['status'], $validation['extra'] ?? []);
            return;
        }

        $sensorId = (int) $input['sensor_id'];
        $values = $input['values'];
        $measuredAt = !empty($input['measured_at']) && $this->isValidDatetime($input['measured_at'])
            ? $input['measured_at']
            : date('Y-m-d H:i:s');

        $context = []; // nombre_variable => valor (para fórmulas y alertas)

        // 1) Guardar mediciones de variables medidas (y construir contexto)
        $savedMeasured = $this->saveMeasurements($sensorId, $values, $measuredAt, $context);

        // 2) Calcular variables derivadas y guardarlas también en measurements
        $savedCalculated = $this->computeAndSaveDerivedVariables($sensorId, $measuredAt, $context);

        // 3) Evaluar reglas de alerta y registrar alertas si se cumple la condición
        $alertsTriggered = $this->evaluateAlertRules($sensorId, $context);

        JsonResponse::success([
            'success' => true,
            'sensor_id' => $sensorId,
            'measured_at' => $measuredAt,
            'saved_measured' => $savedMeasured,
            'saved_calculated' => $savedCalculated,
            'alerts_triggered' => $alertsTriggered,
        ], 201);
    }

    /**
     * Valida el cuerpo de la petición. Devuelve null si es válido o un array con message, status y opcional extra.
     */
    private function validateInput(array &$input): ?array
    {
        $hasKey = isset($input['key']) && trim((string) $input['key']) !== '';
        $hasSensorId = isset($input['sensor_id']);

        if (!$hasKey && !$hasSensorId) {
            return [
                'message' => 'Falta el campo obligatorio: key (llave del sensor) o sensor_id',
                'status' => 400,
                'extra' => ['fields' => ['key', 'sensor_id']],
            ];
        }

        if ($hasKey) {
            $key = trim((string) $input['key']);
            $sensor = $this->sensorModel->getByApiKey($key);
            if (!$sensor) {
                return [
                    'message' => 'Sensor no encontrado con esa llave',
                    'status' => 404,
                    'extra' => ['key' => $key],
                ];
            }
            $input['sensor_id'] = (int) $sensor['id'];
        } else {
            $sensorId = filter_var($input['sensor_id'], FILTER_VALIDATE_INT);
            if ($sensorId === false || $sensorId < 1) {
                return [
                    'message' => 'sensor_id debe ser un entero positivo',
                    'status' => 400,
                    'extra' => ['field' => 'sensor_id', 'received' => $input['sensor_id']],
                ];
            }

            $sensor = $this->sensorModel->getById($sensorId);
            if (!$sensor) {
                return [
                    'message' => 'Sensor no encontrado',
                    'status' => 404,
                    'extra' => ['sensor_id' => $sensorId],
                ];
            }
            $input['sensor_id'] = (int) $sensorId;
        }

        if (!isset($input['values']) || !is_array($input['values'])) {
            return [
                'message' => 'Falta el campo obligatorio: values (objeto con nombres de variable y valores numéricos)',
                'status' => 400,
                'extra' => ['field' => 'values'],
            ];
        }

        $invalid = [];
        foreach ($input['values'] as $name => $value) {
            if ($value !== '' && $value !== null && !is_numeric($value)) {
                $invalid[] = ['name' => $name, 'value' => $value];
            }
        }
        if (!empty($invalid)) {
            return [
                'message' => 'Todos los valores en "values" deben ser numéricos',
                'status' => 400,
                'extra' => ['invalid_entries' => $invalid],
            ];
        }

        return null;
    }

    private function isValidDatetime(string $s): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $s);
        return $dt !== false;
    }

    /**
     * Guarda en measurements cada valor que corresponda a una variable de tipo "measure" del sensor.
     * Rellena $context con nombre => valor para uso en fórmulas y alertas.
     */
    private function saveMeasurements(int $sensorId, array $values, string $measuredAt, array &$context): array
    {
        $saved = [];
        foreach ($values as $varName => $value) {
            if ($value === '' || $value === null || !is_numeric($value)) {
                continue;
            }
            $var = $this->variableModel->getByNameAndSensor($sensorId, (string) $varName);
            if ($var) {
                $this->measurementModel->insert($sensorId, (int) $var['id'], (float) $value, $measuredAt);
                $context[$varName] = (float) $value;
                $saved[] = $varName;
            }
        }
        return $saved;
    }

    /**
     * Evalúa cada fórmula del sensor, obtiene el valor de la variable calculada y lo guarda en measurements.
     * Las variables calculadas se añaden a $context para fórmulas encadenadas y para alertas.
     */
    private function computeAndSaveDerivedVariables(int $sensorId, string $measuredAt, array &$context): array
    {
        $saved = [];
        $formulas = $this->formulaModel->getActiveBySensorId($sensorId);

        foreach ($formulas as $formula) {
            try {
                $params = $formula['parameters'];
                $fullContext = array_merge($context, $params);
                $result = FormulaEvaluator::evaluate($formula['expression'], $fullContext);
            } catch (InvalidArgumentException $e) {
                error_log("Formula id={$formula['id']}: " . $e->getMessage());
                continue;
            }

            $resultVariableId = (int) $formula['result_variable_id'];
            $this->measurementModel->insert($sensorId, $resultVariableId, (float) $result, $measuredAt);

            $resultVar = $this->variableModel->getById($resultVariableId);
            if ($resultVar) {
                $context[$resultVar['name']] = (float) $result;
                $saved[] = $resultVar['name'];
            }
        }
        return $saved;
    }

    /**
     * Evalúa las reglas de alerta activas del sensor con el contexto actual y registra alertas si se cumple la condición.
     */
    private function evaluateAlertRules(int $sensorId, array $context): int
    {
        $rules = $this->alertRuleModel->getActiveBySensorId($sensorId);
        $count = 0;

        foreach ($rules as $rule) {
            $var = $this->variableModel->getById((int) $rule['variable_id']);
            if (!$var || !array_key_exists($var['name'], $context)) {
                continue;
            }

            $value = (float) $context[$var['name']];
            $threshold = (float) $rule['threshold_value'];
            $op = $rule['operator'];
            $triggered = false;
            switch ($op) {
                case '>':
                    $triggered = $value > $threshold;
                    break;
                case '<':
                    $triggered = $value < $threshold;
                    break;
                case '>=':
                    $triggered = $value >= $threshold;
                    break;
                case '<=':
                    $triggered = $value <= $threshold;
                    break;
                case '=':
                    $triggered = abs($value - $threshold) < 1e-9;
                    break;
            }

            if ($triggered) {
                $this->alertModel->create(
                    (int) $rule['id'],
                    $sensorId,
                    (int) $rule['variable_id'],
                    $value,
                    $threshold,
                    $op,
                    $rule['description'] ?? "Alerta: {$var['name']} {$op} {$threshold}"
                );
                $count++;
            }
        }
        return $count;
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['__json_error' => json_last_error_msg()];
        }
        return is_array($decoded) ? $decoded : [];
    }
}
