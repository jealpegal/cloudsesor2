/**
 * Ejemplo de envío de datos desde NodeMCU (ESP8266) al backend PHP.
 * Requiere: ESP8266 core para Arduino IDE, WiFi y ArduinoJson v6.
 *
 * Túnel Pinggy (u otro): la URL pública suele ser HTTPS.
 * Este sketch usa WiFiClientSecure + setInsecure() solo para pruebas (no valida
 * el certificado). En producción conviene validar certificado o usar red local.
 *
 * IMPORTANTE: la URL debe terminar en /api/data (no solo el host del túnel).
 *
 * Configuración:
 * - SSID y PASSWORD de tu WiFi
 * - SERVER_URL: URL completa del POST (host Pinggy + /api/data)
 * - SENSOR_API_KEY: misma llave (api_key) que el sensor en la BD / frontend
 *
 * El JSON debe coincidir con los nombres de variables del sensor en la BD.
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

// ========== CONFIGURAR ==========
const char* WIFI_SSID     = "TU_WIFI_SSID";
const char* WIFI_PASSWORD = "TU_WIFI_PASSWORD";

// Host Pinggy sin barra final + ruta de la API (obligatorio /api/data)
const char* SERVER_URL    = "https://TU_SUBDOMINIO.run.pinggy-free.link/api/data";

// Llave del sensor (columna api_key en la BD; la defines al crear/editar el sensor)
const char* SENSOR_API_KEY = "abc123";

// Intervalo entre envíos (ms)
const unsigned long INTERVAL_MS = 15000;

unsigned long lastSend = 0;

void setup() {
  Serial.begin(115200);
  delay(100);
  Serial.println("\n--- CloudSensor NodeMCU ---");

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi conectado");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    delay(1000);
    return;
  }

  if (millis() - lastSend >= INTERVAL_MS) {
    lastSend = millis();
    sendSensorData();
  }

  delay(100);
}

/**
 * POST /api/data
 * Body: { "key": "abc123", "values": { "nivel": ..., "temperatura": ... } }
 * Respuesta correcta (201): incluye "saved_measured": ["nivel", "temperatura", ...]
 * Si ves "hint": "La API está bajo /api/…" → la URL no lleva /api/data
 */
void sendSensorData() {
  float nivel       = 10.0f + (float)(millis() % 50) / 10.0f;
  float temperatura = 25.0f + (float)(millis() % 200) / 10.0f;

  StaticJsonDocument<256> doc;
  doc["key"] = SENSOR_API_KEY;

  JsonObject values = doc.createNestedObject("values");
  values["nivel"]       = nivel;
  values["temperatura"] = temperatura;

  String payload;
  serializeJson(doc, payload);

  Serial.print("POST ");
  Serial.println(SERVER_URL);
  Serial.println(payload);

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.begin(client, SERVER_URL);
  http.addHeader("Content-Type", "application/json");
  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    String response = http.getString();
    Serial.print("Response ");
    Serial.print(httpCode);
    Serial.print(": ");
    Serial.println(response);
    if (response.indexOf("saved_measured") >= 0) {
      Serial.println("OK: datos guardados.");
    } else if (response.indexOf("\"hint\"") >= 0) {
      Serial.println("ERROR: falta /api/data en SERVER_URL.");
    }
  } else {
    Serial.print("Error: ");
    Serial.println(http.errorToString(httpCode));
  }

  http.end();
}
