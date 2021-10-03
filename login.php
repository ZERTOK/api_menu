<?php
	$response = ['status' => 'OK', 'data'=> array()];
	header('Content-type: application/json');
	require_once('inc/connexion.inc.php');
	require_once('inc/database_functions.inc.php');
	$cnn = getConnection();
	$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');

	switch ($method)
    {

        case 'POST':
            $json = file_get_contents('php://input');
            $receivedUserInfo = json_decode($json);
            if(isset($receivedUserInfo->username) && isset($receivedUserInfo->password))
            {

              $sql = 'SELECT * FROM tbl_users WHERE username = :un';
              $stmt = $cnn->prepare($sql);
                $stmt->bindValue(':un', strval($receivedUserInfo->username), PDO::PARAM_STR);
            }
            try{
              $toReturn = array();
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if(password_verify($receivedUserInfo->password, $data[0]["password"]))
                {
                  do {
                    $Verify = array();
                    $token = MakeToken();
                    $sql = 'SELECT token, num_tblusers FROM tbl_token WHERE token = :tk OR num_tblusers = :userid';
                    $stmt = $cnn->prepare($sql);
                    $stmt->bindValue(':tk', $token, PDO::PARAM_STR);
                    $stmt->bindValue(':userid', intval($data[0]["id"]), PDO::PARAM_INT);
                    $stmt->execute();

                    $Verify = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if(!empty($Verify)){ // Renouveler le token si l'utilisateur en a deja un
                      if($Verify[0]["num_tblusers"] == intval($data[0]["id"]))
                      {
                        $sql = "DELETE FROM tbl_token WHERE num_tblusers = :id";
                        $stmt = $cnn->prepare($sql);
                        $stmt->bindValue(':id', intval($data[0]["id"]), PDO::PARAM_INT);
                        $stmt->execute();
                      }
                    }
                  } while (!empty($Verify));
                  $sql = 'INSERT INTO tbl_token (token, num_tblusers, dateCreation) VALUES (:tk, :userid, :dtCreate)';
                  $time = date('Y-m-d:H:i');
                  $stmt = $cnn->prepare($sql);
                  $stmt->bindValue(':tk', $token, PDO::PARAM_STR);
                  $stmt->bindValue(':userid', intval($data[0]["id"]), PDO::PARAM_INT);
                  $stmt->bindValue(':dtCreate', $time, PDO::PARAM_STR);
                  $stmt->execute();
                  $toReturn["token"] = $token;
									$response['status'] = "OK";
	                $response['data'] = $toReturn;
                }
								else {
									$message = array('message' => "Wrong username or password" );
									$response['status'] = "ERROR";
									$response['data'] = $message;
								}

            }

            catch(PDOException $e)
            {
                $response['status'] = "error";
                $response['data'] = $e->getMessage();
            }
            echo json_encode($response);

            break;
        default:
            http_response_code(400);
            return;
            break;
    }
    function MakeToken(){
      $token = "";
      for ($i=0; $i < 16; $i++) {
        do {
          $value = rand(33,126);
          $letter = chr($value);
        } while ($letter == '"');
        $token .= $letter;
      }
      return $token;
    }
?>
