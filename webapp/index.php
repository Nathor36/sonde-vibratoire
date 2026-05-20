<?php
session_start();                               // Démarre ou reprend la session PHP pour accéder aux données de connexion
if (!isset($_SESSION['user'])) {               // Si l'utilisateur n'est PAS connecté (pas de session active)
    header('Location: login.php'); exit;       // Redirige vers la page de connexion — protège la page contre les accès non autorisés
}
require_once 'db.php';                         // Charge le fichier de connexion MySQL (fonction getDB())
$derniere = getDB()                            // Récupère la dernière mesure enregistrée (la plus récente)
    ->query('SELECT * FROM measures ORDER BY id_measure DESC LIMIT 1') // Trie par ID décroissant, prend seulement la 1ère ligne
    ->fetch();                                 // Retourne un tableau associatif ou false si la table est vide
$mesures  = getDB()                            // Récupère les 20 dernières mesures pour le tableau historique
    ->query('SELECT * FROM measures ORDER BY id_measure DESC LIMIT 20') // Trie du plus récent au plus ancien, limite à 20 lignes
    ->fetchAll();                              // Retourne un tableau de tableaux associatifs (toutes les lignes)
?>
<!DOCTYPE html>                                <!-- Déclare le type de document HTML5 -->
<html lang="fr">                               <!-- Langue française -->
<head>
    <meta charset="UTF-8">                     <!-- Encodage UTF-8 pour les accents et caractères spéciaux -->
    <meta http-equiv="refresh" content="5">    <!-- IMPORTANT : recharge automatiquement la page toutes les 5 secondes (sans JavaScript) -->
    <title>Dashboard</title>                   <!-- Titre affiché dans l'onglet du navigateur -->
    <link rel="stylesheet" href="style.css">   <!-- Charge la feuille de style partagée avec login.php -->
</head>
<body>                                         <!-- Corps de la page HTML -->
<div class="header">                           <!-- Barre de navigation en haut : titre à gauche, nom+déconnexion à droite (flexbox CSS) -->
    <h1>Sonde de vibration – ArcelorMittal</h1><!-- Titre principal de l'application -->
    <div>Connecte : <strong><?= $_SESSION['user'] ?></strong>  <!-- Affiche le nom de l'utilisateur connecté depuis la session -->
    | <a href="logout.php" class="logout">Deconnexion</a></div><!-- Lien vers logout.php qui détruit la session -->
</div>                                         <!-- Fin de la barre de navigation -->

<?php if ($derniere && $derniere['threshold_exceeded']): ?> <!-- Si une mesure existe ET que le seuil est dépassé (valeur 1 en base) -->
    <div class="alerte-banner">ALERTE ! Le seuil de vibration est depasse !</div> <!-- Bandeau rouge plein écran visible uniquement en cas d'alerte -->
<?php endif; ?>                                <!-- Fin du bloc conditionnel alerte -->

<div class="rms-live">                         <!-- Bloc blanc centré qui affiche la valeur RMS en grand -->
    <p style="color:gray">Valeur RMS en direct</p> <!-- Sous-titre gris au-dessus de la valeur -->
    <div class="rms-valeur <?= ($derniere && $derniere['threshold_exceeded']) ? 'rms-alerte' : 'rms-ok' ?>"> <!-- Applique 'rms-alerte' (rouge clignotant) ou 'rms-ok' (vert) selon le seuil -->
        <?= $derniere ? number_format($derniere['rms'], 4) . ' g' : '-- g' ?> <!-- Affiche le RMS avec 4 décimales ou '--' si aucune donnée -->
    </div>                                     <!-- Fin du bloc valeur RMS -->
    <p style="color:gray"><?= $derniere ? 'Derniere mesure : ' . $derniere['date_measure'] : 'En attente de donnees...' ?></p> <!-- Affiche l'horodatage de la dernière mesure ou un message d'attente -->
</div>                                         <!-- Fin du bloc rms-live -->

<h2 style="margin-bottom:10px">20 dernieres mesures</h2> <!-- Titre de la section tableau historique -->
<table>                                        <!-- Tableau HTML qui affiche l'historique des mesures -->
    <thead>                                    <!-- En-tête du tableau (non répété lors du défilement) -->
        <tr>                                   <!-- Ligne d'en-tête -->
            <th>#</th>                         <!-- Colonne : identifiant unique de la mesure en base -->
            <th>Date / Heure</th>              <!-- Colonne : horodatage automatique enregistré par MySQL -->
            <th>RMS (g)</th>                   <!-- Colonne : valeur RMS globale des 3 axes en g -->
            <th>Axe X</th>                     <!-- Colonne : accélération sur l'axe X en g -->
            <th>Axe Y</th>                     <!-- Colonne : accélération sur l'axe Y en g -->
            <th>Axe Z</th>                     <!-- Colonne : accélération sur l'axe Z en g (≈1g au repos) -->
            <th>Etat</th>                      <!-- Colonne : badge "Normal" ou "Alerte" selon threshold_exceeded -->
        </tr>
    </thead>                                   <!-- Fin de l'en-tête du tableau -->
    <tbody>                                    <!-- Corps du tableau : contient les lignes de données -->
    <?php foreach ($mesures as $m): ?>         <!-- Boucle sur chacune des 20 mesures récupérées en base -->
        <tr>                                   <!-- Une ligne par mesure -->
            <td><?= $m['id_measure'] ?></td>   <!-- Affiche l'ID unique de la mesure (clé primaire AUTO_INCREMENT) -->
            <td><?= $m['date_measure'] ?></td> <!-- Affiche l'horodatage au format YYYY-MM-DD HH:MM:SS -->
            <td><strong><?= number_format($m['rms'], 4) ?></strong></td>   <!-- RMS en gras avec 4 décimales (ex: 0.9821) -->
            <td><?= number_format($m['axis_x'], 3) ?></td>                 <!-- Axe X avec 3 décimales -->
            <td><?= number_format($m['axis_y'], 3) ?></td>                 <!-- Axe Y avec 3 décimales -->
            <td><?= number_format($m['axis_z'], 3) ?></td>                 <!-- Axe Z avec 3 décimales -->
            <td><?= $m['threshold_exceeded']                               <!-- Vérifie si le seuil était dépassé lors de cette mesure -->
                ? '<span class="badge-alerte">Alerte</span>'               <!-- Badge rouge si seuil dépassé (threshold_exceeded = 1) -->
                : '<span class="badge-ok">Normal</span>' ?></td>           <!-- Badge vert si normal (threshold_exceeded = 0) -->
        </tr>                                  <!-- Fin de la ligne de mesure -->
    <?php endforeach; ?>                       <!-- Fin de la boucle foreach sur les mesures -->
    <?php if (empty($mesures)): ?>             <!-- Si le tableau $mesures est vide (aucune donnée en base) -->
        <tr><td colspan="7" style="text-align:center;color:gray;padding:20px">Aucune donnee</td></tr> <!-- Ligne unique sur toute la largeur avec message -->
    <?php endif; ?>                            <!-- Fin du bloc conditionnel "aucune donnée" -->
    </tbody>                                   <!-- Fin du corps du tableau -->
</table>                                       <!-- Fin du tableau HTML -->
</body>                                        <!-- Fin du corps de la page -->
</html>                                        <!-- Fin du document HTML -->
