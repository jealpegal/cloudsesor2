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

### IP pública (ESP8266 / red exterior)

Con **HTTP** (recomendado en NodeMCU) la URL es la misma ruta cambiando host y puerto por los que expongas en el router o en un VPS:

```
http://TU_IP_PUBLICA:8000/api/data/ingest?key=TU_API_KEY&nivel=1.5&temperatura=25.2
```

1. En el equipo donde corre PHP: **`php -S 0.0.0.0:8000 router.php`** (desde la carpeta `backend`), no solo `localhost`, para aceptar conexiones desde fuera de la máquina.
2. En el router: **reenvío de puerto** (NAT) del puerto elegido (ej. 8000) hacia la **IP local** de ese PC.
3. Firewall del SO: permitir TCP en ese puerto.
4. El sketch Arduino usa `SERVER_BASE = "http://TU_IP_PUBLICA:8000"` (sin `/api`; el código añade `/api/data/ingest`).
5. **Frontend:** en `frontend/.env` define `VITE_API_URL=http://TU_IP_PUBLICA:8000/api` para que el dashboard llame a la misma API expuesta.
6. **CORS:** el backend admite orígenes `http(s)://IPv4:puerto` (p. ej. `http://TU_IP_PUBLICA:5173`). Orígenes concretos extra: variable de entorno `CORS_ALLOWED_ORIGINS` (lista separada por comas).

**HTTPS** con dominio y certificado en la IP pública puede funcionar desde el navegador; en **ESP8266** el TLS moderno suele dar problemas: para la placa mantén **HTTP** hacia la IP pública o un puerto dedicado sin TLS.

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
