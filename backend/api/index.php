<?php
/**
 * Punto de entrada de la API REST.
 * Enrutado por método y path: GET/POST/PUT/DELETE y /api/...
 *
 * Uso con PHP built-in server:
 *   php -S localhost:8000 -t backend
 * Las peticiones a /api/* se redirigen a api/index.php (ver router abajo).
 *
 * Con Apache: usar RewriteRule para enviar /api/* a este script.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

// Cargar modelos y controladores
require_once __DIR__ . '/../controllers/SensorsController.php';
require_once __DIR__ . '/../controllers/SensorVariablesController.php';
require_once __DIR__ . '/../controllers/DataController.php';
require_once __DIR__ . '/../controllers/FormulasController.php';
require_once __DIR__ . '/../controllers/AlertRulesController.php';
require_once __DIR__ . '/../controllers/AlertsController.php';
require_once __DIR__ . '/../controllers/MeasurementsController.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Normalizar path: quitar barra inicial y trailing
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

try {
    // GET  /api/sensors          -> SensorsController::index
    // GET  /api/sensors/:id      -> SensorsController::show
    // POST /api/sensors          -> SensorsController::store
    // PUT  /api/sensors/:id      -> SensorsController::update
    // DELETE /api/sensors/:id    -> SensorsController::delete

    // GET  /api/sensors/:id/variables     -> SensorVariablesController::index
    // POST /api/sensors/:id/variables     -> SensorVariablesController::store
    // PUT  /api/sensors/:id/variables/:vid -> SensorVariablesController::update
    // DELETE /api/sensors/:id/variables/:vid -> SensorVariablesController::delete

    // GET  /api/sensors/:id/formulas      -> FormulasController::index
    // POST /api/formulas                  -> FormulasController::store
    // PUT  /api/formulas/:id              -> FormulasController::update
    // DELETE /api/formulas/:id            -> FormulasController::delete

    // GET  /api/sensors/:id/alert-rules   -> AlertRulesController::index
    // POST /api/alert-rules               -> AlertRulesController::store
    // PUT  /api/alert-rules/:id           -> AlertRulesController::update
    // DELETE /api/alert-rules/:id         -> AlertRulesController::delete

    // GET  /api/alerts                    -> AlertsController::index
    // POST /api/alerts/:id/read           -> AlertsController::markRead

    // POST /api/data                      -> DataController::store

    $sensorsCtrl = new SensorsController($pdo);
    $variablesCtrl = new SensorVariablesController($pdo);
    $dataCtrl = new DataController($pdo);
    $formulasCtrl = new FormulasController($pdo);
    $alertRulesCtrl = new AlertRulesController($pdo);
    $alertsCtrl = new AlertsController($pdo);
    $measurementsCtrl = new MeasurementsController($pdo);

    // GET  /api/data/ingest  (recibir datos por GET: ?key=API_KEY&var1=val1&var2=val2)
    if ($method === 'GET' && $segments[0] === 'data' && isset($segments[1]) && $segments[1] === 'ingest' && count($segments) === 2) {
        $dataCtrl->storeFromGet();
        return;
    }

    // POST /api/data
    if ($method === 'POST' && $segments[0] === 'data' && count($segments) === 1) {
        $dataCtrl->store();
        return;
    }

    // GET/POST /api/sensors
    if ($segments[0] === 'sensors') {
        $id = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;

        if ($id === null && count($segments) === 1) {
            if ($method === 'GET') { $sensorsCtrl->index(); return; }
            if ($method === 'POST') { $sensorsCtrl->store(); return; }
        }

        if ($id !== null && count($segments) === 2) {
            if ($method === 'GET') { $sensorsCtrl->show($id); return; }
            if ($method === 'PUT') { $sensorsCtrl->update($id); return; }
            if ($method === 'DELETE') { $sensorsCtrl->delete($id); return; }
        }

        // /api/sensors/:id/variables
        if ($id !== null && isset($segments[2]) && $segments[2] === 'variables') {
            $vid = isset($segments[3]) && ctype_digit($segments[3]) ? (int) $segments[3] : null;
            if ($vid === null && count($segments) === 3) {
                if ($method === 'GET') { $variablesCtrl->index($id); return; }
                if ($method === 'POST') { $variablesCtrl->store($id); return; }
            }
            if ($vid !== null && count($segments) === 4) {
                if ($method === 'PUT') { $variablesCtrl->update($id, $vid); return; }
                if ($method === 'DELETE') { $variablesCtrl->delete($id, $vid); return; }
            }
        }

        // /api/sensors/:id/measurements
        if ($id !== null && isset($segments[2]) && $segments[2] === 'measurements' && count($segments) === 3 && $method === 'GET') {
            $measurementsCtrl->index($id);
            return;
        }

        // /api/sensors/:id/formulas
        if ($id !== null && isset($segments[2]) && $segments[2] === 'formulas' && count($segments) === 3 && $method === 'GET') {
            $formulasCtrl->index($id);
            return;
        }

        // /api/sensors/:id/alert-rules
        if ($id !== null && isset($segments[2]) && $segments[2] === 'alert-rules' && count($segments) === 3 && $method === 'GET') {
            $alertRulesCtrl->index($id);
            return;
        }
    }

    // POST /api/formulas  PUT/DELETE /api/formulas/:id
    if ($segments[0] === 'formulas') {
        $fid = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;
        if ($fid === null && count($segments) === 1 && $method === 'POST') {
            $formulasCtrl->store();
            return;
        }
        if ($fid !== null && count($segments) === 2) {
            if ($method === 'PUT') { $formulasCtrl->update($fid); return; }
            if ($method === 'DELETE') { $formulasCtrl->delete($fid); return; }
        }
    }

    // POST /api/alert-rules  PUT/DELETE /api/alert-rules/:id
    if ($segments[0] === 'alert-rules') {
        $rid = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;
        if ($rid === null && count($segments) === 1 && $method === 'POST') {
            $alertRulesCtrl->store();
            return;
        }
        if ($rid !== null && count($segments) === 2) {
            if ($method === 'PUT') { $alertRulesCtrl->update($rid); return; }
            if ($method === 'DELETE') { $alertRulesCtrl->delete($rid); return; }
        }
    }

    // GET /api/alerts   POST /api/alerts/:id/read
    if ($segments[0] === 'alerts') {
        if (count($segments) === 1 && $method === 'GET') {
            $alertsCtrl->index();
            return;
        }
        $aid = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;
        if ($aid !== null && isset($segments[2]) && $segments[2] === 'read' && count($segments) === 3 && $method === 'POST') {
            $alertsCtrl->markRead($aid);
            return;
        }
    }

    // No coincide ninguna ruta
    JsonResponse::error('Not Found', 404, ['path' => $path]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    JsonResponse::error('Error interno del servidor', 500, ['message' => $e->getMessage()]);
}
