<?php
$response = ['status' => 'OK', 'data'=> array()];
header('Content-type: application/json');
require_once('inc/connexion.inc.php');
require_once('inc/database_functions.inc.php');
$cnn = getConnection();
$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
$headers = getallheaders();
$licenses = array();
$json = file_get_contents('php://input');
$receivedUserInfo = json_decode($json);
$ip_address;
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
try {
  switch ($method)
  {
    case 'POST':

    if(!isset($headers['token'])){
      throw new \Exception("No token found. Please log in before trying to create a new license.", 1);
    }
    else {
      $token = $headers['token'];
      $sql = 'SELECT username, tbl_users.token , status FROM tbl_users INNER JOIN tbl_token ON tbl_token.num_tblusers = tbl_users.id WHERE tbl_token.token LIKE :token';
      $stmt = $cnn->prepare($sql);
      $stmt->bindValue(':token', $token, PDO::PARAM_STR);
      $stmt->execute();
      $data = $stmt->fetchAll(PDO::FETCH_OBJ);
      $duration;
      if(!isset($receivedUserInfo->expiration)){
        $license = $receivedUserInfo->license;
        if(isset($receivedUserInfo->license)){
          if(isset($data[0])){
            if($data[0]->token != null){
              throw new \Exception("You already have a token assigned to your account", 1);
            }
            else {
              $stmt = $cnn->prepare('SELECT tbl_users.id FROM tbl_users INNER JOIN tbl_license ON tbl_license.id = tbl_users.token WHERE tbl_license.license = :licenseid');
              $stmt->bindValue(':licenseid', $license, PDO::PARAM_STR);
              $stmt->execute();
              $data = $stmt->fetchAll(PDO::FETCH_OBJ);
              if (isset($data[0])){
                  throw new \Exception("License already redeemed", 1);
                }
            }
            $sql = 'SELECT * FROM tbl_license WHERE license = :license';
            $stmt = $cnn->prepare($sql);
            $stmt->bindValue(':license', $license, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_OBJ);
            if(isset($data[0])){
              $licenseId = $data[0]->id;
              $sql = 'UPDATE tbl_users SET token = :licenseId';
              $stmt = $cnn->prepare($sql);
              $stmt->bindValue(':licenseId', $licenseId, PDO::PARAM_INT);
              $stmt->execute();
              $response['data'] = 'License successfully redeemed';
            }
            else{
              throw new \Exception("Invalid or expired token", 1);

            }
          }
        }
        else {
          throw new \Exception("Duration not set (1w, 1m, 1y, LIFETIME)", 1);
        }

      }
      else {
        $duration = $receivedUserInfo->expiration;
      }
      if(isset($duration)){
        if(!isset($receivedUserInfo->quantity)){
          $quantity = 1;
        }
        else {
          $quantity = $receivedUserInfo->quantity;
        }

        if(isset($data[0])){
          $username = $data[0]->username;
          $permission = $data[0]->status;
          if($permission == 1){
            for ($i=0; $i < $quantity; $i++) {
              $license = "";
              do {
                $ok = false;
                $license = CreateLicense();

                $sql = 'SELECT id FROM tbl_license WHERE license = :license';
                $stmt = $cnn->prepare($sql);
                $stmt->bindValue(':license', $license, PDO::PARAM_STR);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_OBJ);
                if(!isset($data[0])){
                  $ok = true;
                }
              } while (!$ok);
              $sql = 'INSERT INTO tbl_license(license, expireDate) VALUES ';
              switch ($duration) {
                case '1w':
                  $sql .='(:license, DATE_ADD(NOW(), INTERVAL 1 week))';
                  break;
                  case '1m':
                    $sql .='(:license, DATE_ADD(NOW(), INTERVAL 1 month))';
                    break;
                    case '1y':
                      $sql .='(:license, DATE_ADD(NOW(), INTERVAL 1 year))';
                      break;
                      case 'LIFETIME':
                        $sql .='(:license, DATE_ADD(NOW(), INTERVAL 10 year))';
                        break;
                default:
                  throw new \Exception("Unexpected duration", 1);
                  break;
              }

              $stmt = $cnn->prepare($sql);
              $stmt->bindValue(':license', $license, PDO::PARAM_STR);
              $stmt->execute();
              $ready = array('license' => $license);
              array_push($licenses, $ready);
            }
            $response['data'] = $licenses;
            $sql = 'INSERT INTO tbl_log(levelAlert, ipAdress, message) VALUES (:levelAlert, :ipAdress, :message)';
            $stmt = $cnn->prepare($sql);
            $stmt->bindValue(':levelAlert', 'Information', PDO::PARAM_STR);
            $stmt->bindValue(':ipAdress', $ip, PDO::PARAM_STR);
            $msg = "$username created $quantity licenses";
            $stmt->bindValue(':message', $msg , PDO::PARAM_STR);
            $stmt->execute();
          }
          else {
            throw new \Exception("Insufficent permissions", 1);

          }
        }
        else {
          throw new \Exception("Expired or invalid token, this action will be logged !", 1);

        }
      }
      break;

    }
      }

} catch (\Exception $e) {
  $response['status'] = "ERROR";
  $content = array('message' => 'Please log in before trying to create a license' );
  $response['data'] = $e->getMessage();
}
function CreateLicense(){
  $token = "";
  for ($i=0; $i < 16; $i++) {
    do {
      $value = rand(33,126);
      $letter = chr($value);
    } while ($letter == '"');
    if($i%4 == 0 && $i !== 0){
      $token.="-";
    }
    $token .= $letter;
  }
  return $token;
}

echo json_encode($response);
?>
