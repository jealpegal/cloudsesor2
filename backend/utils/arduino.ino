#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>

// Backend en esta máquina de desarrollo (misma WiFi que el NodeMCU).
// IP LAN actual de este PC: 192.168.101.6 — si el router te asigna otra, ejecuta: hostname -I
//
// ——————— Wi‑Fi (misma subred que 192.168.101.x) ———————
const char* ssid     = "TU_WIFI_SSID";
const char* password = "TU_WIFI_PASSWORD";

// ——————— API PHP en este equipo (puerto 8000, ver README) ———————
// Sin barra final. Arranque:  cd backend && php -S 0.0.0.0:8000 router.php
// Desde fuera de la LAN usa tu IP pública y NAT:  http://TU_IP_PUBLICA:8000
const char* SERVER_BASE = "http://192.168.101.6:8000";

// Llave del sensor (api_key en la BD); cambia si creaste otra
const char* SENSOR_API_KEY = "abc123";

// Nombres = variables tipo "measure" del sensor en la app
const char* VAR_NIVEL       = "nivel";
const char* VAR_TEMPERATURA = "temperatura";

const unsigned long INTERVAL_MS = 10000;

// ——————— Ultrasonido HC-SR04 ———————
const uint8_t trigPin = D4;  // GPIO2
const uint8_t echoPin = D3;  // GPIO0

// ——————— Kalman ———————
float Q = 0.02f;    // Varianza del ruido de proceso (ajustar)
float R = 0.5f;     // Varianza del ruido de medición (ajustar)
float P = 1.0f;     // Covarianza inicial de la estimación
float x_est = 0.0f; // Estado inicial estimado (en cm)
// Filtro de Kalman univariante (1D): combina estimación anterior, nueva medición, Q y R.

// ——————— Función de filtro de Kalman ———————
float kalmanFilter(float z_meas) {
  float x_pred = x_est;
  float P_pred = P + Q;
  float K = P_pred / (P_pred + R);
  x_est = x_pred + K * (z_meas - x_pred);
  P     = (1 - K) * P_pred;
  return x_est;
}

// ——————— LM35 en A0 ———————
const float VREF = 2.936f;   // Ajusta según tu medición del ADC
const int ADC_MAX = 1023;
const int NUM_SAMPLES = 30;
const int SAMPLE_DELAY_MS = 10;

WiFiClient wifiClient;

static String normalizarBaseUrl(const char* base) {
  String b = String(base);
  while (b.length() > 0 && b.charAt(b.length() - 1) == '/')
    b.remove(b.length() - 1);
  return b;
}

float readAvgRaw() {
  analogRead(A0);
  delay(2);
  long s = 0;
  for (int i = 0; i < NUM_SAMPLES; i++) {
    s += analogRead(A0);
    delay(SAMPLE_DELAY_MS);
  }
  return s / (float)NUM_SAMPLES;
}

/**
 * GET http://IP:puerto/api/data/ingest?key=...&nivel=...&temperatura=...
 * (Misma API que en docs/API_INGEST_GET.md)
 */
bool enviarAlBackend(float nivel_cm, bool nivel_ok, float temp_c) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi: sin conexión.");
    return false;
  }

  String base = normalizarBaseUrl(SERVER_BASE);
  String url = base + "/api/data/ingest?key=" + String(SENSOR_API_KEY);
  if (nivel_ok) {
    url += "&";
    url += VAR_NIVEL;
    url += "=";
    url += String(nivel_cm, 3);
  }
  url += "&";
  url += VAR_TEMPERATURA;
  url += "=";
  url += String(temp_c, 3);

  HTTPClient http;
  http.setTimeout(30000);
  http.setUserAgent("CloudSensor-ESP8266/1");
  http.addHeader("Connection", "close");
  http.begin(wifiClient, url);

  int code = http.GET();
  if (code > 0) {
    Serial.printf("HTTP %d: ", code);
    Serial.println(http.getString());
  } else {
    Serial.printf("Error HTTP: %s | heap: %u\n",
                  http.errorToString(code).c_str(),
                  (unsigned)ESP.getFreeHeap());
    Serial.printf("URL base usada: %s (en PC: ss -tlnp | grep 8000 → debe ser 0.0.0.0:8000)\n", base.c_str());
  }
  http.end();
  return code == 201 || code == 200;
}

void setup() {
  Serial.begin(115200);
  delay(100);
  Serial.println("\n\n=== INICIO NODEMCU (CloudSensor, IP pública HTTP) ===");

  pinMode(trigPin, OUTPUT);
  pinMode(echoPin, INPUT);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 25000) {
    delay(500);
    Serial.print('.');
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\nWiFi OK. IP local del NodeMCU: %s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("\nWiFi: no conectado.");
  }

  delay(200);
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);
  long dur0 = pulseIn(echoPin, HIGH, 30000);
  if (dur0 > 0) {
    x_est = (dur0 * 0.0343f) / 2.0f;
    Serial.printf("Inicializando Kalman en %.1f cm\n", x_est);
  }

  Serial.printf("VREF LM35: %.3f V\n", VREF);
  Serial.printf("Ingest: %s/api/data/ingest\n", normalizarBaseUrl(SERVER_BASE).c_str());
  Serial.println("PC: usa  php -S 0.0.0.0:8000 router.php  (si usas 127.0.0.1 el ESP no conecta).");
  Serial.println("Setup completado.\n");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    delay(2000);
    return;
  }

  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duracion = pulseIn(echoPin, HIGH, 30000);
  bool nivel_ok = false;
  float nivel_cm = 0.0f;

  if (duracion == 0) {
    Serial.println("Sin eco (fuera de rango).");
  } else {
    float distancia = (duracion * 0.0343f) / 2.0f;
    nivel_cm = kalmanFilter(distancia);
    nivel_ok = true;
    Serial.printf("Nivel (Kalman): %.2f cm\n", nivel_cm);
  }

  float raw = readAvgRaw();
  float volt = (raw / (float)ADC_MAX) * VREF;
  float tempC = volt * 100.0f;
  Serial.printf("Temperatura: %.2f C\n", tempC);

  enviarAlBackend(nivel_cm, nivel_ok, tempC);

  delay(INTERVAL_MS);
}
