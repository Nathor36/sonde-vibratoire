// ── BIBLIOTHÈQUES ─────────────────────────────────────────
#include <WiFi.h>              
#include <WiFiClientSecure.h>  // Crée un socket TCP chiffré TLS 1.2 (nécessaire pour MQTTS)
#include <PubSubClient.h>      
#include <Wire.h>              // Protocole I2C : relie l'ESP32 au capteur ADXL345 sur GPIO21/22
#include <Adafruit_Sensor.h>   // Couche Adafruit commune à tous les capteurs (interface unifiée)
#include <Adafruit_ADXL345_U.h>// Pilote spécifique pour le capteur accéléromètre ADXL345
#include "mbedtls/md.h"        // Bibliothèque crypto intégrée à l'ESP32 — fournit HMAC-SHA256

// ── PARAMÈTRES RÉSEAU ET SÉCURITÉ ─────────────────────────
const char* WIFI_SSID  = "BTS CIEL FIBRE 2.4";            
const char* WIFI_PASS  = "L0g4n123*";                     
const char* MQTT_HOST  = "10.1.40.15";                    
const char* MQTT_TOPIC = "vibration/rms";                 
const char* HMAC_KEY   = "ArcelorMittal_SecretKey_2026";  
const int   MQTT_PORT  = 8883;                            
const float SEUIL      = 1.2;                             

// ── CERTIFICAT CA TLS ─────────────────────────────────────
// Certificat de l'autorité de certification ArcelorMittal-CA (auto-signé)
// Permet à l'ESP32 de vérifier l'identité du serveur Mosquitto avant de chiffrer
const char* CA_CERT = R"EOF(
-----BEGIN CERTIFICATE-----
MIIDFzCCAf+gAwIBAgIUMGd2hsVj+cInjy7D/c9dihiCjIYwDQYJKoZIhvcNAQEL
BQAwGzEZMBcGA1UEAwwQQXJjZWxvck1pdHRhbC1DQTAeFw0yNjA0MTQwNzA5MjJa
Fw0zNjA0MTEwNzA5MjJaMBsxGTAXBgNVBAMMEEFyY2Vsb3JNaXR0YWwtQ0EwggEi
MA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCr4VfAPsToHflF/WD55A3fthwW
eLzx2vMKi83C9PlLhSFnQQmM0GUg/cw7T+G5B+nsDoHxuyiRck1Y4hLUQKMJYOFZ
aRSJriDqmDuaQJ5XxacQ73CNJlnfMnmAZLCVeBMebIqhm7myN99xFu8XaZP32LAi
J+yWMJpg8Xbhi+QjF3cFHGWuHqptW+3iqGFtzk7iv0up1qm8JE/toHIIRk9gKqpk
Fb3QEBQ/KajwWHIT1EUnt9zuvGoI9jK+xwNgI2DtxkmAfSu+V/mI+Xp+67aE5qSF
zttJIfUbzQOxj42k68OiYDmnetWdxi0dZUQZdMmJTYpijqdIL6/h8ZgvGcYBAgMB
AAGjUzBRMB0GA1UdDgQWBBRhD7jcuYR0AkdVCYa65rsr1JIimDAfBgNVHSMEGDAW
gBRhD7jcuYR0AkdVCYa65rsr1JIimDAPBgNVHRMBAf8EBTADAQH/MA0GCSqGSIb3
DQEBCwUAA4IBAQB19o2bghBuE0LK3Zki52wj0yS2ZWRfUMCMx/s5KqvTk5Mmui4q
K6eKVE13XLEOaYnMA8tIAirXqcXKYDZv0MitboUSOdtDD1BPCrGGDfcVJmFLMlXa
8IPel8TkJ5Z5KqIAk/sTQOSaSW6WcJq6E4kOMv4WsKLURxxwQMgRN69/cUdomJKc
9tZ9hF5QstOGURp2XR7MmDEg+eo9kWylYQ6WpEvDsvnhttuTPPIgK+oyBe+Uk1Ne
1f8MDduMDNuXL01ENhPw6PlDkQiowDYJJGq97lsYkc1dpsP04sKIAGufxGwLkkU1
mmLtJ/78gj47eqJnce7ru945UdKRC6wIDBPf
-----END CERTIFICATE-----
)EOF";
// Fin du certificat CA — sans ce certificat, la connexion TLS serait refusée

// ── OBJETS GLOBAUX ─────────────────────────────────────────
Adafruit_ADXL345_Unified capteur = Adafruit_ADXL345_Unified(12345); // Crée l'objet capteur ADXL345 (12345 = ID arbitraire)
WiFiClientSecure wifiClient;     // Socket réseau sécurisé TLS — chiffre toutes les données envoyées
PubSubClient mqtt(wifiClient);   // Client MQTT qui passe par le socket TLS (wifiClient) ci-dessus

// ── FONCTION : CONNEXION WIFI ──────────────────────────────
void connecterWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASS);              // Lance la tentative de connexion au réseau WiFi
    while (WiFi.status() != WL_CONNECTED) delay(500); // Attend 500 ms entre chaque vérification de connexion
    Serial.println("WiFi OK : " + WiFi.localIP().toString()); // Affiche l'adresse IP obtenue dans le moniteur série
}

// ── FONCTION : CONNEXION MQTT ──────────────────────────────
void connecterMQTT() {
    while (!mqtt.connected()) {                    // Répète jusqu'à ce que la connexion MQTT soit établie
        if (!mqtt.connect("ESP32_Sonde")) delay(3000); // Si échec : attend 3 s avant de réessayer
    }
    Serial.println("MQTT OK");                     // Confirme la connexion dans le moniteur série
}

// ── FONCTION : SIGNATURE HMAC-SHA256 ──────────────────────
// Calcule une empreinte numérique du message avec la clé secrète
// Si quelqu'un modifie le message en transit, la signature ne correspondra plus
String signer(String msg) {
    byte res[32];                                  // Tableau de 32 octets pour stocker le résultat HMAC (256 bits)
    mbedtls_md_context_t ctx;                      // Structure de contexte pour le calcul HMAC
    mbedtls_md_init(&ctx);                         // Initialise le contexte (met à zéro la mémoire)
    mbedtls_md_setup(&ctx, mbedtls_md_info_from_type(MBEDTLS_MD_SHA256), 1); // Configure SHA-256 en mode HMAC (1)
    mbedtls_md_hmac_starts(&ctx, (unsigned char*)HMAC_KEY, strlen(HMAC_KEY)); // Charge la clé secrète dans le contexte
    mbedtls_md_hmac_update(&ctx, (unsigned char*)msg.c_str(), msg.length());  // Traite le message à signer
    mbedtls_md_hmac_finish(&ctx, res);             
    mbedtls_md_free(&ctx);                         
    String h = "";                                 
    for (int i=0; i<32; i++) {                     // Parcourt les 32 octets du résultat
        if (res[i] < 16) h += "0";                // Ajoute un zéro devant si l'octet est < 0x10 (padding)
        h += String(res[i], HEX);                 // Convertit l'octet en 2 caractères hexadécimaux
    }
    return h;                                      // Retourne la signature : 64 caractères hex (ex: "a3f2...c9")
}

// ── FONCTION : CALCUL RMS ──────────────────────────────────
// RMS = Valeur Quadratique Moyenne sur les 3 axes (X, Y, Z)
// Formule : RMS = sqrt((Σax² + Σay² + Σaz²) / (3 × N)
// ax, ay, az sont passés par référence pour être récupérés dans loop()
float rms(float &ax, float &ay, float &az) {
    float s = 0;                                   
    sensors_event_t e;                             
    for (int i = 0; i < 100; i++) {               
        capteur.getEvent(&e);                      
        ax = e.acceleration.x / 9.81;             
        ay = e.acceleration.y / 9.81;             
        az = e.acceleration.z / 9.81;             
        s += ax*ax + ay*ay + az*az;               
        delay(5);                                  
    }
    return sqrt(s / 100);                          
}

// ── SETUP : S'EXÉCUTE UNE SEULE FOIS AU DÉMARRAGE ─────────
void setup() {
    Serial.begin(115200);                          
    Wire.begin(21, 22);                            
    if (!capteur.begin()) {                        
        Serial.println("ADXL345 non detecte !");   // Si échec : affiche une erreur dans le moniteur série
        while(1);                                  // Bloque le programme (boucle infinie) — impossible de continuer
    }
    capteur.setRange(ADXL345_RANGE_4_G);           // Configure la plage de mesure à ±4g (équilibre précision/portée)
    connecterWiFi();                               
    wifiClient.setCACert(CA_CERT);                 
    mqtt.setServer(MQTT_HOST, MQTT_PORT);          
    mqtt.setBufferSize(768);                       // Augmente le buffer MQTT à 768 octets (payload JSON + HMAC)
    connecterMQTT();                               // Établit la connexion MQTT/TLS avec le broker
}

// ── LOOP : TOURNE EN CONTINU APRÈS SETUP ──────────────────
void loop() {
    if (WiFi.status() != WL_CONNECTED) connecterWiFi(); // Reconnecte le WiFi si la connexion a été perdue
    if (!mqtt.connected()) connecterMQTT();        // Reconnecte le broker MQTT si la connexion a été perdue
    mqtt.loop();                                   // Traite les messages entrants et maintient la connexion MQTT
    float ax, ay, az;                             // Déclare les variables pour stocker les derniers axes lus
    float r = rms(ax, ay, az);                    // Mesure le RMS sur 100 échantillons (500 ms de mesure)
    String json = "{\"rms\":"  + String(r, 4)     // Construit le JSON avec le RMS (4 décimales)
                + ",\"x\":"   + String(ax, 3)     
                + ",\"y\":"   + String(ay, 3)     
                + ",\"z\":"   + String(az, 3)     
                + "}";                             
    String payload = json.substring(0, json.length()-1) // Supprime le dernier "}" pour insérer le champ hmac
                   + ",\"hmac\":\"" + signer(json) + "\"}"; // Ajoute la signature HMAC-SHA256 puis referme le JSON
    Serial.println("RMS=" + String(r,4) + (r >= SEUIL ? " ALERTE!" : " OK")); // Affiche le RMS et l'état dans le moniteur série
    mqtt.publish(MQTT_TOPIC, payload.c_str());     // Publie le payload JSON signé sur le topic MQTT via TLS
    delay(2000);                                   
}
