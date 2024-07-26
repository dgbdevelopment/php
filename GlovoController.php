<?php

class GlovoController {
    private $token;
    private $conn;
    private $statusList;
    private $typeOrderList;

    public function __construct($apiToken, $conn, $statusList, $typeOrderList) {
        $this->token = $apiToken;
        $this->conn = $conn;
        $this->statusList = $statusList;
        $this->typeOrderList = $typeOrderList;
    }

    public function receiveRequest($body) {
      // file_put_contents('./body_log_from_glovo'. date("j.n.Y").'.json', $body);
      $order = json_decode($body);
      $this->performOrderNotification($order);
    }

    public function getToken(){
      return $this->token;
    }

    private function performOrderNotification($order) {     
      $this->buildPlatformOrder($order);
      $this->buildPlatformOrderItem($order);
    }

    private function buildPlatformOrder($order) {
        file_put_contents('LastOrderFromGlovo_log.json', json_encode($order));
        $id = $order->order_id;
        $name = $order->order_code;
        $date = strtotime($order->order_time) * 1000;
        $status = 1;
        $paid = ($order->estimated_total_price) / 100;
        $discount = ($order->partner_discounted_products_total) / 100;
        $clientId = $order->customer->hash;
        $clientName = $order->customer->name;
        $clientPhone = $order->customer->phone_number;
        $storePlatformId = $order->store_id;
        $address = isset($order->delivery_address) ? $order->delivery_address->label : 'No disponible';
        $typeOrder = $order->is_picked_up_by_customer ? 0 : 2;
        $observations = $order->allergy_info . PHP_EOL . $order->special_requirements;
        $deliveryPlatform = 'GLOVO';
        $discountCodes = ''; // Agrega esta línea si es necesario para el parámetro $discountCodes
        $numCommensals = 0; // Agrega esta línea si es necesario para el parámetro $numCommensals

        $query = "INSERT INTO PlatformOrder (id, name, date, status, paid, discount, numCommensals, clientId, clientName, clientPhone, storePlatformId, observations, address, discountCodes, typeOrder, deliveryPlatform) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Preparar y ejecutar la declaración
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sssiddsssssssiss', 
            $id, $name, $date, $status, $paid, $discount, 
            $numCommensals, $clientId, $clientName, $clientPhone, 
            $storePlatformId, $observations, $address, $discountCodes, 
            $typeOrder, $deliveryPlatform
        );

        // Ejecutar la declaración y verificar si se insertó correctamente
        if ($stmt->execute()) {
            file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Glovo Order inserted successfully." . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents('db_log.txt',  date("d/m/Y-H:i:s") . " -> Error inserting Glovo order: " . json_encode($stmt) . PHP_EOL, FILE_APPEND);
        }

        // Cerrar la declaración
        $stmt->close();
    }

    private function buildPlatformOrderItem($order, $products = null, $parentMenuId = null) {
      $itemForStorage = new stdClass();
      if(!isset($products)) $products = $order->products;
      foreach ($products as $item) {
          $itemForStorage->id = $this->generateUUID();
          $itemForStorage->platformItemId = $item->id;
          $itemForStorage->orderId = $order->order_id;
          $itemForStorage->itemName = $item->name;
          $itemForStorage->comment = $order->allergy_info;
          $itemForStorage->quantity = $item->quantity;
          $itemForStorage->price = $item->price / 100;
          $itemForStorage->discount = $item->discount / 100;
          $itemForStorage->date = strtotime($order->order_time) * 1000; 
          
          if(isset($item->sub_products)){
            //Es un menu
            $itemForStorage->isMenu = 1;
          } else {
            //No es un menu
            $itemForStorage->isMenu = 0;
          }

          if(isset($products) && isset($parentMenuId)){
            //Pertenece a un menu
            $itemForStorage->parentMenuId = $parentMenuId;
          } else {
            //No pertenece a un menu
            $itemForStorage->parentMenuId = null;
          }

          $itemForStorage->total = $itemForStorage->price * $itemForStorage->quantity;

          // Preparar la consulta SQL
          $sql = "INSERT INTO PlatformOrderItem (
              id, platformItemId, orderId, itemName, comment, quantity, price, total, date, discount, parentMenuId, isMenu
          ) VALUES (
              ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
          )";

          // Preparar la declaración
          $stmt = $this->conn->prepare($sql);

          // Vincular los parámetros
          $stmt->bind_param(
              'sssssiddidsi',
              $itemForStorage->id,
              $itemForStorage->platformItemId,
              $itemForStorage->orderId,
              $itemForStorage->itemName,
              $itemForStorage->comment,
              $itemForStorage->quantity,
              $itemForStorage->price,
              $itemForStorage->total,
              $itemForStorage->date,
              $itemForStorage->discount,
              $itemForStorage->parentMenuId,
              $itemForStorage->isMenu
          );

          // Ejecutar la consulta
          if ($stmt->execute()) {
            file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Registro en PlaftormOrderItem insertado correctamente." . PHP_EOL, FILE_APPEND);
            foreach ($item->attributes as $complement) {
              $this->buildPlatformComplement($itemForStorage->id, $complement);
            }
          } else {
            file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Error al insertar el registro en PlaftormOrderItem: " . $stmt->error . PHP_EOL, FILE_APPEND);
          }

          // Cerrar la declaración
          $stmt->close();

          if(isset($item->sub_products)){

            $this->buildPlatformOrderItem($order, $item->sub_products, $itemForStorage->id);            
          }
      }
    }

    private function buildPlatformComplement($platformOrderItemId, $complement){
      $complementToStorage = new stdClass();
      $complementToStorage->id = $this->generateUUID();
      $complementToStorage->platformOrderItemId = $platformOrderItemId;
      $complementToStorage->platformComplementId = $complement->id;
      $complementToStorage->complementName = $complement->name;
      $complementToStorage->quantity = $complement->quantity;
      $complementToStorage->price = $complement->price / 100;
      $complementToStorage->total = $complementToStorage->price * $complementToStorage->quantity;
      $complementToStorage->date = time() * 1000;
      //No viene comment
      //No viene vat
      $sql = "INSERT INTO PlatformComplement (
        id, platformOrderItemId, platformComplementId, complementName, quantity, price, total, date
      ) VALUES (
          ?, ?, ?, ?, ?, ?, ?, ?
      )";

    // Preparar la declaración
    $stmt = $this->conn->prepare($sql);

    // Vincular los parámetros
    $stmt->bind_param(
      'ssssiddi',
      $complementToStorage->id,
      $complementToStorage->platformOrderItemId,
      $complementToStorage->platformComplementId,
      $complementToStorage->complementName,
      $complementToStorage->quantity,
      $complementToStorage->price,
      $complementToStorage->total,
      $complementToStorage->date
    );

      // Ejecutar la consulta
      if ($stmt->execute()) {
        file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Registro en PlaftormComplement insertado correctamente." . PHP_EOL, FILE_APPEND);
      } else {
        file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Error al insertar el registro en PlaftormComplement: " . $stmt->error . PHP_EOL, FILE_APPEND);
      }

      // Cerrar la declaración
      $stmt->close();
    }

    // You will receive a POST request notification every time an order is cancelled.
    // This notification will be sent only if the order has previously been dispatched to the store.
    public function cancelOrder($order){
      $orderId = json_decode($order)->order_id;
      $status = $this->statusList['CANCELLED'];
      // Iniciar la transacción
      $this->conn->begin_transaction();
      try {
        // Preparar la sentencia para actualizar la tabla PlatformOrder
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        // Preparar la sentencia para insertar en la tabla CancelledOrders
        $stmt = $this->conn->prepare('INSERT INTO CancelledOrders (orderId, deliveryPlatform, notified) VALUES (?, ?, ?)');
        $deliveryPlatform = 'GLOVO'; 
        $notified = 0; 
        $stmt->bind_param('ssi', $orderId, $deliveryPlatform, $notified);
        $stmt->execute();
        $stmt->close();

        // Confirmar la transacción
        $this->conn->commit();
      } catch (Exception $e) {
        // Revertir la transacción en caso de error
        $this->conn->rollback();
        throw $e;
      }
    }

    // The order has been accepted by the store. Be aware that if you don't accept the order we will still move forward with the order, as we don't require an acceptance to proceed.
    public function acceptOrder($orderId, $storeId){
      global $glovoBaseURL;
      $url = $glovoBaseURL . '/api/v0/integrations/orders/' . $orderId . '/accept';
      $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));
      // Añadir 30 minutos
      $now->add(new DateInterval('PT30M'));
      $formattedDate = $now->format('Y-m-d\TH:i:s\Z');

      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Glovo-Store-Address-External-Id: ' . $storeId,
          'Authorization: ' . $this->getToken()  
      ));

      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        "committedPreparationTime" => $formattedDate
      )));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      // Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      if ($httpStatusCode == 404 || $httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => 418, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //Cambiar el estado de la orden aceptada
      else if ($httpStatusCode == 202 || $httpStatusCode == 204) {
        $status = $this->statusList['ACCEPTED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden aceptada correctamente')), 'message' => 'Orden aceptada correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al aceptar la orden'));
    }

    // The order is ready to be picked up by a courier or the customer (Only available for orders delivered by Glovo couriers)
    public function readyForPickUp($orderId, $storeId){
      global $glovoBaseURL;
      $url = $glovoBaseURL . '/api/v0/integrations/orders/' . $orderId . '/ready_for_pickup';
      // Inicializar cURL
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Glovo-Store-Address-External-Id: ' . $storeId,
          'Authorization: ' . $this->getToken()  
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      // Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      if ($httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => 418, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //Cambiar el estado de la orden marcada como lista para recoger
      else if ($httpStatusCode == 204) {
        $status = $this->statusList['READY'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden marcada como lista para recoger correctamente')), 'message' => 'Orden marcada como lista para recoger correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al aceptar la orden'));
    }

    public function markOrderDispatched($orderId, $storeId, $whoPickUp){
      global $glovoBaseURL;
      $url = $glovoBaseURL . '/api/v0/integrations/orders/' . $orderId;
      if ($whoPickUp == 'courier') {
        // The courier has collected the order in the store and is now being delivered to the customer (Only available for Marketplace orders)
        $url = $url . '/out_for_delivery';
      } else if ($whoPickUp == 'customer') {
        // The order has been picked up by the customer (Only available for orders to be picked up by the customer).
        $url = $url . '/customer_picked_up';
      } else {
        return (Array('status' => 400, 'data' => json_encode(Array('message' => 'Parámetro "whoPickUp" inválido')), 'message' => 'Parámetro "whoPickUp" inválido'));
      }
      // Inicializar cURL
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Glovo-Store-Address-External-Id: ' . $storeId,
          'Authorization: ' . $this->getToken()  
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      // Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      if ($httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => 418, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //Cambiar el estado de la orden marcada como despachada
      else if ($httpStatusCode == 204) {
        $status = $this->statusList['DISPATCHED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden marcada como despachada correctamente')), 'message' => 'Orden marcada como despachada correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al aceptar la orden'));
    }


    private function generateUUID() {
      $data = random_bytes(16);
      $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante RFC 4122
  
      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function retrieveMenu(){
      return (Array('status' => 405, 'data' => ['message' => "No disponemos aún de menú para la plataforma glovo."]));
    }
    
}
?>
