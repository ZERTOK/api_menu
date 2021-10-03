<?php
require_once('inc/utils.inc.php');
function getConnection()
{
    /* dev */
    $dsn = 'mysql:host=localhost;dbname=zertokdb';
    $utilisateur = 'root';
    $motDePasse = '';


    try {
      $cnn = new PDO( $dsn, $utilisateur,$motDePasse );
      $cnn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $cnn->exec("SET CHARACTER SET utf8");
    } catch ( PDOException $e ) {
        showError($e->getMessage());
    }
    return $cnn;
}
?>
