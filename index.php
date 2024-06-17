
<?php
//Ruta original: https://betadelivery.turbopos.es/api/uber_eats

require_once "./config.php";
require_once "./uberController.php";

/********* Daber que llega en el body de la petición a Uber Eats ************/
$log  = file_get_contents( 'php://input' );
file_put_contents('./body_log_'.date("j.n.Y-h.m.s").'.json', $log);
/****************************************************************************/

$server = json_encode($_SERVER);
file_put_contents('./server_log_'.date("j.n.Y").'.json', $server);

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


file_put_contents('test.txt', 'route: ' . $route . ', relative_path: "' . $relative_path . '"');

if ($request_method === 'POST' && $relative_path == '/api/uber_eats') {
  file_put_contents('test.txt', PHP_EOL . 'Entra en el IF', FILE_APPEND);
  $uberSignHeader = $_SERVER['HTTP_X_UBER_SIGNATURE'];
  if ($uberSignHeader == generarHMAC(file_get_contents('php://input'), $uberSignKey)){
    http_response_code(200);
    receiveRequest(file_get_contents('php://input'));
  } else{
    http_response_code(401);
  }
//TODO empezar a pedir orders por shopId
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
  $response = acceptOrder($orderId);
  echo $response['data'];
  http_response_code($response['status']);

}else if ($request_method === 'GET' && $relative_path === '/api/glovo') {
  http_response_code(403);
}else if ($request_method === 'GET' && $relative_path === '/api/just_eat') {
  http_response_code(403);
}else{
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
  $stmt = $conn->prepare('SELECT * FROM config WHERE shopId = ?');
  $stmt->bind_param('s', $shopId);
  $stmt->execute();
  $result = $stmt->get_result();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    if ($token != $data['authToken']) {
      return (Array('status' => 401, 'data' => null, 'message' => 'Token no válido'));
    }
    $stmt->close();

    $stmt_orders = $conn->prepare(
      'SELECT po.*
        FROM PlatformOrder po
        JOIN Config c ON po.storePlatformId = c.uberStoreId 
            OR po.storePlatformId = c.globoStoreId
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

  } else {
      return (Array('status' => 404, 'data' => null, 'message' => 'No existe ninguna shop con shopId ' . $shopId));
  }

  $stmt->close();

}

function getOrderItemsByOrderId($orderId) {
  global $conn;
  $stmt = $conn->prepare('SELECT * FROM platformOrderItem WHERE orderId = ?');
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

function acceptOrder($orderId) {
  global $conn, $token;
  $stmt = $conn->prepare('SELECT * FROM PlatformOrder WHERE id = ?');
  $stmt->bind_param('s', $orderId);
  $stmt->execute();
  $result = $stmt->get_result();

  // Verificar si se obtuvieron resultados
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $platform = $data['deliveryPlatform'];

    if ($platform == 'UBER_EATS') {
      $url = 'https://api.uber.com/v1/eats/orders/' . $orderId . '/accept_pos_order';
      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  // Establecer el método POST
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $token  // Agregar el token de acceso 
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      curl_close($ch);

      // Mostrar la respuesta del servidor
      if(isset(json_decode($response)->message) && json_decode($response)->message == "Order not found") {
        $status = 2;
        $stmt = $conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => 404, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //TODO cambiar el estado de la orden aceptada
      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($httpStatusCode == 204) {
        $status = 3;
        $stmt = $conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => array('message' => 'Orden aceptada correctamente')));
      }
      return (Array('status' => 200, 'data' => $response, 'message' => 'Orden aceptada correctamente'));
    } else if ($platform == 'GLOBO'){
      //TODO Petición a Globo para aceptar orden
    } else if ($platform == 'JUST_EAT'){
      //TODO Petición a Just-eat para aceptar orden
    }
  }
  $stmt->close();
  return (Array('status' => 200, 'data' => array('orderId' => $orderId), 'message' => 'Orden aceptada correctamente'));
}