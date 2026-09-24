# 2.13 ENFOQUE METODOLÓGICO DEL DESARROLLO WEB

El desarrollo de la plataforma web se abordó bajo una arquitectura de tres capas lógicamente desacopladas: una capa de presentación implementada como aplicación de página única (SPA) en React 18 con React Router 6 y empaquetada con Vite 5; una capa de lógica de negocio construida como interfaz de programación de aplicaciones (API) de estilo REST en PHP 8 bajo el patrón Modelo-Vista-Controlador; y una capa de persistencia sobre MySQL con motor InnoDB. La comunicación entre capas se realiza exclusivamente mediante el protocolo HTTP sobre representaciones JSON, sin estado de sesión compartido, lo que garantiza la independencia tecnológica de cada componente y permite sustituir cualquiera de ellos sin afectar a los demás.

El principio rector que gobierna la totalidad de las decisiones de diseño descritas en esta sección es el de **configuración sobre codificación**: la plataforma no fue concebida como una aplicación destinada a un conjunto predeterminado de magnitudes físicas, sino como un sistema genérico de adquisición, transformación y visualización de series temporales, en el cual las entidades observadas, las magnitudes que las describen, las ecuaciones que las relacionan y las condiciones de alerta que las supervisan **se declaran como datos en tiempo de ejecución y no como estructuras codificadas en el programa**. En la instancia particular de la presente investigación el sistema se configuró con las variables `nivel` y `temperatura`, pero dicha configuración constituye un estado de la base de datos y no una característica del software: la incorporación de nuevas magnitudes —presión, conductividad, caudal o cualquier otra— se realiza íntegramente desde la interfaz web, sin modificar una sola línea de código fuente, sin alterar la estructura de las tablas y sin interrumpir el servicio. Las cuatro subsecciones siguientes formalizan los contratos, algoritmos y parámetros temporales que materializan dicho principio.

## 2.13.1 Contrato de la API y esquema JSON

### 2.13.1.1 Estilo arquitectónico y mecanismo de enrutamiento

La capa de servicios se diseñó siguiendo el estilo arquitectónico REST, en el cual cada concepto del dominio se expone como un recurso identificado por un localizador uniforme y se manipula mediante la semántica estándar de los métodos HTTP: `GET` para la recuperación, `POST` para la creación, `PUT` para la actualización y `DELETE` para la eliminación. El servicio es completamente **sin estado** (*stateless*), es decir, cada petición transporta la totalidad de la información necesaria para su procesamiento y el servidor no mantiene contexto conversacional entre peticiones consecutivas, propiedad que simplifica el razonamiento sobre la concurrencia y habilita el escalamiento horizontal del servicio.

El enrutamiento se resuelve mediante un controlador frontal (*front controller*) único, implementado en el archivo `backend/api/index.php`. El script `router.php` intercepta la totalidad del tráfico dirigido al prefijo `/api` mediante la expresión regular `#^/api(?|$|/(.*)$)#`, extrae la ruta residual y la inyecta en el parámetro `path`. El controlador frontal normaliza dicha ruta suprimiendo las barras extremas, la descompone en segmentos mediante `explode('/', $path)` y determina el controlador y método destino en función de la combinación del verbo HTTP y del patrón de segmentos. Los identificadores numéricos incrustados en la ruta se validan con `ctype_digit()` antes de su conversión a entero, con lo cual una ruta malformada del tipo `/api/sensors/abc` no alcanza jamás la capa de persistencia.

**Tabla 1.** Mapa de recursos expuestos por la API

| Método y recurso | Controlador y operación | Propósito |
|---|---|---|
| `GET /api/sensors` | `SensorsController::index` | Listado de entidades monitoreadas; el parámetro `with_variables=1` incorpora las variables de cada una |
| `POST /api/sensors` | `SensorsController::store` | Registro de una nueva entidad y asignación de su credencial |
| `GET|PUT|DELETE /api/sensors/:id` | `SensorsController::show|update|delete` | Consulta, modificación y baja de una entidad |
| `GET|POST /api/sensors/:id/variables` | `SensorVariablesController::index|store` | Consulta y **declaración de nuevas magnitudes** |
| `PUT|DELETE /api/sensors/:id/variables/:vid` | `SensorVariablesController::update|delete` | Modificación y baja de una magnitud |
| `GET /api/sensors/:id/measurements` | `MeasurementsController::index` | Recuperación del histórico; admite `limit`, `variable_id` y `chart` |
| `GET /api/sensors/:id/formulas` | `FormulasController::index` | Consulta de las ecuaciones asociadas |
| `POST /api/formulas`, `PUT|DELETE /api/formulas/:id` | `FormulasController::store|update|delete` | Gestión de las ecuaciones de transformación |
| `GET /api/sensors/:id/alert-rules` | `AlertRulesController::index` | Consulta de reglas de supervisión |
| `POST /api/alert-rules`, `PUT|DELETE /api/alert-rules/:id` | `AlertRulesController::store|update|delete` | Gestión de reglas de supervisión |
| `GET /api/alerts` | `AlertsController::index` | Consulta de eventos de alerta; admite `unread_only` y `limit` |
| `POST /api/alerts/:id/read` | `AlertsController::markRead` | Acuse de lectura de un evento |
| `POST /api/data` | `DataController::store` | **Ingesta de datos mediante cuerpo JSON** |
| `GET /api/data/ingest` | `DataController::storeFromGet` | **Ingesta de datos mediante parámetros de consulta** |

Fuente: elaboración propia.

Toda ruta que no corresponda a ninguno de los patrones anteriores produce una respuesta `404 Not Found` con el eco de la ruta solicitada, y la totalidad del despacho se encuentra envuelta en un bloque `try/catch (Throwable $e)` que convierte cualquier excepción no prevista en una respuesta `500 Internal Server Error` con estructura JSON, garantizando que **el cliente jamás reciba una traza de error de PHP en texto plano**, comportamiento reforzado por la directiva `ini_set('display_errors', '0')`.

### 2.13.1.2 Agnosticismo del contrato de ingesta respecto de las magnitudes

La característica metodológicamente más relevante del contrato de ingesta es que **no declara campo alguno correspondiente a una magnitud física concreta**. El servidor no conoce a priori los nombres `nivel` o `temperatura`; lo que conoce es un procedimiento de resolución dinámica: por cada par nombre-valor recibido, consulta la relación `sensor_variables` en busca de una variable con ese nombre perteneciente a la entidad identificada, y persiste el valor únicamente si dicha variable ha sido previamente declarada por el usuario desde la interfaz web. La lógica correspondiente, implementada en `DataController::saveMeasurements()`, se expresa en su forma esencial como:

```php
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
```

De esta construcción se derivan tres propiedades de diseño. Primera, el contrato es **extensible sin modificación del servidor**: declarar una nueva magnitud desde el formulario de la vista `SensorVariables` la habilita inmediatamente para su recepción, sin despliegue de código nuevo. Segunda, el contrato es **autofiltrante**: los pares cuyo nombre no corresponde a ninguna variable declarada se descartan silenciosamente en lugar de generar un error, lo que confiere tolerancia a clientes que emitan campos no configurados en una instalación determinada. Tercera, el contrato es **autodocumentado**, por cuanto la respuesta enumera explícitamente en el arreglo `saved_measured` las magnitudes efectivamente persistidas, permitiendo al cliente detectar por diferencia cuáles de sus campos fueron ignorados.

La declaración de una magnitud se realiza mediante el recurso `POST /api/sensors/:id/variables`, cuyo esquema JSON es el siguiente:

```json
{
  "name": "nivel",
  "type": "measure",
  "unit": "cm"
}
```

El campo `name` es la cadena que actuará como clave de resolución durante la ingesta y que podrá emplearse como identificador dentro de las expresiones matemáticas descritas en la subsección 2.13.3. El campo `type` admite exclusivamente los valores `measure`, para magnitudes recibidas del exterior, y `calculated`, para magnitudes producidas por evaluación de una ecuación en el servidor; `SensorVariablesController::store()` normaliza cualquier otro valor a `measure`, aplicando el principio de configuración segura por omisión. El campo `unit` es una etiqueta descriptiva de presentación —`cm`, `°C`, `%`, `mm`— que la capa de presentación emplea para rotular los ejes de las representaciones gráficas y que no interviene en cálculo alguno. El controlador rechaza con código `400` tanto el nombre vacío como el nombre duplicado dentro de la misma entidad, verificación que anticipa en la capa de aplicación la restricción de unicidad declarada en el esquema relacional.

### 2.13.1.3 Ingesta mediante POST con cuerpo JSON

El recurso principal de ingesta es `POST /api/data`, atendido por el método `DataController::store()`. El cuerpo de la petición debe ser un documento JSON conforme al esquema siguiente, mostrado con la configuración particular de la presente investigación:

```json
{
  "key": "abc123",
  "values": {
    "nivel": 12.480,
    "temperatura": 25.317
  }
}
```

El documento admite además la forma extendida con marca temporal explícita e identificación por clave subrogada:

```json
{
  "sensor_id": 1,
  "measured_at": "2026-03-18 14:22:07",
  "values": {
    "nivel": 12.480,
    "temperatura": 25.317
  }
}
```

**Tabla 2.** Especificación del esquema JSON de la petición de ingesta

| Campo | Tipo JSON | Obligatoriedad | Validación aplicada en el servidor | Persistencia |
|---|---|---|---|---|
| `key` | Cadena | Alternativa excluyente con `sensor_id` | `trim()` y resolución contra la columna única `sensors.api_key` mediante sentencia preparada | `VARCHAR(64)` |
| `sensor_id` | Entero | Alternativa excluyente con `key` | `filter_var(..., FILTER_VALIDATE_INT)` y verificación de valor mayor o igual a uno | `INT UNSIGNED` |
| `values` | Objeto | Sí | Debe ser arreglo asociativo; cada valor debe satisfacer `is_numeric()` | — |
| `values.<nombre>` | Número | Al menos uno | Resolución dinámica del nombre contra `sensor_variables`; conversión explícita a `float` | `DECIMAL(20,6)` |
| `measured_at` | Cadena | No | `DateTime::createFromFormat('Y-m-d H:i:s', $s)`; ante ausencia o formato inválido se asigna la hora del servidor | `DATETIME` |

Fuente: elaboración propia.

La validación se concentra en el método privado `validateInput()`, que opera por referencia sobre el arreglo de entrada y **resuelve la credencial a identificador numérico antes de que la petición alcance la lógica de persistencia**, de modo que el resto del flujo opera uniformemente sobre `sensor_id` con independencia del modo de identificación empleado por el cliente. Los valores no numéricos no se descartan silenciosamente en esta ruta: se acumulan en el arreglo `$invalid` y se devuelven al cliente en el campo `invalid_entries` de la respuesta de error, proporcionando un diagnóstico preciso del campo infractor y de su contenido. La deserialización se realiza en `getJsonInput()`, que lee el flujo `php://input` y verifica el resultado con `json_last_error()`, de forma que un documento sintácticamente inválido produce `400 Bad Request` con el mensaje del analizador sintáctico en lugar de un fallo silencioso.

Debe destacarse la elección del tipo de persistencia `DECIMAL(20,6)` frente a los tipos de coma flotante binaria. Los valores llegan al servidor como números JSON de doble precisión o como cadenas numéricas y se almacenan en un tipo de **precisión decimal fija**, decisión deliberada por cuanto los tipos `FLOAT` y `DOUBLE` del gestor introducen errores de representación no deterministas, inadmisibles en un histórico destinado a servir de insumo para inferencia estadística y para el ajuste de los modelos de calibración.

### 2.13.1.4 Ingesta mediante GET con parámetros de consulta

Con el objeto de admitir clientes de recursos computacionales limitados o carentes de biblioteca de serialización JSON, la API expone la ruta equivalente `GET /api/data/ingest`, en la cual la carga útil se codifica en la cadena de consulta:

```http
GET /api/data/ingest?key=abc123&nivel=12.480&temperatura=25.317
```

Esta variante reduce el tamaño de la trama al suprimir las cabeceras `Content-Type` y `Content-Length` junto con el cuerpo de la petición, y simplifica la verificación funcional, por cuanto cualquier navegador o cliente `curl` puede reproducir la transmisión exacta sin herramientas especializadas. Su semántica de resolución es idéntica a la de la ruta POST: el método `storeFromGet()` recorre la superglobal `$_GET`, excluye los parámetros reservados mediante el arreglo `$reserved = ['key', 'measured_at']` —previniendo que sean interpretados erróneamente como magnitudes— y admite como candidato todo par restante cuyo valor satisfaga simultáneamente ser no vacío y `is_numeric()`.

Corresponde señalar que esta ruta constituye una desviación consciente respecto de la ortodoxia REST, según la cual el método `GET` debe ser seguro e idempotente y, por tanto, carecer de efectos sobre el estado del servidor. La desviación se documenta explícitamente y se justifica por el requisito de compatibilidad con clientes restringidos; su alcance se limita a un único recurso, mientras que el resto de la API observa rigurosamente la semántica de los verbos HTTP. La respuesta se emite con código `201 Created`, coherente con el efecto real de creación de recursos.

### 2.13.1.5 Esquema de la respuesta y semántica de los códigos de estado

Ambas rutas de ingesta convergen en la misma tubería de procesamiento —persistencia de magnitudes medidas, evaluación de ecuaciones y verificación de reglas de alerta— y en el mismo esquema de respuesta, emitido por la clase utilitaria `JsonResponse`. Dicha clase centraliza la fijación del código de estado mediante `http_response_code()` y la emisión de la cabecera `Content-Type: application/json; charset=utf-8`, e invoca `json_encode()` con la bandera `JSON_UNESCAPED_UNICODE`, indispensable para la representación literal de unidades como `°C` sin secuencias de escape que degradarían la legibilidad del cuerpo:

```json
{
  "success": true,
  "sensor_id": 1,
  "measured_at": "2026-03-18 14:22:07",
  "saved_measured": ["nivel", "temperatura"],
  "saved_calculated": ["grosor"],
  "alerts_triggered": 0
}
```

Este documento no constituye un simple acuse de recibo, sino un **informe de ejecución de la tubería completa**: el arreglo `saved_measured` enumera las magnitudes medidas efectivamente persistidas, `saved_calculated` las magnitudes derivadas obtenidas por evaluación de ecuaciones —según el algoritmo descrito en la subsección 2.13.3— y `alerts_triggered` el cardinal de reglas de umbral satisfechas durante el mismo ciclo. Un cliente puede así verificar en una única transacción no solo que su envío fue aceptado, sino qué magnitudes reconoció el servidor, qué valores derivó de ellas y si alguna condición de supervisión resultó activada.

**Tabla 3.** Códigos de estado HTTP y su semántica en el contrato

| Código | Condición que lo origina | Estructura del cuerpo |
|---|---|---|
| `200 OK` | Recuperación exitosa de un recurso o actualización efectiva | Representación del recurso |
| `201 Created` | Creación de una entidad, variable, ecuación, regla, o persistencia de al menos una medición | Representación del recurso creado o informe de ejecución |
| `204 No Content` | Eliminación efectiva y respuesta a la petición de verificación previa `OPTIONS` | Vacío, por especificación |
| `400 Bad Request` | Ausencia de campo obligatorio, cuerpo JSON malformado, valores no numéricos o nombre duplicado | `{"error": "...", "field": "...", "invalid_entries": [...]}` |
| `404 Not Found` | Credencial no registrada, identificador inexistente o ruta no reconocida | `{"error": "...", "key": "...", "path": "..."}` |
| `500 Internal Server Error` | Excepción no prevista capturada por el bloque `catch (Throwable $e)` del controlador frontal | `{"error": "Error interno del servidor", "message": "..."}` |

Fuente: elaboración propia.

La uniformidad de este esquema de error —presencia garantizada del campo `error` con un mensaje legible— es explotada por la capa de presentación en la función `request()` del cliente HTTP, que construye un objeto `Error` enriquecido con los atributos `status` y `data`, propagando hacia los componentes de React un diagnóstico estructurado en lugar de un fallo genérico de red.

### 2.13.1.6 Política de intercambio de recursos entre orígenes

Dado que la capa de presentación y la capa de servicios se despliegan en orígenes distintos —puerto 5173 para el servidor de desarrollo de Vite y puerto 8000 para el servidor PHP—, la política del mismo origen aplicada por los navegadores bloquearía las peticiones asíncronas. La solución se implementó en dos niveles complementarios.

En desarrollo, el servidor de Vite actúa como intermediario inverso mediante la configuración declarada en `vite.config.js`, que redirige el prefijo `/api` hacia `http://localhost:8000` con reescritura de la cabecera `Host`. Bajo este esquema el navegador percibe un origen único y la restricción no llega a plantearse.

En despliegue, el archivo `config/cors.php` —incluido antes que cualquier otra dependencia en el controlador frontal— implementa una autorización selectiva por lista blanca, admitiendo los orígenes correspondientes a `localhost` o `127.0.0.1` en cualquier puerto, los orígenes expresados como dirección IPv4 literal, y los declarados explícitamente en la variable de entorno `CORS_ALLOWED_ORIGINS`. La política **refleja el origen concreto de la petición** en la cabecera `Access-Control-Allow-Origin` únicamente cuando este supera la validación, en lugar de emitir el comodín `*`, práctica que restringe la superficie expuesta. Las peticiones de verificación previa `OPTIONS`, generadas por el navegador ante la presencia de la cabecera `Content-Type: application/json`, se responden con `204 No Content` y una directiva `Access-Control-Max-Age: 86400` que autoriza al navegador a memorizar la autorización durante veinticuatro horas, suprimiendo un intercambio preparatorio por cada petición subsiguiente.

## 2.13.2 Arquitectura de la base de datos relacional

### 2.13.2.1 Justificación del modelo genérico frente al modelo de columnas fijas

La decisión estructural de mayor alcance del diseño de persistencia consistió en descartar el modelo intuitivo —una tabla de mediciones con una columna por magnitud, del tipo `mediciones(id, fecha, nivel, temperatura)`— en favor de un modelo genérico de tipo entidad-atributo-valor. El modelo de columnas fijas presenta tres deficiencias que lo hacen inadmisible para los objetivos del presente trabajo. En primer lugar, incorporar una nueva magnitud exigiría ejecutar una sentencia de definición de datos `ALTER TABLE`, operación privilegiada que bloquea la relación durante su ejecución y que no puede delegarse en el usuario final desde una interfaz web sin comprometer gravemente la seguridad. En segundo lugar, entidades heterogéneas —una con tres magnitudes y otra con diez— compartirían la misma estructura, generando una proporción elevada de valores nulos que degradaría el almacenamiento y complicaría las consultas. En tercer lugar, la unidad de medida y la naturaleza de cada magnitud quedarían implícitas en el nombre de la columna, sin lugar donde registrarlas formalmente.

El modelo adoptado eleva las magnitudes a la categoría de **filas de datos** en la relación `sensor_variables`, con lo cual la operación de «añadir una variable» pasa de ser una modificación del esquema a ser una inserción ordinaria, ejecutable por el usuario final desde el navegador, sin privilegios administrativos, sin bloqueo de tablas y sin interrupción del servicio. Este es el fundamento técnico que confiere a la plataforma su carácter genérico: la configuración con las magnitudes `nivel` y `temperatura` es un estado de los datos, no una propiedad del software.

El esquema comprende seis relaciones organizadas en tres estratos funcionales: definición (`sensors`, `sensor_variables`), transformación (`formulas`) y observación (`measurements`, `alert_rules`, `alerts`). La totalidad de ellas se declaró con motor de almacenamiento **InnoDB** y juego de caracteres `utf8mb4` con cotejamiento `utf8mb4_unicode_ci`. La elección de InnoDB no es accesoria: es el único motor de MySQL que implementa restricciones de clave foránea con verificación efectiva y transacciones conformes a las propiedades ACID, condiciones necesarias para las garantías de integridad descritas en 2.13.2.7. El juego `utf8mb4`, por su parte, codifica la totalidad del plano multilingüe suplementario de Unicode, permitiendo almacenar sin pérdida símbolos de unidad como `°C` o `µm` en la columna `sensor_variables.unit`.

### 2.13.2.2 Relación `sensors`: raíz de la jerarquía de propiedad

Constituye la entidad raíz del modelo y representa cada objeto o proceso sujeto a monitorización. Su clave primaria `id`, de tipo `INT UNSIGNED` con incremento automático, es un identificador subrogado, compacto y monótono, deliberadamente independiente de cualquier atributo del dominio, de manera que el renombramiento de una entidad no propague actualizaciones a las relaciones dependientes.

El atributo `api_key VARCHAR(64)` está protegido por la restricción `UNIQUE KEY uk_sensors_api_key`, y es precisamente esta restricción la que confiere validez formal al mecanismo de identificación de clientes descrito en 2.13.1: la consulta `SELECT ... FROM sensors WHERE api_key = ?` ejecutada por `SensorModel::getByApiKey()` está garantizada a retornar a lo sumo una fila, de modo que la resolución credencial → entidad es determinista **por construcción del esquema y no por convención de programación**. Adicionalmente, la restricción impide a nivel de motor que dos clientes compartan credencial, situación que provocaría la mezcla indistinguible de sus series temporales y la corrupción irreversible del histórico.

Los atributos `created_at` y `updated_at` implementan trazabilidad temporal automática mediante las cláusulas `DEFAULT CURRENT_TIMESTAMP` y `ON UPDATE CURRENT_TIMESTAMP`, delegando en el gestor —y no en la aplicación— la consistencia de la auditoría, con lo cual ninguna modificación puede eludir el registro por omisión del programador.

### 2.13.2.3 Relación `sensor_variables`: catálogo semántico del sistema

Esta relación es el núcleo del carácter genérico de la plataforma: constituye el catálogo declarativo de las magnitudes que el sistema reconoce. El atributo `type ENUM('measure','calculated')` particiona el conjunto de variables en dos clases disjuntas con semántica de escritura diferenciada: las de tipo `measure`, cuyo valor procede de una petición de ingesta externa, y las de tipo `calculated`, cuyo valor es producido internamente por la evaluación de una ecuación. El atributo `unit VARCHAR(20)` registra la unidad de medida, dato que el modelo de columnas fijas no tendría dónde alojar y que la capa de presentación consume para rotular las representaciones gráficas.

El elemento de diseño más significativo es la restricción `UNIQUE KEY uk_sensor_variable (sensor_id, name)`, que declara la pareja (entidad, nombre) como **clave candidata natural**. Esta restricción es el fundamento formal del contrato de ingesta expuesto en 2.13.1.2: cuando el servidor recibe el par `nivel = 12.480`, el método `SensorVariableModel::getByNameAndSensor()` ejecuta `WHERE sensor_id = ? AND name = ?` con la certeza —garantizada por el motor y no por el código de la aplicación— de obtener a lo sumo un registro, por lo que la correspondencia nombre → identificador es unívoca y la ingesta es determinista. La restricción es de alcance local a la entidad, lo que permite deliberadamente que dos entidades distintas posean magnitudes homónimas —dos depósitos, cada uno con su variable `nivel`— sin colisión ni ambigüedad, propiedad indispensable en una plataforma multientidad.

La clave foránea `fk_sensor_variables_sensor` referencia `sensors(id)` con la acción `ON DELETE CASCADE`.

### 2.13.2.4 Relación `formulas`: parametrización del modelo matemático

Almacena las reglas de transformación que producen las magnitudes calculadas. La separación entre los atributos `expression TEXT` —la estructura simbólica del modelo, por ejemplo `b0 + b1 * delta_nivel`— y `parameters JSON` —los coeficientes numéricos, por ejemplo `{"b0": -3.595, "b1": 11.221}`— es una decisión metodológica de primer orden: **permite recalibrar el modelo modificando un único registro desde la interfaz web, sin desplegar código nuevo y sin detener el servicio**. El uso del tipo nativo `JSON` de MySQL, disponible desde la versión 5.7, añade validación sintáctica en el momento de la inserción, de modo que el gestor rechaza documentos malformados antes de que puedan alcanzar el evaluador; el modelo `FormulaModel` encapsula la serialización con `json_encode()` en la escritura y `json_decode($r['parameters'], true)` en la lectura, de forma que las capas superiores manipulan siempre arreglos nativos de PHP.

La relación posee dos claves foráneas: `fk_formulas_sensor` hacia `sensors(id)` y `fk_formulas_result_variable` hacia `sensor_variables(id)`. Esta segunda restricción garantiza que ninguna ecuación pueda apuntar a una variable de destino inexistente, previniendo el escenario en que el sistema calcularía un resultado sin ubicación válida donde depositarlo. La interfaz web refuerza esta garantía en el origen: el formulario de la vista `Formulas` puebla el selector de variable resultado exclusivamente con aquellas de tipo `calculated`, mediante el filtro `variables.filter((v) => v.type === 'calculated')`, y deshabilita la creación de ecuaciones cuando no existe ninguna candidata.

### 2.13.2.5 Relación `measurements`: núcleo del histórico de series temporales

Es la relación de mayor cardinalidad esperada del sistema, razón por la cual su clave primaria se declaró de tipo `BIGINT UNSIGNED`, con un dominio de 2⁶⁴ − 1 valores que descarta cualquier agotamiento del contador en el horizonte de vida de la instalación. Su estructura materializa el modelo genérico: cada fila es una tripleta que asocia una variable, un valor y un instante, de manera que **el número de magnitudes que el sistema puede almacenar es ilimitado y no guarda relación alguna con el número de columnas de la tabla**.

La relación distingue dos marcas temporales con semántica diferenciada. El atributo `measured_at` registra el instante en que la magnitud fue observada en el fenómeno, susceptible de ser suministrado por el cliente para la reinyección de datos históricos o su corrección posterior. El atributo `created_at` registra el instante de escritura efectiva en la base de datos y no es modificable por el cliente. Su diferencia constituye la latencia de ingesta y permite auditar retransmisiones diferidas o desfases del reloj del emisor, información que se perdería irrecuperablemente bajo una sola marca temporal.

Se definieron cuatro índices secundarios, entre los cuales destaca el índice compuesto `idx_sensor_measured (sensor_id, measured_at)`. Este índice responde directamente al patrón de acceso dominante de la aplicación —consultas de la forma `WHERE m.sensor_id = ? ORDER BY m.measured_at DESC LIMIT ?`, ejecutadas por `MeasurementModel::getLatestBySensor()` y `getForChart()`—, permitiendo al optimizador satisfacer simultáneamente el filtrado y la ordenación mediante un único recorrido del árbol B+ y eliminando la operación de ordenación en memoria (*filesort*), cuyo costo crecería linealmente con el volumen acumulado del histórico y degradaría progresivamente el tiempo de respuesta de la interfaz.

Ambos métodos de consulta ejecutan una reunión con `sensor_variables` para proyectar el atributo `v.name AS variable_name`, de modo que la capa de presentación recibe la serie ya rotulada semánticamente y no requiere resolver identificadores contra un catálogo local. El controlador `MeasurementsController::index()` acota además el parámetro `limit` mediante la expresión `max(1, min(500, $limit))`, defensa que impide que un cliente induzca la recuperación de un volumen arbitrario de registros en una sola petición.

### 2.13.2.6 Relaciones `alert_rules` y `alerts`: supervisión declarativa

Las condiciones de supervisión también se declaran como datos y no como código. La relación `alert_rules` expresa cada condición como la tripleta variable-operador-umbral, con el operador restringido por el tipo `ENUM('>', '<', '>=', '<=', '=')` —restricción de dominio verificada por el gestor que hace imposible almacenar un operador no soportado— y el umbral en el mismo tipo `DECIMAL(20,6)` empleado para los valores medidos, garantizando que la comparación se realice entre magnitudes de idéntica representación. El atributo `is_active TINYINT(1)` permite suspender temporalmente una regla sin eliminarla, preservando su definición y el histórico de eventos asociado.

La relación `alerts` registra cada activación efectiva. Su diseño incorpora una decisión deliberada de **desnormalización orientada a la preservación histórica**: además de referenciar la regla que la originó, la fila duplica los atributos `threshold_value` y `operator` vigentes en el momento del disparo, junto con el `value` que lo provocó. De este modo, la modificación posterior del umbral de una regla no altera retroactivamente el significado de los eventos ya registrados, propiedad indispensable para que el histórico de alertas constituya evidencia auditable. El atributo `read_at DATETIME DEFAULT NULL` implementa el acuse de lectura mediante el patrón de marca temporal anulable, que registra simultáneamente el hecho y el instante de la lectura, información que un simple indicador booleano no conservaría.

### 2.13.2.7 Garantía de integridad referencial

La consistencia del histórico se sustenta en tres mecanismos complementarios, ordenados de mayor a menor nivel de garantía.

**Restricciones de clave foránea con propagación en cascada.** La totalidad de las relaciones dependientes declara sus claves foráneas con `ON DELETE CASCADE` hacia `sensors(id)` y, cuando corresponde, hacia `sensor_variables(id)`. El efecto es doble. En sentido de inserción, el motor rechaza con error toda medición cuyo `variable_id` no exista en el catálogo, haciendo **imposible por construcción la existencia de registros huérfanos**: no puede subsistir en el histórico un valor numérico sin una variable que le confiera significado semántico ni una unidad que permita interpretarlo. Esta garantía es especialmente crítica en un modelo genérico, donde el significado del dato reside íntegramente en la relación de catálogo y no en el nombre de una columna. En sentido de eliminación, la supresión de una entidad propaga la eliminación a sus variables, ecuaciones, mediciones, reglas y alertas en una única transacción atómica, evitando el estado intermedio inconsistente en que subsistirían mediciones apuntando a una entidad ya inexistente. La cascada se activa mediante la operación única `DELETE FROM sensors WHERE id = ?` de `SensorModel::delete()`, sin que la aplicación deba orquestar el borrado en el orden correcto, y la interfaz advierte explícitamente de su alcance antes de solicitar confirmación al usuario.

**Uso exclusivo de sentencias preparadas.** La totalidad de los métodos de la capa de modelo emplea `PDO::prepare()` con marcadores posicionales y vinculación de parámetros en `execute()`, sin que exista un solo punto del código donde una entrada del usuario se concatene a una cadena SQL. Este patrón separa el plano de instrucción del plano de datos en el protocolo cliente-servidor de MySQL, de modo que ningún valor procedente de una petición de ingesta o de un formulario de la interfaz puede ser reinterpretado como instrucción. Se neutraliza así el vector de inyección SQL, que constituye simultáneamente una amenaza de seguridad y una amenaza de integridad, por cuanto una inyección exitosa permitiría la modificación arbitraria e indetectable del histórico. La conexión se establece además con el modo de reporte de errores por excepción, lo que impide que un fallo de escritura pase inadvertido.

**Validación de dominio en la capa de aplicación.** Previamente a toda escritura, los controladores verifican la existencia de la entidad referenciada, filtran los valores no numéricos mediante `is_numeric()` y validan el formato de la marca temporal con `DateTime::createFromFormat('Y-m-d H:i:s', $s)`. `SensorVariablesController` comprueba adicionalmente la unicidad del nombre antes de insertar y verifica, en las operaciones de modificación y baja, que la variable pertenezca efectivamente a la entidad indicada en la ruta mediante la condición `(int) $variable['sensor_id'] !== $sensorId`, control de autorización horizontal que impide manipular por ruta cruzada un recurso ajeno.

Como consideración de diseño para trabajos futuros, se documenta que la relación `measurements` conserva el atributo `sensor_id` de forma redundante, dado que dicho dato es derivable mediante la reunión con `sensor_variables`. Esta desnormalización controlada fue adoptada para evitar una operación de reunión en las consultas de mayor frecuencia y resulta consistente en la práctica porque `saveMeasurements()` resuelve siempre la variable dentro del ámbito de la entidad identificada. No obstante, el esquema no impone dicha coherencia mediante una restricción declarativa; una evolución del modelo podría añadir la clave única `(sensor_id, id)` en `sensor_variables` y sustituir las dos claves foráneas simples de `measurements` por una clave foránea compuesta, elevando la garantía del nivel aplicativo al nivel del motor de almacenamiento.

## 2.13.3 Algoritmo del evaluador de ecuaciones seguro (RPN)

### 2.13.3.1 Planteamiento del problema y justificación de la exclusión de `eval()`

El requisito funcional de permitir que el usuario defina, desde la interfaz web y sin intervención del desarrollador, las ecuaciones que transforman las magnitudes medidas en magnitudes derivadas constituye la contrapartida lógica del modelo de datos genérico: de poco serviría poder declarar variables arbitrarias si las relaciones entre ellas permanecieran codificadas en el programa. Su implementación, sin embargo, plantea un problema clásico de seguridad informática.

La solución trivial consistiría en delegar la evaluación en la construcción `eval()` del lenguaje PHP, que interpreta una cadena arbitraria como código fuente. Dicha aproximación es inadmisible: la cadena `expression` recorre un trayecto que se origina en un campo de texto del navegador, atraviesa la red y reposa en la base de datos, por lo que su contenido debe considerarse dato no confiable en todo momento. Una expresión maliciosa de la forma `1; system("rm -rf /");` obtendría, bajo `eval()`, ejecución remota de código arbitrario con los privilegios del proceso servidor, la vulnerabilidad de mayor severidad en la clasificación convencional. Adicionalmente, `eval()` permitiría la construcción de bucles no acotados que agotarían los recursos del proceso, degradando la disponibilidad del servicio para el resto de los clientes.

Por esta razón se implementó, en la clase `FormulaEvaluator` (archivo `backend/utils/FormulaEvaluator.php`), un intérprete propio de dominio específico. Su propiedad de seguridad fundamental es **estructural y no basada en el filtrado de listas negras**: el evaluador carece por completo de mecanismo para invocar funciones, acceder a variables del entorno de ejecución o producir efectos laterales, puesto que su gramática admite exclusivamente cuatro operadores binarios, paréntesis, literales numéricos e identificadores. Aun cuando un atacante lograra introducir una expresión arbitraria en la base de datos, el peor resultado alcanzable es una operación aritmética o el lanzamiento de una excepción controlada.

### 2.13.3.2 Validación previa por lista blanca

Antes de iniciar el análisis, el método público `evaluate()` normaliza la expresión eliminando la totalidad de los espacios en blanco mediante `preg_replace('/\s+/', '', $expression)` y la contrasta contra la expresión regular de caracteres admitidos:

```php
private const SAFE_CHARS = '/^[a-zA-Z0-9+\-*\/.()_\s]+$/';
```

Se trata de una lista blanca anclada en ambos extremos mediante los metacaracteres `^` y `$`, es decir, la totalidad de la cadena debe pertenecer al alfabeto autorizado y no meramente contener una subcadena válida. Los caracteres típicamente asociados a vectores de ataque —comillas simples y dobles, punto y coma, signo de dólar, llaves, corchetes y caracteres de control— quedan excluidos por omisión y no por enumeración, propiedad de seguridad notablemente más robusta que el enfoque de lista negra, el cual resulta vulnerable a toda construcción que el diseñador no haya anticipado.

### 2.13.3.3 Primera etapa: análisis léxico (tokenización)

El método privado `tokenize()` implementa un autómata finito determinista que recorre la cadena carácter a carácter, en un único paso y sin retroceso, produciendo una secuencia de unidades léxicas (*tokens*). Cada unidad es un arreglo asociativo con los campos `type` y `value`, donde el tipo pertenece al conjunto {`number`, `identifier`, `operator`, `paren`}.

El reconocimiento procede mediante clasificación por categoría del carácter actual. Los paréntesis se emiten directamente. Los operadores del conjunto `{+, -, *, /}` se emiten como unidades de tipo `operator`. Ante un dígito o un punto decimal, el autómata entra en modo de acumulación numérica, consumiendo caracteres mientras satisfagan `ctype_digit()` o sean el punto decimal, y verifica el lexema resultante con `is_numeric()` antes de convertirlo a `float`, lo que permite detectar números malformados como `1.2.3`. Ante una letra o un guion bajo, entra en modo de acumulación de identificadores, consumiendo caracteres alfanuméricos y guiones bajos, y valida el lexema contra el patrón:

```php
private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
```

Este patrón exige que todo identificador comience por letra o guion bajo, con lo cual se impide la aparición de construcciones ambiguas como `2x`, interpretables tanto como producto implícito cuanto como identificador. Cualquier carácter no clasificable provoca el lanzamiento de una excepción `InvalidArgumentException` con el carácter infractor incorporado al mensaje de diagnóstico. Conviene subrayar que este patrón define simultáneamente la regla de nomenclatura admisible para las variables declaradas en la interfaz web: para que una magnitud pueda intervenir en una ecuación, su nombre debe ser un identificador válido, restricción que la documentación de usuario debe recoger explícitamente.

El aspecto algorítmicamente más delicado de esta etapa es el tratamiento del **signo menos unario**, indispensable para el modelo de calibración del presente trabajo, cuyo término independiente es negativo. Puesto que la etapa de evaluación implementa exclusivamente operadores binarios, el analizador léxico resuelve la ambigüedad mediante la técnica del **cero implícito**: cuando detecta el carácter `-` en una posición donde no puede figurar un operador binario —al inicio de la expresión, inmediatamente después de otro operador, o inmediatamente después de un paréntesis de apertura—, inserta un literal `0.0` antes de emitir el operador, transformando la operación unaria `-x` en la operación binaria equivalente `0 - x`. La condición se evalúa en el código como:

```php
$allowUnaryMinus = ($lastType === null || $lastType === 'operator' ||
    ($lastType === 'paren' && $lastToken !== false && $lastToken['value'] === '('));
```

### 2.13.3.4 Segunda etapa: conversión a notación polaca inversa

La notación infija convencional es ambigua sin un conjunto adicional de reglas de precedencia y asociatividad, y su evaluación directa requeriría un análisis recursivo con retroceso. Por ello, el método `toRPN()` transforma la secuencia de unidades léxicas a **notación polaca inversa** (RPN, *Reverse Polish Notation*), representación posfija en la cual cada operador sucede a sus operandos y que, por construcción, carece de paréntesis y es libre de ambigüedad.

La transformación implementa el algoritmo de la estación de clasificación (*shunting-yard*), formulado por Edsger W. Dijkstra, con dos estructuras auxiliares: una cola de salida `$output` y una pila de operadores `$stack`. La tabla de precedencia se declara explícitamente:

```php
$precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
```

Las reglas de transición son las siguientes. Los literales numéricos y los identificadores se transfieren de inmediato a la cola de salida, preservando su orden relativo. Un paréntesis de apertura se apila sin condición. Un paréntesis de cierre provoca el desapilado y la transferencia a la salida de todos los operadores hasta encontrar el paréntesis de apertura correspondiente, que se descarta; el agotamiento de la pila sin hallarlo constituye un desbalance y genera excepción. Ante un operador, se desapilan y transfieren a la salida todos los operadores del tope cuya precedencia sea mayor o igual que la del operador entrante —condición expresada en el código como el complemento de `$precedence[$top['value']] < $precedence[$t['value']]`—, y a continuación se apila el operador entrante. La utilización del predicado *mayor o igual* implementa la **asociatividad por la izquierda**, propiedad que garantiza la interpretación de `1-2-3` como `(1-2)-3` y de `100/10/2` como `(100/10)/2`, comportamiento verificado experimentalmente con resultados de −4 y 5 respectivamente. Concluida la lectura, se vacía la pila hacia la salida; la presencia residual de un paréntesis indica desbalance y genera excepción.

### 2.13.3.5 Tercera etapa: evaluación mediante estructura de pila

El método `evaluateRPN()` recorre linealmente la cola posfija operando sobre una pila de valores de tipo `float`. Los literales numéricos se apilan directamente. Los identificadores se **resuelven por consulta al contexto**, un arreglo asociativo construido en `DataController::computeAndSaveDerivedVariables()` mediante `array_merge($context, $params)`, que fusiona las magnitudes recibidas en el ciclo de ingesta con los coeficientes almacenados en el atributo `parameters` de la ecuación. La resolución impone dos verificaciones sucesivas: la existencia de la clave, comprobada con `array_key_exists()`, y la naturaleza numérica del valor asociado, comprobada con `is_numeric()`.

Esta doble verificación es determinante para la propiedad de seguridad del evaluador, pues **un identificador es únicamente una clave de búsqueda en un arreglo controlado por el servidor y jamás una referencia al espacio de nombres del intérprete PHP**; un identificador ausente del contexto no se resuelve a nulo ni a cadena vacía —comportamiento que produciría un resultado numérico silenciosamente erróneo—, sino que aborta la evaluación con excepción.

Ante un operador, se verifica la disponibilidad de al menos dos operandos —condición que detecta expresiones incompletas—, se extraen en el orden `$b = array_pop(); $a = array_pop();` (invirtiendo el orden de extracción respecto del de inserción, lo cual preserva la no conmutatividad de la resta y la división) y se apila el resultado. La división incorpora una guarda de tolerancia numérica, `if (abs($b) < 1e-12)`, que rechaza no solo el divisor exactamente nulo sino también los divisores suficientemente próximos a cero como para provocar desbordamiento del resultado; la comparación por umbral es metodológicamente preferible a la igualdad estricta `$b == 0` en aritmética de coma flotante.

La invariante de terminación exige que la pila contenga exactamente un elemento al concluir el recorrido; cualquier otro cardinal denota una expresión malformada, como `3 4 +` con operandos sobrantes, y genera excepción.

El evaluador presenta complejidad temporal y espacial **Θ(n)** respecto del número de unidades léxicas, en las tres etapas, dado que cada unidad se procesa un número constante de veces y cada operador ingresa y egresa de la pila exactamente una vez. Esta cota lineal garantiza que la evaluación de ecuaciones no introduce degradación apreciable en la latencia de la ruta de ingesta, aun cuando una entidad tenga configuradas múltiples ecuaciones encadenadas.

### 2.13.3.6 Traza de ejecución del modelo de calibración

Se presenta a continuación la traza completa del procesamiento de la ecuación de calibración obtenida en el presente trabajo:

$$\text{Grosor} = -3{,}595 + 11{,}221 \cdot \Delta\text{Nivel}$$

Dado que la gramática de identificadores admite exclusivamente caracteres alfanuméricos ASCII y el guion bajo, la variable ΔNivel se declara en la relación `sensor_variables` bajo el nombre `delta_nivel`, y la variable de destino `grosor` se declara con tipo `calculated`. La expresión almacenada en `formulas.expression` es, en su forma literal, `-3.595 + 11.221 * delta_nivel`. Para la traza se adopta el valor observado ΔNivel = 0,842.

Tras la normalización, que elimina los espacios en blanco, la cadena de entrada al analizador léxico es `-3.595+11.221*delta_nivel`.

**Tabla 4.** Traza de la etapa de análisis léxico (tokenización)

| Posición | Carácter | Regla aplicada | Unidad léxica emitida |
|---|---|---|---|
| 0 | `-` | `lastType === null` ⇒ menos unario; inserción de cero implícito | `{number, 0.0}` |
| 0 | `-` | Operador binario del conjunto autorizado | `{operator, -}` |
| 1–5 | `3.595` | Acumulación numérica; validación con `is_numeric()` | `{number, 3.595}` |
| 6 | `+` | Operador binario | `{operator, +}` |
| 7–12 | `11.221` | Acumulación numérica | `{number, 11.221}` |
| 13 | `*` | Operador binario | `{operator, *}` |
| 14–24 | `delta_nivel` | Acumulación de identificador; validación con `IDENTIFIER_PATTERN` | `{identifier, delta_nivel}` |

Fuente: elaboración propia.

La secuencia resultante consta de siete unidades léxicas: `0`, `-`, `3.595`, `+`, `11.221`, `*`, `delta_nivel`.

**Tabla 5.** Traza de la conversión a notación polaca inversa (algoritmo *shunting-yard*)

| Paso | Unidad léxica | Decisión del algoritmo | Pila de operadores | Cola de salida |
|---|---|---|---|---|
| 1 | `0` | Operando: transferencia directa a la salida | ∅ | `0` |
| 2 | `-` (p = 1) | Pila vacía: apilar | `-` | `0` |
| 3 | `3.595` | Operando: transferencia directa a la salida | `-` | `0 3.595` |
| 4 | `+` (p = 1) | Tope `-` con p = 1; como 1 ≮ 1, desapilar `-` hacia la salida (asociatividad izquierda); apilar `+` | `+` | `0 3.595 -` |
| 5 | `11.221` | Operando: transferencia directa a la salida | `+` | `0 3.595 - 11.221` |
| 6 | `*` (p = 2) | Tope `+` con p = 1; como 1 < 2, no se desapila; apilar `*` | `+ *` | `0 3.595 - 11.221` |
| 7 | `delta_nivel` | Operando: transferencia directa a la salida | `+ *` | `0 3.595 - 11.221 delta_nivel` |
| 8 | — (fin de entrada) | Vaciado de la pila: primero `*`, luego `+` | ∅ | `0 3.595 - 11.221 delta_nivel * +` |

Fuente: elaboración propia.

La expresión en notación polaca inversa resultante es: **`0 3.595 − 11.221 delta_nivel × +`**

**Tabla 6.** Traza de la evaluación sobre estructura de pila (contexto: `delta_nivel` = 0,842)

| Paso | Unidad léxica | Operación ejecutada | Estado de la pila (base → tope) |
|---|---|---|---|
| 1 | `0` | Apilar literal | `[0,000000]` |
| 2 | `3.595` | Apilar literal | `[0,000000 ; 3,595000]` |
| 3 | `-` | `b` ← 3,595; `a` ← 0,000; apilar `a − b` = −3,595000 | `[−3,595000]` |
| 4 | `11.221` | Apilar literal | `[−3,595000 ; 11,221000]` |
| 5 | `delta_nivel` | Resolución en contexto: `array_key_exists` ⇒ verdadero; `is_numeric` ⇒ verdadero; apilar 0,842000 | `[−3,595000 ; 11,221000 ; 0,842000]` |
| 6 | `*` | `b` ← 0,842; `a` ← 11,221; apilar `a × b` = 9,448082 | `[−3,595000 ; 9,448082]` |
| 7 | `+` | `b` ← 9,448082; `a` ← −3,595; apilar `a + b` = 5,853082 | `[5,853082]` |
| 8 | — | Verificación de invariante: `count($stack) === 1` ⇒ válido | Resultado: **5,853082** |

Fuente: elaboración propia.

El valor retornado, Grosor = 5,853082, es persistido por `MeasurementModel::insert()` en la relación `measurements` asociado al `result_variable_id` de la ecuación y a la **misma marca `measured_at` de las mediciones que lo originaron**, garantizando la coherencia temporal entre la magnitud medida y su derivada, condición sin la cual las series no serían comparables en las representaciones gráficas. Acto seguido, la magnitud calculada se incorpora al contexto de evaluación, lo que habilita el encadenamiento de ecuaciones —una ecuación puede consumir el resultado de otra evaluada previamente— y su consideración por las reglas de supervisión dentro del mismo ciclo de ingesta, de modo que una alerta puede definirse indistintamente sobre una magnitud medida o sobre una derivada.

Esta traza fue verificada experimentalmente mediante la ejecución instrumentada de la clase `FormulaEvaluator` sobre PHP 8.4, obteniéndose la secuencia posfija y el resultado numérico consignados en las tablas 5 y 6.

### 2.13.3.7 Tratamiento de errores y limitaciones declaradas

La totalidad de las condiciones anómalas —carácter no autorizado, identificador inválido, número malformado, paréntesis desbalanceados, variable no definida, valor no numérico, división por cero y expresión incompleta— se señalizan mediante la excepción tipada `InvalidArgumentException`, portadora de un mensaje diagnóstico específico. En el punto de invocación, `DataController::computeAndSaveDerivedVariables()` captura la excepción, la registra con `error_log()` junto al identificador de la ecuación infractora y continúa el bucle con la siguiente. Esta política de **degradación controlada** es deliberada: una ecuación mal configurada por el usuario invalida su propia magnitud derivada, pero no aborta la transacción de ingesta ni compromete la persistencia de las magnitudes medidas, que constituyen el dato primario e irrecuperable del experimento. Una excepción propagada habría descartado la totalidad del envío por causa de un error de configuración ajeno a los datos.

En aras del rigor, se documentan dos limitaciones del intérprete. En primer lugar, la gramática soportada es intencionalmente mínima: no contempla el operador de potenciación ni funciones trascendentes —logaritmo, exponencial, funciones trigonométricas—, lo cual es suficiente para los modelos de regresión lineal del presente trabajo pero restringiría la incorporación de modelos no lineales; su extensión requeriría añadir el reconocimiento de identificadores seguidos de paréntesis como llamadas a función y una tabla blanca de funciones matemáticas autorizadas, preservando el principio de gramática cerrada.

En segundo lugar, la técnica del cero implícito para el signo menos unario es correcta cuando este aparece al inicio de la expresión o tras un paréntesis de apertura, pero produce una interpretación errónea cuando sucede a un operador de precedencia superior: la expresión `2*-3` se reescribe como `2*0-3` y se evalúa como −3 en lugar de −6, por cuanto el cero insertado queda asociado al operador de mayor precedencia. La ecuación de calibración del presente trabajo no se ve afectada, dado que su signo negativo encabeza la expresión y el operador siguiente posee precedencia igual. No obstante, la práctica recomendada —y la adoptada en la configuración definitiva del sistema— consiste en expresar el modelo en su forma parametrizada `b0 + b1 * delta_nivel`, declarando los coeficientes en el atributo `parameters` como `{"b0": -3.595, "b1": 11.221}`. Esta forma elimina por completo el problema, puesto que los valores negativos ingresan al evaluador como datos del contexto y no como unidades léxicas de la expresión; es además la única que permite recalibrar el modelo sin reescribir su estructura simbólica, y la que hace explícito el papel de cada coeficiente ante un lector del registro. Se verificó que ambas formas producen el resultado idéntico de 5,853082.

## 2.13.4 Polling de consultas en el frontend (React)

### 2.13.4.1 Determinación del intervalo y alcance de la actualización automática

La capa de presentación implementa un mecanismo de sondeo periódico (*polling*) para la actualización de los datos críticos sin recarga de la página, requisito derivado de la naturaleza de aplicación de página única del cliente. El intervalo se declara como constante nombrada en el ámbito de módulo de los dos componentes que lo utilizan, práctica que evita la dispersión de valores mágicos y documenta el parámetro en el propio código:

```javascript
// frontend/src/pages/Dashboard.jsx, línea 7
const ALERTS_POLL_INTERVAL_MS = 5000

// frontend/src/pages/Alerts.jsx, línea 7
const POLL_INTERVAL_MS = 5000
```

El intervalo configurado es, por tanto, de **5000 milisegundos (5 segundos)**, equivalente a una frecuencia de sondeo *f*ₛ = 0,2 Hz. El valor se expone además al usuario en la propia interfaz mediante la expresión `{ALERTS_POLL_INTERVAL_MS / 1000}`, de modo que la cadencia de actualización es una propiedad visible del sistema y no un comportamiento oculto.

Es preciso delimitar con exactitud el alcance del mecanismo. El sondeo automático se aplica **exclusivamente al recurso de alertas** (`GET /api/alerts`), por ser el dato cuya obsolescencia acarrea consecuencias operativas: una condición de umbral excedida que el operador no perciba oportunamente. Los restantes recursos siguen estrategias de actualización diferenciadas según su criticidad y su costo: el catálogo de entidades y las últimas mediciones del tablero se recuperan una única vez durante el montaje del componente, y las series destinadas a las representaciones gráficas se refrescan bajo demanda explícita del usuario mediante el control «Actualizar gráficas» de la vista `SensorCharts`. Esta diferenciación es un resultado de diseño y no una omisión: el volumen de las consultas gráficas —hasta 150 registros por variable, según la constante `CHART_LIMIT`, multiplicado por el número de variables declaradas— desaconseja su repetición automática, mientras que las alertas se recuperan acotadas a 20 registros en el tablero y a 100 en la vista dedicada. La estrategia sigue así el criterio de asignar el costo de red proporcionalmente al valor operativo de la información y no uniformemente a todos los recursos.

### 2.13.4.2 Implementación mediante el ciclo de vida de React

La programación del temporizador se realiza dentro del gancho `useEffect`, con retorno de la función de limpieza correspondiente:

```javascript
useEffect(() => {
  loadAlerts()
  const interval = setInterval(loadAlerts, ALERTS_POLL_INTERVAL_MS)
  return () => clearInterval(interval)
}, [loadAlerts])
```

Cuatro decisiones de implementación merecen comentario técnico. Primera, la invocación directa de `loadAlerts()` antes de programar el temporizador evita que el usuario deba esperar un ciclo completo de 5 segundos para visualizar los datos iniciales, latencia que resultaría perceptible como un fallo de carga. Segunda, la función de limpieza que ejecuta `clearInterval()` se invoca automáticamente cuando el componente se desmonta —al navegar hacia otra ruta del enrutador— y antes de cada reejecución del efecto, lo que impide la acumulación de temporizadores concurrentes y la consiguiente fuga de memoria y multiplicación no controlada de peticiones; su omisión constituye uno de los defectos más frecuentes en aplicaciones React con sondeo. Tercera, la función `loadAlerts` se estabiliza mediante el gancho `useCallback`, de modo que el arreglo de dependencias del efecto conserva identidad referencial entre renderizados y el temporizador no se recrea de forma espuria en cada actualización del estado. Cuarta, la cadena de promesas incorpora la cláusula `.catch(() => {})`, que absorbe los fallos transitorios de red: una interrupción momentánea de la conectividad no interrumpe el ciclo de sondeo ni satura la interfaz con mensajes de error recurrentes, y la sesión se recupera automáticamente en la iteración siguiente sin intervención del usuario.

La totalidad de las peticiones se canaliza a través del módulo `frontend/src/api/client.js`, que centraliza la resolución de la dirección base —tomada de la variable de entorno `VITE_API_URL` o, en su defecto, del prefijo `/api` servido por el intermediario de Vite—, la serialización automática del cuerpo, la fijación de la cabecera `Content-Type` y la conversión de las respuestas de error en excepciones enriquecidas. Esta centralización constituye un punto único de modificación: alterar la política de reintentos, incorporar autenticación por testigo o instrumentar la medición de latencia son cambios localizados en un solo archivo y no dispersos entre las ocho vistas de la aplicación.

### 2.13.4.3 Justificación técnica de la frecuencia seleccionada

La elección del intervalo obedece a la relación cuantitativa entre la cadencia con que el histórico se actualiza en el servidor y la cadencia con que el cliente lo consulta, y responde al compromiso entre latencia de visualización y carga inducida sobre la infraestructura.

Sea *T*_ing el periodo de ingesta, esto es, el intervalo entre dos escrituras consecutivas sobre la relación `measurements` provenientes de un mismo cliente emisor. En la instalación evaluada dicho parámetro se configuró en 10 segundos, valor que constituye una propiedad del despliegue y no del software: la plataforma admite cualquier cadencia, y el criterio de dimensionamiento que se expone a continuación es aplicable a cualquier valor de *T*_ing. La frecuencia de generación de datos es entonces *f*_ing = 1 ⁄ *T*_ing ≈ 0,1 Hz.

La frecuencia de sondeo seleccionada, *f*ₛ = 0,2 Hz, satisface la relación:

$$f_s \geq 2 \cdot f_{ing} \quad \Rightarrow \quad 0{,}200 \text{ Hz} \geq 2 \times 0{,}100 \text{ Hz} = 0{,}200 \text{ Hz}$$

Se cumple así el criterio de muestreo de Nyquist-Shannon, trasladado del dominio del procesamiento de señales al de la sincronización cliente-servidor: al consultar a una frecuencia igual o superior al doble de la frecuencia de generación del dato, **se garantiza que ningún evento producido en el servidor puede permanecer inadvertido para el cliente**. La consecuencia práctica es la ausencia de pérdida perceptual de alertas: dado que cada ciclo de ingesta genera a lo sumo un conjunto de eventos y que entre dos ingestas consecutivas transcurren al menos dos ciclos de sondeo, todo evento registrado es recuperado por el cliente antes de que pueda generarse el siguiente. Un intervalo de sondeo superior al periodo de ingesta produciría, por el contrario, un fenómeno análogo al solapamiento espectral (*aliasing*), en el cual dos eventos consecutivos se presentarían al operador de forma indistinguible dentro de una misma actualización.

**Tabla 7.** Análisis de latencia extremo a extremo de la plataforma

| Parámetro | Expresión | Valor en la instalación evaluada |
|---|---|---|
| Periodo de ingesta configurado | *T*_ing | 10,00 s |
| Periodo de sondeo del cliente | `ALERTS_POLL_INTERVAL_MS` | 5,00 s |
| Factor de sobremuestreo | *T*_ing ⁄ *T*ₛ | 2,00 |
| Latencia de visualización en el peor caso | *T*ₛ | 5,00 s |
| Latencia de visualización promedio | *T*ₛ ⁄ 2 | 2,50 s |
| Latencia extremo a extremo en el peor caso | *T*_ing + *T*ₛ | 15,00 s |

Fuente: elaboración propia.

Corresponde precisar que el factor de sobremuestreo de 2,00 constituye el límite estricto del criterio. Puesto que el periodo de ingesta efectivo tiende a ser marginalmente superior a su valor nominal —por adición de la latencia de red y del tiempo de proceso del cliente emisor—, el margen real es ligeramente favorable. No obstante, ante despliegues con cadencias de ingesta más exigentes se recomienda preservar un factor de sobremuestreo no inferior a dos, ajustando la constante de intervalo en consecuencia.

### 2.13.4.4 Evaluación de la carga inducida sobre el servidor

El razonamiento inverso justifica el límite inferior del intervalo, esto es, por qué no se seleccionó una frecuencia mayor. Cada ciclo de sondeo genera una petición HTTP a `GET /api/alerts`, que se traduce en una consulta SQL con reunión sobre las relaciones `alerts`, `sensors` y `sensor_variables`. La carga por cliente conectado asciende a:

$$C = \frac{3600 \text{ s/h}}{5 \text{ s}} = 720 \text{ peticiones por hora y por pestaña activa}$$

frente a las 360 peticiones por hora que genera la ingesta con *T*_ing = 10 s. Reducir el intervalo a 1 segundo quintuplicaría dicha carga hasta 3600 peticiones por hora **sin aportar información adicional alguna**, puesto que entre dos escrituras consecutivas el estado del recurso permanece invariante: aproximadamente el 90 % de esas peticiones retornaría un conjunto de resultados idéntico al inmediatamente anterior. La carga crece además linealmente con el número de pestañas y de operadores conectados, factor multiplicativo que un intervalo agresivo amplificaría proporcionalmente.

Esta consideración resulta particularmente relevante en la arquitectura evaluada, en la que la capa de servicios se ejecuta sobre el servidor embebido de PHP, de naturaleza monoproceso y por tanto sin capacidad de atención concurrente: las peticiones se serializan y una consulta lenta bloquea a las siguientes. Un sondeo agresivo saturaría el proceso y podría inducir el rechazo o la demora de las propias peticiones de ingesta, comprometiendo el dato primario e irrecuperable del experimento en beneficio de una mejora meramente cosmética de la interfaz. El intervalo de 5 segundos representa, en consecuencia, el punto de equilibrio óptimo: el mayor periodo que continúa satisfaciendo el criterio de Nyquist respecto de la cadencia de ingesta, minimizando simultáneamente el número de transacciones redundantes.

### 2.13.4.5 Consideraciones sobre la evolución del mecanismo

Se reconocen tres limitaciones del enfoque adoptado, que se consignan como líneas de trabajo futuro. La primera es que el sondeo por intervalo fijo continúa ejecutándose aunque la pestaña del navegador se encuentre en segundo plano; su mitigación consistiría en suspender el temporizador mediante la propiedad `document.visibilityState` y el evento `visibilitychange`, eliminando íntegramente el tráfico generado por sesiones no observadas. La segunda es que cada ciclo transfiere la representación completa del recurso aunque su contenido no haya variado; el empleo de validadores condicionales de HTTP —cabeceras `ETag` e `If-None-Match`— permitiría responder con `304 Not Modified` y suprimir la transferencia del cuerpo, conservando el mismo patrón de peticiones con una fracción del ancho de banda.

La tercera y más significativa es que el paradigma de sondeo resulta intrínsecamente subóptimo frente a los mecanismos de notificación iniciada por el servidor —eventos enviados por el servidor (*Server-Sent Events*) o *WebSockets*—, los cuales reducirían la latencia media de 2,5 s a un valor próximo a cero y suprimirían la totalidad de las peticiones redundantes. Su adopción fue descartada en la presente iteración por resultar incompatible con el modelo de ejecución monoproceso del servidor embebido utilizado durante la fase experimental, que no admite conexiones persistentes sin agotar su única unidad de atención; su implementación requeriría la migración a un servidor de aplicaciones con soporte para concurrencia, decisión que excede el alcance del presente trabajo y que se documenta como recomendación para el escalamiento de la plataforma.
