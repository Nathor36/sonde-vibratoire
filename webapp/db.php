<?php
define('DB_HOST', 'localhost');        
define('DB_USER', 'root');             
define('DB_PASS', '9+H6L7vVOR');       
define('DB_NAME', 'Vibration_Sensor'); 

// getDB() — retourne toujours la même connexion PDO (pattern Singleton)
// PDO = PHP Data Objects : couche d'abstraction standard pour accéder aux bases de données en PHP
// Le Singleton évite d'ouvrir plusieurs connexions si getDB() est appelé plusieurs fois dans la même page
function getDB(): PDO {
    static $pdo = null;            // Variable statique : garde sa valeur en mémoire entre les appels de la fonction
    if ($pdo === null) {           // Si la connexion n'a pas encore été créée, on l'initialise
        try {                      // Le bloc try intercepte les erreurs de connexion
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', // DSN : driver + hôte + base + encodage UTF-8
                DB_USER,           
                DB_PASS,           
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Toute erreur SQL lève une exception PHP (facilite le débogage)
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // fetch() retourne un tableau associatif : $row['rms'] au lieu de $row[0]
                    PDO::ATTR_EMULATE_PREPARES   => false,                   // Désactive l'émulation pour utiliser de vraies requêtes préparées (sécurité)
                ]
            );
        } catch (PDOException $e) {// Si la connexion échoue (mauvais mot de passe, MySQL arrêté, etc.)
            die(json_encode(['error' => 'Connexion BDD impossible : ' . $e->getMessage()])); // Arrête le script et retourne un message d'erreur JSON
        }
    }
    return $pdo;                   // Retourne la connexion PDO (créée maintenant ou lors d'un appel précédent)
}
