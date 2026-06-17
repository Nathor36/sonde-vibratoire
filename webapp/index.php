<?php
session_start();                               
if (!isset($_SESSION['user'])) {               
    header('Location: login.php'); exit;       
}
require_once 'db.php';                         
$derniere = getDB()                            
    ->query('SELECT * FROM measures ORDER BY id_measure DESC LIMIT 1') 
    ->fetch();                                 
$mesures  = getDB()                            // Récupère les 20 dernières mesures pour le tableau historique
    ->query('SELECT * FROM measures ORDER BY id_measure DESC LIMIT 20') // Trie du plus récent au plus ancien, limite à 20 lignes
    ->fetchAll();                              // Retourne un tableau de tableaux associatifs (toutes les lignes)
?>
<!DOCTYPE html>                                
<html lang="fr">                              
<head>
    <meta charset="UTF-8">                     
    <meta http-equiv="refresh" content="5">    
    <title>Dashboard</title>                   <!-- Titre affiché dans l'onglet du navigateur -->
    <link rel="stylesheet" href="style.css">   <!-- Charge la feuille de style partagée avec login.php -->
</head>
<body>                                         
<div class="header">                        
    <h1>Sonde de vibration – ArcelorMittal</h1>
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
<table>                                        
    <thead>                                    
        <tr>                                   
            <th>#</th>                         
            <th>Date / Heure</th>              
            <th>RMS (g)</th>                   
            <th>Axe X</th>                     
            <th>Axe Y</th>                     
            <th>Axe Z</th>                     
            <th>Etat</th>                      
        </tr>
    </thead>                                   
    <tbody>                                    <!-- Corps du tableau : contient les lignes de données -->
    <?php foreach ($mesures as $m): ?>         <!-- Boucle sur chacune des 20 mesures récupérées en base -->
        <tr>                                   <!-- Une ligne par mesure -->
            <td><?= $m['id_measure'] ?></td>   <!-- Affiche l'ID unique de la mesure (clé primaire AUTO_INCREMENT) -->
            <td><?= $m['date_measure'] ?></td> <!-- Affiche l'horodatage au format YYYY-MM-DD HH:MM:SS -->
            <td><strong><?= number_format($m['rms'], 4) ?></strong></td> 
            <td><?= number_format($m['axis_x'], 3) ?></td>                 
            <td><?= number_format($m['axis_y'], 3) ?></td>                 
            <td><?= number_format($m['axis_z'], 3) ?></td>                 
            <td><?= $m['threshold_exceeded']                               
                ? '<span class="badge-alerte">Alerte</span>'               
                : '<span class="badge-ok">Normal</span>' ?></td>           <!-- Badge vert si normal (threshold_exceeded = 0) -->
        </tr>                                  
    <?php endforeach; ?>                       
    <?php if (empty($mesures)): ?>             
        <tr><td colspan="7" style="text-align:center;color:gray;padding:20px">Aucune donnee</td></tr> 
    <?php endif; ?>                            
    </tbody>                                   
</table>                                       
</body>                                        
</html>                                        
