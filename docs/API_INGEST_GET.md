# Recepción de datos por GET (ingest)

Ruta para que los sensores envíen datos mediante una petición **GET**. La llave identifica el sensor y cada variable llega como parámetro de la URL.

---

## Ruta

```
GET /api/data/ingest
```

**Ejemplo base (backend en localhost:8000):**
```
http://localhost:8000/api/data/ingest
```

### Pinggy (recomendado: NodeMCU sin IP ni NAT)

El NodeMCU solo necesita WiFi e internet. En tu PC el PHP escucha en local y Pinggy crea una URL pública:

```
https://TU_SUBDOMINIO.run.pinggy-free.link/api/data/ingest?key=TU_API_KEY&nivel=1.5&temperatura=25.2
```

1. **`cd backend && php -S 0.0.0.0:8000 router.php`**
2. En otra terminal: **`pinggy http 8000`** (o el cliente Pinggy que uses).
3. Copia el host **HTTPS** en el sketch: `SERVER_BASE = "https://TU_SUBDOMINIO.run.pinggy-free.link"` (sin `/api`; el código añade `/api/data/ingest`).
4. El ESP8266 usa `WiFiClientSecure` + `setInsecure()` en el sketch (solo pruebas; no valida certificado).

**Frontend:** puedes usar la misma URL de Pinggy en `frontend/.env`: `VITE_API_URL=https://TU_SUBDOMINIO.run.pinggy-free.link/api`.

### Si el navegador no abre la URL (túnel caído)

En Pinggy **gratis**, al cerrar la terminal o apagar el PC la URL **deja de existir** (DNS `NXDOMAIN`). No es un typo en `bnedx-...`: hay que **volver a ejecutar** `pinggy http 8000`, copiar el **host nuevo** y actualizar el sketch / pruebas en el navegador.

Comprueba en el PC (con PHP y Pinggy activos):

```bash
curl -sS "https://TU_HOST_NUEVO.run.pinggy-free.link/api/data/ingest?key=abc123&temperatura=1"
```

Debe responder `201` y JSON con `saved_measured`. Si `curl` falla con "Could not resolve host", el túnel no está activo o la URL es vieja.

### Alternativa: IP pública + NAT

Si no usas Pinggy, puedes exponer el puerto 8000 con reenvío en el router y `SERVER_BASE = "http://TU_IP_PUBLICA:8000"` (HTTP, sin TLS en la placa). Ver comentarios históricos en el repositorio; para tesis suele ser más simple Pinggy.

---

## Parámetros (query string)

| Parámetro     | Obligatorio | Placeholder / Cómo rellenar | Descripción |
|---------------|-------------|-----------------------------|-------------|
| **key**       | Sí          | `key=TU_API_KEY`            | Llave del sensor (`api_key`). Usa la misma que configuraste al crear/editar el sensor (ej. `abc123`). |
| **measured_at** | No        | `measured_at=2025-03-18%2012:00:00` | Fecha/hora en formato `YYYY-MM-DD HH:MM:SS`. En la URL el espacio se escribe como `%20`. Si no se envía, se usa la hora del servidor. |
| **Variables** | Sí (al menos una) | `nivel=1.5&temperatura=25.2` | Cada variable = parámetro con **nombre** (igual al de la variable en el sensor, tipo `measure`) y **valor numérico**. Separa con `&`. |

**Resumen:** El **nombre del parámetro** debe coincidir con una variable del sensor (tipo `measure` en `sensor_variables`). El **valor** debe ser numérico. Solo se guardan variables que existan para ese sensor.

---

## Ejemplos de URL

Sensor con llave `abc123`, variables `nivel` y `temperatura`:

```
GET /api/data/ingest?key=abc123&nivel=1.5&temperatura=25.2
```

Con fecha/hora:

```
GET /api/data/ingest?key=abc123&nivel=1.5&temperatura=25.2&measured_at=2025-03-18%2012:00:00
```

Desde un dispositivo (ESP, Arduino, etc.) puedes hacer la petición GET a esa URL; cada variable que quieras guardar va como parámetro con su nombre y valor.

---

## Cómo configurar la llave del sensor

1. **Al crear el sensor (POST /api/sensors):**
   ```json
   { "name": "Tanque 1", "description": "...", "api_key": "abc123" }
   ```

2. **Al editar el sensor (PUT /api/sensors/:id):**
   ```json
   { "api_key": "abc123" }
   ```

La `api_key` debe ser única. Es la que usarás en `?key=...` al enviar datos por GET.

---

## Respuesta

- **201**: Datos guardados. Cuerpo incluye `sensor_id`, `measured_at`, `saved_measured` (variables guardadas), `saved_calculated`, `alerts_triggered`.
- **400**: Falta `key`, no hay variables numéricas o formato inválido.
- **404**: No existe un sensor con esa `key`.

---

## Dónde se guarda cada variable

Cada parámetro GET (excepto `key` y `measured_at`) se interpreta como:

- **Nombre** = nombre de una variable del sensor (tabla `sensor_variables`, tipo `measure`).
- **Valor** = número que se guarda en la tabla `measurements` para esa variable y ese sensor en el instante `measured_at`.

Si el sensor tiene variables calculadas (fórmulas), se calculan y guardan después de las medidas. Las reglas de alerta se evalúan con todos los valores (medidos + calculados).

---

## Cómo crear una fórmula (variable calculada)

Las fórmulas permiten obtener un valor a partir de las variables medidas (por ejemplo: `nivel * 0.5 + temperatura * 0.2`). Pasos:

### 1. Crear la variable donde se guardará el resultado

La variable debe ser de tipo **calculated**. Desde la API:

```http
POST /api/sensor_variables
Content-Type: application/json

{
  "sensor_id": 1,
  "name": "indice_calculado",
  "type": "calculated",
  "unit": "%"
}
```

Guarda el `id` de la variable creada; lo usarás como `result_variable_id` en la fórmula.

### 2. Crear la fórmula

```http
POST /api/formulas
Content-Type: application/json

{
  "sensor_id": 1,
  "name": "Índice combinado",
  "expression": "nivel * a1 + temperatura * a2 + b",
  "result_variable_id": 5,
  "parameters": { "a1": 0.5, "a2": 0.2, "b": 0 }
}
```

| Campo | Placeholder / Cómo rellenar |
|-------|-----------------------------|
| **sensor_id** | ID del sensor (número). |
| **name** | Nombre descriptivo de la fórmula (ej. "Índice combinado"). |
| **expression** | Expresión matemática usando **nombres de variables medidas** (ej. `nivel`, `temperatura`) y **parámetros** (ej. `a1`, `a2`, `b`). Operadores: `+`, `-`, `*`, `/`, paréntesis. |
| **result_variable_id** | ID de la variable de tipo `calculated` que creaste en el paso 1. |
| **parameters** | Objeto JSON con los coeficientes que aparecen en la expresión. Ejemplo: `{"a1": 0.5, "a2": 0.2, "b": 0}`. |

Al recibir datos por GET (o POST `/api/data`), primero se guardan las medidas y luego se evalúa la expresión sustituyendo las variables por sus valores y los parámetros por los de `parameters`; el resultado se guarda en la variable calculada.
