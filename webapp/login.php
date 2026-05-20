<?php
session_start();                               
if (isset($_SESSION['user'])) {                
    header('Location: index.php'); exit;       
}
$erreur = '';                                  

if ($_SERVER['REQUEST_METHOD'] == 'POST') {    
    require_once 'db.php';                     
    $req = getDB()->prepare('SELECT * FROM users WHERE emails = ?'); 
    $req->execute([$_POST['email']]);          
    $user = $req->fetch();                     // Récupère la première ligne du résultat (l'utilisateur trouvé ou false si inexistant)
    if ($user && $user['passwords'] === md5($_POST['password'])) { // Vérifie que l'utilisateur existe ET que le hash MD5 du mdp correspond
        $_SESSION['user'] = $user['firstnames'] . ' ' . $user['names']; // Enregistre le nom complet dans la session
        header('Location: index.php'); exit;
    }
    $erreur = 'Email ou mot de passe incorrect.'; // Si identifiants incorrects, prépare le message d'erreur à afficher
}
?>
<!DOCTYPE html>                                
<html lang="fr">                               
<head>
    <meta charset="UTF-8">                     
    <title>Connexion</title>                   
    <link rel="stylesheet" href="style.css">   
</head>
<body class="login-page">                      
<div class="login-box">                        
    <h2>Connexion</h2>                         
    <?php if ($erreur): ?>                     
        <p class="erreur"><?= $erreur ?></p>   
    <?php endif; ?>                            
    <form method="POST">                       
                <label>Email</label>                   
        <input type="email" name="email" required>     
        <label>Mot de passe</label>            
        <input type="password" name="password" required> 
        <button type="submit">Se connecter</button>    
    </form>                                                                            
</body>                                        
</html>                                        
