
<?php
//Ruta original: https://betadelivery.turbopos.es/api/uber_eats

require "./config.php";
require "./uberController.php";


$servername = "127.0.0.1";
$username = "deliverybeta";
$password = "3_Mrzx62";
$dbname = "deliverybeta";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);
// Verificar la conexión
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
} else {
  echo '<pre>'; print_r($conn); echo '</pre>';
}

/********* Daber que llega en el body de la petición a Uber Eats ************/
$log  = file_get_contents( 'php://input' );
file_put_contents('./log_'.date("j.n.Y").'.log', $log);
/****************************************************************************/

/********* Saber que me llega en $_SERVER desde Uber Eats *******************/
$logHandle = fopen('server_log.txt', 'w'); // 'a' para añadir
// Verificar si el archivo se abrió correctamente
if ($logHandle) {
    // Iterar sobre el array $_SERVER
    foreach ($_SERVER as $key => $value) {
        // Formatear la línea como "clave: valor"
        $logLine = $key . ': ' . $value . PHP_EOL;        
        // Escribir la línea en el archivo de log
        fwrite($logHandle, $logLine);    }    
    // Cerrar el archivo de log
    fclose($logHandle);    
}
/****************************************************************************/

$request_method = $_SERVER['REQUEST_METHOD'];
$route = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// if ($request_method === 'GET' && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/api/uber_eats') {
if ($request_method === 'GET' && $route == 'integraciones') {
  $uberSignHeader = $_SERVER['HTTP_X_UBER_SIGNATURE'];
  if ($uberSignHeader == generarHMAC(file_get_contents('php://input'), $uberSignKey)){
    file_put_contents('./log_'.date("j.n.Y").'.log', PHP_EOL . "VERIFICACION CORRECTA" . PHP_EOL, FILE_APPEND);
    http_response_code(200);
    $uberController->receiveRequest($conn, file_get_contents('php://input'));
  } else{
    file_put_contents('./log_'.date("j.n.Y").'.log', PHP_EOL . "VERIFICACION INCORRECTA" . PHP_EOL, FILE_APPEND);
    http_response_code(401);
  }
}else if ($request_method === 'GET' && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/api/glovo/') {
  http_response_code(403);
}else if ($request_method === 'GET' && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/api/just_eat/') {
  http_response_code(403);
}else{
  http_response_code(404);
}

$conn->close();
exit;
//echo "Conexión exitosa";
// http_response_code(200);

function generarHMAC($body, $secretKey) {
  // Generar el HMAC usando hash_hmac con el algoritmo sha256
  $hmac = hash_hmac('sha256', $body, $secretKey);
  // Convertir el resultado a minúsculas (por si acaso, hash_hmac ya lo hace en minúsculas)
  $hmac = strtolower($hmac);  
  return $hmac;
}