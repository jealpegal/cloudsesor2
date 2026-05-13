<?php
/**
 * Controlador de alertas: listar alertas disparadas, marcar como leída.
 */

require_once __DIR__ . '/../models/Alert.php';

class AlertsController
{
    private AlertModel $alertModel;

    public function __construct(PDO $pdo)
    {
        $this->alertModel = new AlertModel($pdo);
    }

    /** GET /api/alerts ?sensor_id= &unread_only=1 &limit=100 */
    public function index(): void
    {
        $sensorId = isset($_GET['sensor_id']) ? (int) $_GET['sensor_id'] : null;
        $unreadOnly = !empty($_GET['unread_only']);
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
        $limit = max(1, min(500, $limit));

        $alerts = $this->alertModel->getList($sensorId, $unreadOnly, $limit);
        $this->json($alerts);
    }

    /** POST /api/alerts/:id/read -> marcar como leída */
    public function markRead(int $id): void
    {
        $this->alertModel->markAsRead($id);
        $this->json(['success' => true]);
    }

    private function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
