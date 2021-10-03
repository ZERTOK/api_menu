<?php

function createSearchStmt($connectionToDB, $username, $token) {
  if(VerifyToken($token, $connectionToDB))
  {
    $sql = createSelectAllSQL();
    $sql .= ' WHERE username=:un';

    // préparation du Statement avec la requête ci-dessus.
    $stmt = $connectionToDB->prepare($sql);
    // Utilisation du binding pour setter les valeurs dans la requête SQL
    $stmt->bindValue (':un', $username, PDO::PARAM_STR);
    return $stmt;
  }
  else {
    Fraud();
  }

}

function createGetIdStmt($connectionToDB, $id, $token) {
  if(VerifyToken($token, $connectionToDB))
  {
    $sql = createSelectAllSQL();
    // On ajoute la condition (filtre) à la requête de base (on doit précisé la table car dans notre modèle, la tables
    // cities a aussi un id)
    $sql .= ' WHERE tbl_users.id=:id';

    // préparation du Statement avec la requête ci-dessus.
    $stmt = $connectionToDB->prepare($sql);
    // Utilisation du binding pour setter les valeurs dans la requête SQL
    $stmt->bindValue (':id', $id, PDO::PARAM_INT);
    return $stmt;
  }
  else {
    Fraud();
  }

}
function VerifyToken($token, $cnn){
  $sql ='SELECT token FROM tbl_token WHERE token = :token';
  $stmt = $cnn->prepare($sql);
  $stmt->bindValue(':token', $token, PDO::PARAM_STR);
  $stmt->execute();
  $data = $stmt->fetchAll(PDO::FETCH_OBJ);
  if(!isset($data[0])){
    logFraud($cnn);
    return false;
  }
  else {
    return true;
  }
}
function logFraud($cnn){
  if (!empty($_SERVER['HTTP_CLIENT_IP']))
  {
    $ip_address = $_SERVER['HTTP_CLIENT_IP'];
  }
//whether ip is from proxy
  elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
  {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
//whether ip is from remote address
  else
  {
  $ip_address = $_SERVER['REMOTE_ADDR'];
  }
  $ip = $ip_address;
  $sql = 'INSERT INTO tbl_log (levelAlert, ipAdress, message, hackstamp) VALUES (:alert, :ip, :message, :hackstamp)';
  $stmt = $cnn->prepare($sql);
  $date = date('Y-m-d:H:i:s');
  $stmt->bindValue (':alert', "WARNING", PDO::PARAM_STR);
  $stmt->bindValue (':ip', $ip, PDO::PARAM_STR);
  $stmt->bindValue (':message', $ip . " attempted to fetch data from the DATABASE without a token", PDO::PARAM_STR);
  $stmt->bindValue (':hackstamp', $date, PDO::PARAM_STR);
  $stmt->execute();
}
/**
 * Cette fonction retourne la commande de de SELECT pour tous les enregistrements
 * @return string Commande SELECT ...
 */
 function Fraud(){
   $data = array('message' => 'Fake token has been detected, this acton will be logged !');
   $response['status'] = 'Access denied';
   $response['data'] = $data;
   echo json_encode($response);
   exit();
 }
function createSelectAllSQL() {
    return 'SELECT id, username, password, status FROM tbl_users';
}
