
<?php
//Ruta original: https://betadelivery.turbopos.es/api/uber_eats
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once "./config.php";
require_once "./UberEatsController.php";
require_once "./GlovoController.php";

$uberEatsController = new UberEatsController($client_id, $client_secret, $conn, $statusList, $typeOrderList);
$glovoController = new glovoController($glovoApiKey, $conn, $statusList, $typeOrderList);

/********* Saber que llega en el body de la petición ************************/
$log  = file_get_contents( 'php://input' );
file_put_contents('./body_log_'.date("d-m-Y").'.txt', date('H:i:s') . " => " . $log . PHP_EOL, FILE_APPEND);
/****************************************************************************/

/********* Saber que llega en $_SERVER **************************************/
$server = json_encode($_SERVER);
file_put_contents('./server_log_'.date("d-m-Y").'.txt', date('H:i:s') . " => " . $server . PHP_EOL, FILE_APPEND);
/****************************************************************************/

$request_method = $_SERVER['REQUEST_METHOD'];
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/integraciones'; // Definir la parte base que deseas excluir
if (isset(parse_url($_SERVER['REQUEST_URI'])['query'])) {
  parse_str(parse_url($_SERVER['REQUEST_URI'])['query'], $queryParams);
}


// Verificar y eliminar la parte base del inicio si existe
if (substr($route, 0, strlen($base_path)) == $base_path) {
    $relative_path = substr($route, strlen($base_path));
} else {
    $relative_path = $route;
}

if ($request_method === 'POST' && $relative_path == '/api/uber_eats') {
  $uberSignHeader = $_SERVER['HTTP_X_UBER_SIGNATURE'];
  if ($uberSignHeader == generarHMAC(file_get_contents('php://input'), $uberSignKey)){
    http_response_code(200);
    $uberEatsController->receiveRequest(file_get_contents('php://input'));
  } else{
    http_response_code(401);
  }

} else if ($request_method === 'GET' && strstr($relative_path,  '/api/orders')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $shopId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $response = getOrdersByShopId($shopId, $accessToken);
  echo json_encode($response['data']);
  http_response_code($response['status']);

} else if ($request_method === 'POST' && strstr($relative_path,  '/api/orders/acceptOrder')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $orderId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = acceptOrder($orderId, $shopId, $accessToken);
  echo $response['data'];
  http_response_code($response['status']);

} else if ($request_method === 'POST' && strstr($relative_path,  '/api/orders/denyOrder')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $orderId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = denyOrder($orderId, $shopId, $accessToken);
  echo $response['data'];
  http_response_code($response['status']);

} else if ($request_method === 'POST' && strstr($relative_path,  '/api/orders/cancelOrder')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $orderId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = cancelOrder($orderId, $shopId, $accessToken);
  echo $response['data'];
  http_response_code($response['status']);

} else if ($request_method === 'GET' && strstr($relative_path,  '/api/getCancelledOrders')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = getCancelledOrders($shopId, $accessToken);
  echo json_encode($response['data']);
  http_response_code($response['status']);

} else if ($request_method === 'POST' && strstr($relative_path,  '/api/orders/markCancelledAsNotified')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $orderId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = markCancelledAsNotified($orderId, $shopId, $accessToken);
  echo $response['data'];
  http_response_code($response['status']);

} else if ($request_method === 'POST' && strstr($relative_path,  '/api/orders/orderReady')) {
  $pathParts = explode('/', trim($relative_path, '/'));
  $orderId = end($pathParts);
  $accessToken = isset($queryParams['access_token']) ? $queryParams['access_token'] : null;
  $shopId = isset($queryParams['shop_id']) ? $queryParams['shop_id'] : null;
  $response = markOderReady($orderId, $shopId, $accessToken);
  echo $response['data'];
  http_response_code($response['status']);

} else if ($request_method === 'GET' && $relative_path === '/api/glovo/dispatched') {
  if (getallheaders()["Authorization"] != $glovoApiKey){
    echo json_encode(['message' => 'No estás uatorizado']);
    http_response_code(401);
  } else {
    http_response_code(200);
    $glovoController->receiveRequest(file_get_contents( 'php://input' ));
  }
} else if ($request_method === 'GET' && $relative_path === '/api/glovo/cancelled') {
  if (getallheaders()["Authorization"] != $glovoApiKey){
    echo json_encode(['message' => 'No estás uatorizado']);
    http_response_code(401);
  } else {
    http_response_code(200);
    $glovoController->cancelOrder(file_get_contents( 'php://input' ));
  }
} else if ($request_method === 'GET' && $relative_path === '/api/just_eat') {
  http_response_code(403);
} else{
  http_response_code(404);
}

$conn->close();
exit;

function generarHMAC($body, $secretKey) {
  // Generar el HMAC usando hash_hmac con el algoritmo sha256
  $hmac = hash_hmac('sha256', $body, $secretKey);
  // Convertir el resultado a minúsculas (por si acaso, hash_hmac ya lo hace en minúsculas)
  $hmac = strtolower($hmac);  
  return $hmac;
}

function getOrdersByShopId($shopId, $token) {
  global $conn;
    if (!isAuth($shopId, $token)) {
      return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
    }

    $stmt_orders = $conn->prepare(
      'SELECT po.*
        FROM PlatformOrder po
        JOIN Config c ON po.storePlatformId = c.uberStoreId 
            OR po.storePlatformId = c.glovoStoreId
            OR po.storePlatformId = c.justEatStoreId
      WHERE c.shopId = ? AND status = 1;'
      );

    $stmt_orders->bind_param('s', $shopId);
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();

    $data = array();
    while ($order = $result_orders->fetch_assoc()) {
      $order['orderItems'] = getOrderItemsByOrderId($order['id']);
      $data[] = $order;
    }

    $stmt_orders->close();
    return (Array('status' => 200, 'data' => $data, 'message' => 'Órdenes recibidas correctamente'));
}

function getOrderItemsByOrderId($orderId) {
  global $conn;
  $stmt = $conn->prepare('SELECT * FROM PlatformOrderItem WHERE orderId = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();

  $data = array();
  while ($item = $result->fetch_assoc()) {
      $data[] = $item;
  }

  $stmt->close();
  return $data;
}

function acceptOrder($orderId, $shopId, $accesToken) {
  global $conn, $uberEatsController;
  if (!isAuth($shopId, $accesToken)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('SELECT * FROM PlatformOrder WHERE id = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $platform = $data['deliveryPlatform'];

    if ($platform == 'UBER_EATS') {
      global $uberEatsController;
      return $uberEatsController->acceptOrder($orderId);      

    } else if ($platform == 'GLOVO'){
      //TODO Petición a Glovo para aceptar orden
    } else if ($platform == 'JUST_EAT'){
      //TODO Petición a Just-eat para aceptar orden
    }
  } else {
    return (Array('status' => 404, 'data' => null, 'message' => 'Orden no encontrada'));
  }  
}

function denyOrder($orderId, $shopId, $token) {
  global $conn, $uberEatsController;
  if (!isAuth($shopId, $token)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('SELECT * FROM PlatformOrder WHERE id = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();
  $stmt->close();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $platform = $data['deliveryPlatform'];

    if ($platform == 'UBER_EATS') {
      return $uberEatsController->denyOrder($orderId);
    } else if ($platform == 'GLOVO'){
      //TODO Petición a Glovo para denegar orden
    } else if ($platform == 'JUST_EAT'){
      //TODO Petición a Just-eat para denegar orden
    }
  } 
}

function cancelOrder($orderId, $shopId, $token) {
  global $conn, $uberEatsController;
  if (!isAuth($shopId, $token)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('SELECT * FROM PlatformOrder WHERE id = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();
  $stmt->close();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $platform = $data['deliveryPlatform'];

    if ($platform == 'UBER_EATS') {
      return $uberEatsController->cancelOrder($orderId);
    } else if ($platform == 'GLOVO'){
      //TODO Petición a Glovo para denegar orden
    } else if ($platform == 'JUST_EAT'){
      //TODO Petición a Just-eat para denegar orden
    }
  }
}

function markCancelledAsNotified($orderId, $shopId, $accessToken){
  global $conn;
  if (!isAuth($shopId, $accessToken)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('UPDATE CancelledOrders SET notified = 1 WHERE orderId = ?');
  $stmt->bind_param('s', $orderId);
  $result = $stmt->execute();
  $rowsAffected = $stmt->affected_rows;
  $stmt->close();
  if ($result && $rowsAffected > 0) {
    return (Array('status' => 200, 'data' => json_encode(['message' => 'Orden marcada como notificada'])));
  } else {
    return (Array('status' => 404, 'data' => json_encode(['message' => 'Error al marcar la orden como notificada'])));
  }
}

function markOderReady($orderId, $shopId, $token) {
  global $conn, $uberEatsController;
  if (!isAuth($shopId, $token)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('SELECT * FROM PlatformOrder WHERE id = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();
  $stmt->close();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $platform = $data['deliveryPlatform'];

    if ($platform == 'UBER_EATS') {
      return $uberEatsController->markOderReady($orderId);
    } else if ($platform == 'GLOVO'){
      //TODO Petición a Glovo para denegar orden
    } else if ($platform == 'JUST_EAT'){
      //TODO Petición a Just-eat para denegar orden
    }
  } else {
    return (Array('status' => 404, 'data' => null, 'message' => 'Orden no encontrada'));
  }
}
  
function isAuth($shopId, $token) {
  global $conn;
  $stmt = $conn->prepare('SELECT * FROM Config WHERE shopId = ?');
  $stmt->bind_param('s', $shopId);
  $stmt->execute();
  $result = $stmt->get_result();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    if ($token == $data['authToken']) {
      return true;
    }      
  }
  $stmt->close();
  return false;
}

function getCancelledOrders($shopId, $accesToken){
  global $conn;
  if (!isAuth($shopId, $accesToken)) {
    return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
  }
  $stmt = $conn->prepare('SELECT * FROM CancelledOrders WHERE notified = 0');
  $stmt->execute();
  $result = $stmt->get_result();

  $data = array();
  while ($order = $result->fetch_assoc()) {
    $data[] = $order['orderId'];
  }

  $stmt->close();
  return (Array('status' => 200, 'data' => $data, 'message' => 'Órdenes canceladas recibidas correctamente'));
}