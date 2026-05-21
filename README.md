Description
Système de surveillance vibratoire des moteurs industriels développé pour ArcelorMittal Centre de Service Reims. L'ESP32 mesure les vibrations via un capteur ADXL345 et calcule une valeur RMS. Les données sont transmises de façon sécurisée au serveur via MQTT over TLS avec signature HMAC. Un dashboard PHP affiche les mesures en temps réel et déclenche une alerte visuelle et email si le seuil de 1,2 g est dépassé.


Contenu du projet
Le dossier ESP32_Sonde_Vibration contient le code Arduino de la sonde (mesure I2C, calcul RMS, chiffrement HMAC, publication MQTT). Le dossier webapp contient le site web PHP avec la page de connexion (login.php), le dashboard de supervision (index.php), la connexion base de données (db.php) et la feuille de style (style.css).


Matériel
ESP32 avec un capteur accéléromètre ADXL345 branché en I2C sur les broches GPIO21 (SDA) et GPIO22 (SCL), alimenté en 3,3 V. Serveur HP ProLiant MicroServer sous Ubuntu 22.04 LTS.
Installation serveur
Installer Mosquitto pour le broker MQTT, MySQL pour la base de données et Apache avec PHP pour le serveur web. Configurer Mosquitto sur le port 8883 avec les certificats TLS. Créer la base de données Vibration_Sensor avec les tables measures et users.


Configuration
Dans le fichier ESP32_Sonde_Vibration.ino, renseigner le nom et mot de passe du réseau WiFi ainsi que l'adresse IP du serveur Mosquitto. Dans db.php, renseigner le mot de passe MySQL.
