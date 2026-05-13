<?php
/**
 * Respuestas JSON estandarizadas para la API.
 * Facilita respuestas consistentes (éxito/error) y documentación en tesis.
 */

class JsonResponse
{
    /**
     * Envía una respuesta JSON de éxito.
     *
     * @param mixed $data    Datos a devolver (array u objeto)
     * @param int   $status Código HTTP (por defecto 200, usar 201 para creación)
     */
    public static function success($data, int $status = 200): void
    {
        self::send($data, $status);
    }

    /**
     * Envía una respuesta JSON de error con mensaje claro.
     *
     * @param string $message Mensaje legible para el cliente
     * @param int    $status  Código HTTP (400, 404, 422, 500)
     * @param array  $extra   Campos adicionales (ej: 'field' => 'sensor_id')
     */
    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        $body = array_merge(
            ['error' => $message],
            $extra
        );
        self::send($body, $status);
    }

    /**
     * Envía el cuerpo JSON y termina la ejecución.
     */
    private static function send($data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($status !== 204) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
}
