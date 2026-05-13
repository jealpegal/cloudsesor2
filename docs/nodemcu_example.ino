/**
 * Ejemplo de envío de datos desde NodeMCU (ESP8266) al backend PHP.
 * Requiere: ESP8266 core para Arduino IDE y WiFi.
 *
 * Configuración:
 * - SSID y PASSWORD de tu WiFi
 * - URL del servidor (IP o dominio donde corre el backend)
 *
 * El JSON enviado debe coincidir con los nombres de variables definidos
 * en el sensor en la base de datos (ej: "nivel", "temperatura").
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <ArduinoJson.h>

// ========== CONFIGURAR ==========
const char* WIFI_SSID     = "TU_WIFI_SSID";
const char* WIFI_PASSWORD = "TU_WIFI_PASSWORD";
const char* SERVER_URL    = "http://192.168.1.100:8000/api/data";  // IP de tu servidor PHP
const int   SENSOR_ID     = 1;  // ID del sensor en la BD (creado desde el frontend)

// Intervalo entre envíos (ms)
const unsigned long INTERVAL_MS = 15000;

unsigned long lastSend = 0;
WiFiClient client;

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
 * Construye el JSON y hace POST a /api/data
 * Formato: { "sensor_id": 1, "values": { "nivel": 10, "temperatura": 30 } }
 */
void sendSensorData() {
  // Simular lecturas (en tu proyecto aquí leerías sensores reales)
  float nivel       = 10.0 + (millis() % 50) / 10.0;   // ej: 10.0 - 15.0
  float temperatura = 25.0 + (millis() % 200) / 10.0;  // ej: 25.0 - 45.0

  // Crear JSON con ArduinoJson (v6)
  StaticJsonDocument<256> doc;
  doc["sensor_id"] = SENSOR_ID;

  JsonObject values = doc.createNestedObject("values");
  values["nivel"]       = nivel;
  values["temperatura"] = temperatura;

  String payload;
  serializeJson(doc, payload);

  Serial.print("POST ");
  Serial.println(SERVER_URL);
  Serial.println(payload);

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
  } else {
    Serial.print("Error: ");
    Serial.println(http.errorToString(httpCode));
  }

  http.end();
}
