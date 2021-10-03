<?php
	$response = ['status' => 'OK', 'data'=> array()];
	header('Content-type: application/json');

	// Connexion à la DB
	require_once('inc/connexion.inc.php');
    // Requêtes vers la DB
	require_once('inc/database_functions.inc.php');
	$cnn = getConnection();

	// GET ou POST ?
	$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');

	switch ($method)
    {
        case 'GET':
						$headers = getallheaders();
						if(!isset($headers["token"])){
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
							$response['status'] = "Access denied";
							$data['message'] = "Flagged attempt to get access to protected datas, this action has been logged.";
							$response['data'] = $data;
							echo json_encode($response);
							exit();
						}
						else {

						$token = $headers["token"];
            // filter_input retourne NULL si le param n'est pas présent
            $username = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS);
            $id = filter_input(INPUT_GET, 'get_id', FILTER_SANITIZE_SPECIAL_CHARS);

            // Préparation du "statement" de la requête SQL
            $stmt = null;
            if(!is_null($username)) {  // On a le param search
                $stmt = createSearchStmt($cnn, $username, $token);
            }
            else if (!is_null($id)){ // On a le param get_id
                $stmt = createGetIdStmt($cnn, $id, $token);
            }else{
							$sql = 'SELECT tbl_users.id, tbl_users.username, tbl_users.status, tbl_users.token, tbl_users.token_date FROM tbl_token INNER JOIN tbl_users ON tbl_token.num_tblusers = tbl_users.id WHERE tbl_token.token = :token';
							$stmt = $cnn->prepare($sql);
							$stmt->bindValue(':token', $token, PDO::PARAM_STR);
							$stmt->execute();
							$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
							echo json_encode($data);
							exit();
						}

            try{
                $stmt->execute();

                // Si la requête est un get_id=... alors il ne peut y avoir qu'un enregistrement ! donc pas un tableau.
                // Les données lues peuvent directement être introduite dans la structure [data] du json
                if(!is_null($id)) {
                    $found = $stmt->fetch(PDO::FETCH_OBJ); // fetch retourne false si il n'a rien trouvé

                    if(!$found)
                        $response['status'] = "no data found";
                    else
                        $response['data'] = $found;
                }
                else {
                    // ici on doit récuérer toutes les lignes
                    // /!\ fetchAll() peut coûter en temps si il y a bcp de données... on y préférera fetch() avec une boucle
                    $res = $stmt->fetchAll(PDO::FETCH_ASSOC); // Retourne un tableau associatif ou false si rien trouvé

                    // dans le cas de la requête search, on devrait indiquer à l'utilisateur si rien n'a été trouvé
                    if( !is_null($username) && !$res) {
                        $response['status'] = "no data found";
                    }
                    else
                        $response['data'] = $res;
                }
            }
            catch(PDOException $e)
            {
                // En cas d'erreur d'execution, c'est une erreur formatée JSON qui sera retournée
                $response['status'] = "error";
                $response['data'] = "L'accès à la base de données a retourné l'erreur : " . $e->getCode();
            }

            // Envoi de la réponse en json
            echo json_encode($response);
						}
            break;
        case 'POST':
            $json = file_get_contents('php://input');
            $receivedUserInfo = json_decode($json);
            // Idée simple : si on a un id: c'est un update
            //               si on en a pas c'est un Insert d'un nouvel utilisateur

            // A ce stade l'insert et l'update utiliseron les même paramètre, seul la requête différera:
            if(isset($receivedUserInfo->username) && isset($receivedUserInfo->password)) // Dans le cas de cet exemple, si l'id est envoyé, c'est un update
                $sql = 'INSERT INTO tbl_users (username, password) VALUES (:un,:pwd)';

            // Ici il serait important de contrôler les données transmises
            // par l'utilisateur afin de les nettoyer et éviter toute tentative
            // de cross-scripting (XSS). Comme il s'agit d'un exemple pour présenter
            // le fonctionnent en mode api d'un service en php, ce n'est pas fait.

            $stmt = $cnn->prepare($sql);


            if(isset($receivedUserInfo->username) && isset($receivedUserInfo->password)) // Dans le cas de cet exemple, si l'id est envoyé,
            {                                // c'est un update donc on doit bindé l'id
                // Attention il se peut que l'id reçu soit entre guillements et donc être un string
                // on le converti ici en int avec intval()
								
								$stmt->bindValue(':un', strval($receivedUserInfo->username), PDO::PARAM_STR);
								$stmt->bindValue(':pwd', password_hash(strval($receivedUserInfo->password), PASSWORD_DEFAULT), PDO::PARAM_STR);
            }
            try{
                $stmt->execute();
                $response['status'] = "OK";
                $response['data'] = null;
            }
            catch(PDOException $e)
            {
                // En cas d'erreur d'execution, c'est une erreur formatée JSON qui sera retournée
                $response['status'] = "error";
                $response['data'] = $e->getCode();
            }

            // Envoi de la réponse en json
            echo json_encode($response);

            break;
        default:
            // Répondra au navigateur "400 - Bad request"
            http_response_code(400);
            return;
            break;
    }

?>
