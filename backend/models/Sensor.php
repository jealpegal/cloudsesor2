<?php
/**
 * Modelo Sensor: CRUD y listado de sensores.
 */

class SensorModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Listar todos los sensores (opcionalmente con variables) */
    public function getAll(bool $withVariables = false): array
    {
        $sql = "SELECT id, name, description, api_key, created_at, updated_at FROM sensors ORDER BY name";
        $stmt = $this->pdo->query($sql);
        $sensors = $stmt->fetchAll();

        if ($withVariables) {
            foreach ($sensors as &$s) {
                $s['variables'] = $this->getVariablesBySensorId((int) $s['id']);
            }
        }

        return $sensors;
    }

    /** Obtener un sensor por ID */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, description, api_key, created_at, updated_at FROM sensors WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Obtener un sensor por api_key (para recepción de datos por GET) */
    public function getByApiKey(string $apiKey): ?array
    {
        if ($apiKey === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT id, name, description, api_key, created_at, updated_at FROM sensors WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Crear sensor (api_key opcional; si no se pasa, se puede generar después) */
    public function create(string $name, ?string $description = null, ?string $apiKey = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO sensors (name, description, api_key) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description ?? '', $apiKey ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Actualizar sensor */
    public function update(int $id, string $name, ?string $description = null, ?string $apiKey = null): bool
    {
        $stmt = $this->pdo->prepare("UPDATE sensors SET name = ?, description = ?, api_key = ? WHERE id = ?");
        return $stmt->execute([$name, $description ?? '', $apiKey ?? null, $id]);
    }

    /** Eliminar sensor (CASCADE borra variables, fórmulas, mediciones, reglas, alertas) */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sensors WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Variables del sensor (delegado a SensorVariableModel si se inyecta; aquí consulta directa) */
    public function getVariablesBySensorId(int $sensorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, sensor_id, name, type, unit, created_at FROM sensor_variables WHERE sensor_id = ? ORDER BY name"
        );
        $stmt->execute([$sensorId]);
        return $stmt->fetchAll();
    }
}
