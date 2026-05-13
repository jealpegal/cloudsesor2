# CloudSensor — Sistema de sensores (Tesis de Ingeniería)

Sistema full-stack para recepción de datos desde NodeMCU (ESP8266), almacenamiento, fórmulas calculadas y alertas.

## Arquitectura

- **Backend:** PHP (sin frameworks), PDO, MySQL
- **Frontend:** React + Vite
- **API:** REST (JSON)
- **Dispositivo:** NodeMCU/ESP8266 enviando datos por **GET** `/api/data/ingest` o **POST** `/api/data`

## Requisitos

- PHP 7.4+ (extensiones: pdo_mysql, json)
- MySQL 5.7+ o MariaDB
- Node.js 18+ (para el frontend)
- (Opcional) Arduino IDE con soporte ESP8266 para el NodeMCU

## Instalación

### 1. Base de datos

Crear la base de datos y el usuario, luego importar el schema:

```bash
mysql -u root -p -e "CREATE DATABASE cloudsensor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p cloudsensor < database/schema.sql
```

O desde MySQL:

```sql
CREATE DATABASE cloudsensor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloudsensor;
SOURCE /ruta/al/proyecto/database/schema.sql;
```

### 2. Backend PHP

Configurar la conexión a la base de datos. Por defecto el backend usa:

- Host: `localhost`
- Base de datos: `cloudsensor`
- Usuario: `root`
- Contraseña: vacía

Puedes definir variables de entorno o editar `backend/config/db.php`:

```php
$db_host = 'localhost';
$db_name = 'cloudsensor';
$db_user = 'root';
$db_pass = 'tu_password';
```

Iniciar el servidor PHP (desde la raíz del proyecto):

```bash
cd backend
php -S localhost:8000 router.php
```

La API quedará en `http://localhost:8000/api/`.

**Exponer por IP pública (NAT):** usa `php -S 0.0.0.0:8000 router.php` y configura el reenvío de puerto del router hacia ese PC. El sketch está en `backend/utils/arduino.ino` con `SERVER_BASE = "http://TU_IP_PUBLICA:8000"`. En el frontend, `VITE_API_URL=http://TU_IP_PUBLICA:8000/api` en `.env`. Detalle en `docs/API_INGEST_GET.md`.

### 3. Frontend React

```bash
cd frontend
npm install
npm run dev
```

El frontend se abre en `http://localhost:5173`.

**Configuración de la API:** Crea un archivo `frontend/.env` (puedes copiar `.env.example`). Si defines `VITE_API_URL`, el frontend usará esa URL para las peticiones; si no la defines, Vite hará de proxy y enviará `/api` a `http://localhost:8000`.

- Con backend PHP en `php -S localhost:8000`: no definas `VITE_API_URL` o usa `VITE_API_URL=http://localhost:8000/api`.
- Con API en Apache/nginx en la misma máquina: `VITE_API_URL=http://localhost/api`.

### 4. NodeMCU (opcional)

1. Instalar el soporte ESP8266 en Arduino IDE (Gestor de tarjetas).
2. Abrir **`backend/utils/arduino.ino`** (ingest por **GET** con `api_key`).
3. Configurar `ssid`, `password`, **`SERVER_BASE`** (`http://TU_IP_PUBLICA:puerto` o `http://IP_LAN:8000`), **`SENSOR_API_KEY`** y nombres de variables si difieren de `nivel` / `temperatura`.
4. Subir el sketch al NodeMCU.

Ejemplo alternativo con **POST** y JSON: `docs/nodemcu_example.ino` (requiere **ArduinoJson** v6).

En el backend, los nombres de las variables (ej: `nivel`, `temperatura`) deben coincidir con las **variables de tipo "Medida"** del sensor en la base de datos.

## Uso rápido

1. **Crear sensor:** Dashboard → Crear sensor → Nombre y descripción.
2. **Variables:** Entrar al sensor → Variables → Añadir variable (nombre igual al que envía el NodeMCU, tipo "Medida"). Para fórmulas, crear también variables tipo "Calculada".
3. **Fórmulas:** En Fórmulas del sensor, expresión como `nivel*a1 + temperatura*a2 + b` y parámetros JSON `{"a1": 1, "a2": 0.5, "b": 0}`. La variable resultado debe ser de tipo Calculada.
4. **Reglas de alerta:** En Alertas del sensor, definir condición (variable, operador, umbral).
5. **Enviar datos:** Desde NodeMCU (o con `curl`/Postman) POST a `http://TU_SERVIDOR:8000/api/data` con body:

```json
{
  "sensor_id": 1,
  "values": {
    "nivel": 10,
    "temperatura": 30
  }
}
```

6. **Ver alertas:** Menú Alertas. La lista se actualiza cada 5 segundos.

## Endpoints API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | /api/sensors | Listar sensores |
| GET | /api/sensors?with_variables=1 | Listar con variables |
| GET | /api/sensors/:id | Obtener sensor |
| POST | /api/sensors | Crear sensor |
| PUT | /api/sensors/:id | Actualizar sensor |
| DELETE | /api/sensors/:id | Eliminar sensor |
| GET | /api/sensors/:id/variables | Variables del sensor |
| POST | /api/sensors/:id/variables | Crear variable |
| PUT | /api/sensors/:id/variables/:vid | Actualizar variable |
| DELETE | /api/sensors/:id/variables/:vid | Eliminar variable |
| GET | /api/sensors/:id/formulas | Fórmulas del sensor |
| POST | /api/formulas | Crear fórmula |
| PUT | /api/formulas/:id | Actualizar fórmula |
| DELETE | /api/formulas/:id | Eliminar fórmula |
| GET | /api/sensors/:id/alert-rules | Reglas de alerta del sensor |
| POST | /api/alert-rules | Crear regla de alerta |
| PUT | /api/alert-rules/:id | Actualizar regla |
| DELETE | /api/alert-rules/:id | Eliminar regla |
| GET | /api/alerts | Listar alertas (?sensor_id= &unread_only=1) |
| POST | /api/alerts/:id/read | Marcar alerta como leída |
| POST | /api/data | Recibir datos (sensor_id, values) |

## Ejemplo con curl

```bash
# Crear sensor
curl -X POST http://localhost:8000/api/sensors \
  -H "Content-Type: application/json" \
  -d '{"name":"Tanque 1","description":"Sensor de nivel y temperatura"}'

# Enviar datos (como el NodeMCU)
curl -X POST http://localhost:8000/api/data \
  -H "Content-Type: application/json" \
  -d '{"sensor_id":1,"values":{"nivel":10,"temperatura":30}}'
```

## Estructura del proyecto

```
cloudsesor2/
├── backend/
│   ├── api/
│   │   └── index.php          # Enrutador y entrada API
│   ├── config/
│   │   ├── db.php             # Conexión PDO
│   │   └── cors.php           # Headers CORS
│   ├── controllers/
│   ├── models/
│   ├── utils/
│   │   └── FormulaEvaluator.php  # Evaluador seguro de fórmulas
│   └── router.php             # Router para php -S
├── database/
│   └── schema.sql            # Tablas MySQL
├── frontend/
│   ├── src/
│   │   ├── api/client.js     # Cliente API
│   │   ├── components/
│   │   ├── pages/
│   │   └── App.jsx
│   └── package.json
├── docs/
│   └── nodemcu_example.ino   # Ejemplo NodeMCU
└── README.md
```

## Notas para la tesis

- El evaluador de fórmulas (`FormulaEvaluator.php`) no usa `eval()`: tokeniza la expresión, la convierte a RPN y evalúa con un stack, aceptando solo números y operadores `+ - * /` y nombres de variables/parámetros.
- Las alertas se generan en el mismo flujo que el guardado de datos: al recibir POST en `/api/data` se guardan mediciones, se evalúan fórmulas y luego se comprueban las reglas de alerta.
- CORS está configurado en `backend/config/cors.php` para permitir peticiones desde el origen del frontend en desarrollo.
