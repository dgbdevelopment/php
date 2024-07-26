<?php

class UBerEatsController {
    private $token;
    private $conn;
    private $client_id;
    private $client_secret;
    private $statusList;
    private $typeOrderList;

    public function __construct($client_id, $client_secret, $conn, $statusList, $typeOrderList) {
        $this->conn = $conn;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->statusList = $statusList;
        $this->typeOrderList = $typeOrderList;
        $this->token = $this->requestToken($client_id, $client_secret);
    }

    public function receiveRequest($body) {
      $body = json_decode($body, true);
      $eventType = $body["event_type"];
      switch ($eventType){
          case "orders.notification":
              $orderId = $body["meta"]["resource_id"];
              $this->performOrderNotification($orderId);
              break;
          case "orders.cancel":
            $orderId = $body["meta"]["resource_id"];
            $this->cancelOrderOnDB($orderId);
            break;
          default:
            file_put_contents('NotCatchedRequests.json', date("d/m/Y-H:i:s") . " -> " . $body, FILE_APPEND);
            break;
      }
    }

    public function getToken(){
      return $this->token;
    }

    private function performOrderNotification($orderId) {
        $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . "?expand=carts,deliveries,payment";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            'Authorization: Bearer ' . $this->getToken()
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($http_status == 401) $this->renewToken();
        else if ($response !== false && $http_status === 200) {
            $response = json_decode($response);
            $this->buildPlatformOrder($response->order);
            $this->buildPlatformOrderItem($response->order);
        } else {
            file_put_contents('ErrorFromUberEats_log.json', date("d/m/Y-H:i:s") . " -> " . json_encode($response), FILE_APPEND);
        }
        curl_close($curl);
    }

    private function buildPlatformOrder($order) {
        file_put_contents('LastOrderFromUberEats_log.json', json_encode($order));
        $id = $order->id;
        $name = $order->display_id;
        $date = strtotime($order->created_time) * 1000;
        $status = $this->statusList[$order->state];
        $paid = ($order->payment->payment_detail->order_total->gross->amount_e5) / 100000;
        $discount = ($order->payment->payment_detail->promotions->total->gross->amount_e5) / 100000;
        $clientId = $order->customers[0]->id;
        $clientName = $order->customers[0]->name->display_name;
        $clientPhone = $order->customers[0]->contact->phone->number;
        $storePlatformId = $order->store->id;
        $address = isset($order->deliveries[0]->location) ? $order->delivery->dropoff->location->street_address_line_one . ', ' . $order->delivery->dropoff->location->street_address_line_two : 'No disponible';
        $typeOrder = $this->typeOrderList[$order->fulfillment_type];
        $observations = isset($order->store_instructions) ? $order->store_instructions : '';
        $deliveryPlatform = $order->ordering_platform;
        $discountCodes = ''; // Agrega esta línea si es necesario para el parámetro $discountCodes
        $numCommensals = 0; // Agrega esta línea si es necesario para el parámetro $numCommensals

        // file_put_contents('test.txt',
        //     'id: ' . $id . PHP_EOL . 
        //     'name:  ' . $name . PHP_EOL . 
        //     'date: ' . $date . PHP_EOL . 
        //     'status:  ' . $status . PHP_EOL .
        //     'paid:  ' . $paid . PHP_EOL .
        //     'discount: ' . $discount . PHP_EOL .
        //     'clientId: ' . $clientId . PHP_EOL .
        //     'clientName: '. $clientName . PHP_EOL .
        //     'clientPhone: '. $clientPhone . PHP_EOL .
        //     'address: '. $address . PHP_EOL .
        //     'storePlatformId: ' . $storePlatformId . PHP_EOL .
        //     'deliveryPlatform: ' . $deliveryPlatform . PHP_EOL .
        //     'typeOrder: ' . $typeOrder . PHP_EOL .
        //     'observations: ' . $observations . PHP_EOL
        // );

        $query = "INSERT INTO PlatformOrder (id, name, date, status, paid, discount, numCommensals, clientId, clientName, clientPhone, storePlatformId, observations, address, discountCodes, typeOrder, deliveryPlatform) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Preparar y ejecutar la declaración
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ssiiddisssssssis', 
            $id, $name, $date, $status, $paid, $discount, 
            $numCommensals, $clientId, $clientName, $clientPhone, 
            $storePlatformId, $observations, $address, $discountCodes, 
            $typeOrder, $deliveryPlatform
        );

        // Ejecutar la declaración y verificar si se insertó correctamente
        if ($stmt->execute()) {
            file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Registro en PlaftormOrder insertado correctamente." . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents('db_log.txt',  date("d/m/Y-H:i:s") . " -> Error al insertar el registro en PlaftormOrder: " . json_encode($stmt) . PHP_EOL, FILE_APPEND);
        }

        // Cerrar la declaración
        $stmt->close();
    }

    private function buildPlatformOrderItem($order) {
        $itemForStorage = new stdClass();
        foreach ($order->carts[0]->items as $item) {
            $itemForStorage->id = $this->generateUUID();
            $itemForStorage->platformItemId = $item->cart_item_id;
            $itemForStorage->orderId = $order->id;
            $itemForStorage->itemName = $item->title;
            $itemForStorage->comment = $item->customer_request->special_instructions;
            $itemForStorage->quantity = $item->quantity->amount;
            $itemForStorage->date = strtotime($order->created_time) * 1000;

            foreach($order->payment->payment_detail->item_charges->price_breakdown as $price){
                if($price->cart_item_id == $itemForStorage->platformItemId){
                    $itemForStorage->price = $price->unit->gross->amount_e5 / 100000;
                }
            }

            foreach($order->payment->tax_reporting->breakdown->items as $tax){
                if($tax->instance_id == $itemForStorage->platformItemId){
                    $itemForStorage->vat = $tax->taxes[0]->rate * 100;
                }
            }

            $itemForStorage->total = $itemForStorage->price * $itemForStorage->quantity;

            // Preparar la consulta SQL
            $sql = "INSERT INTO PlatformOrderItem (
                id, platformItemId, orderId, itemName, comment, quantity, price, total, date, vat
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

            // Preparar la declaración
            $stmt = $this->conn->prepare($sql);

            // Vincular los parámetros
            $stmt->bind_param(
                'sssssiddid',
                $itemForStorage->id,
                $itemForStorage->platformItemId,
                $itemForStorage->orderId,
                $itemForStorage->itemName,
                $itemForStorage->comment,
                $itemForStorage->quantity,
                $itemForStorage->price,
                $itemForStorage->total,
                $itemForStorage->date,
                $itemForStorage->vat
            );

            // Ejecutar la consulta
            if ($stmt->execute()) {
              file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Registro en PlaftormOrderItem insertado correctamente." . PHP_EOL, FILE_APPEND);
              if (isset($item->selected_modifier_groups)){
                foreach($item->selected_modifier_groups as $complements){
                  $this->buildPlatformComplement($order, $itemForStorage->id, $complements);
                }
              }
            } else {
              file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Error al insertar el registro en PlaftormOrderItem: " . $stmt->error . PHP_EOL, FILE_APPEND);
            }

            // Cerrar la declaración
            $stmt->close();
        }
    }

    private function requestToken($client_id, $client_secret){
      $token = $this->getTokenFromDB();
      if(isset($token)){
        return $token;
      } else {
        $result = $this->getTokenFromUberEats($client_id, $client_secret); 
        return $result;
      }
    }

    private function getTokenFromDB(){
      // Obtiene el tiempo actual en milisegundos
      $currentTimestamp = round(microtime(true) * 1000);

      // Consulta SQL actualizada para incluir la verificación de expirationDate
      $query = "SELECT token FROM Tokens WHERE platform = 'UBER_EATS' AND expirationDate > $currentTimestamp";
      $result = $this->conn->query($query);

      if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row["token"];
      } else {
        return null;
      }
    }

    private function getTokenFromUberEats($client_id, $client_secret) {
        $curl = curl_init();
        // Configurar las opciones de la solicitud
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://auth.uber.com/oauth/v2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'client_credentials',
                'scope' => 'eats.order eats.store'
            )
        ));

        // Ejecutar la solicitud y obtener la respuesta
        $response = curl_exec($curl);

        // Verificar si hubo algún error
        if ($response === false) {
            $error_message = curl_error($curl);
            return null;
        } else {
          $token = json_decode($response)->access_token;
          if (isset($token)){
            $expirationDate = round(microtime(true) * 1000) + 2592000 * 1000;
            $query = "INSERT INTO Tokens (platform, token, expirationDate) VALUES ('UBER_EATS', '$token', $expirationDate)
              ON DUPLICATE KEY UPDATE token = VALUES(token), expirationDate = VALUES(expirationDate);";
            $result = $this->conn->query($query);
            return $token;  
          }
          return null;
        }

        // Cerrar la sesión cURL
        curl_close($curl);
    }

    public function acceptOrder($orderId){
      $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . '/accept';
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  // Establecer el método POST
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $this->getToken()  // Agregar el token de acceso 
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if($httpStatusCode == 401) $this->renewToken();

      // Mostrar la respuesta del servidor
      else if($httpStatusCode == 404) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return Array('status' => 404, 'data' => $response, 'message' => 'Orden no encontrada o expirada');
      }
      //TODO cambiar el estado de la orden aceptada
      else if ($httpStatusCode == 200) {
        $status = $this->statusList['ACCEPTED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden aceptada correctamente')), 'message' => 'Orden aceptada correctamente'));
      }
      return Array('status' => 500, 'data' => null, 'message' => 'Error al aceptar la orden');
    }

    public function denyOrder($orderId){
      $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . '/deny';
      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  // Establecer el método POST
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $this->getToken()  // Agregar el token de acceso 
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        "deny_reason" => array(
          "info" => "Denied by shop",
          "type" => "CAPACITY",
          "client_error_code" => "408",
          "item_metadata" => new stdClass()
        )
      )));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if($httpStatusCode == 401) $this->renewToken();

      // Mostrar la respuesta del servidor
      else if($httpStatusCode == 404 || $httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => $httpStatusCode, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //TODO cambiar el estado de la orden denegada
      else if ($httpStatusCode == 200) {
        $status = $this->statusList['DENNIED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden denegada correctamente')), 'message' => 'Orden denegada correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al denegar la orden'));
    }

    public function cancelOrder($orderId){
      $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . '/cancel';
      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $this->getToken()  
      ));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        "deny_reason" => array(
          "info" => "Cancelled by shop",
          "type" => "CAPACITY",
          "client_error_code" => "408"
        )
      )));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if($httpStatusCode == 401) $this->renewToken();

      // Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      else if($httpStatusCode == 404 || $httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => $httpStatusCode, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //Cambiar el estado de la orden cancelada
      else if ($httpStatusCode == 200) {
        $status = $this->statusList['CANCELLED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden cancelada correctamente')), 'message' => 'Orden cancelada correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al denegar la orden'));
    }

    private function cancelOrderOnDB($orderId){
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
        $deliveryPlatform = 'UBER_EATS'; 
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

    public function markOderReady($orderId){
      $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . '/ready';
      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_POST, true);  // Establecer el método POST
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $this->getToken()  // Agregar el token de acceso 
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if($httpStatusCode == 401) $this->renewToken();
      //Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      else if($httpStatusCode == 404 || $httpStatusCode == 400) {
        $status = $this->statusList['EXPIRED'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();

        return (Array('status' => 418, 'data' => $response, 'message' => 'Orden no encontrada o expirada'));
      }
      //TODO cambiar el estado de la orden a READY
      else if ($httpStatusCode == 200) {
        $status = $this->statusList['READY'];
        $stmt = $this->conn->prepare('UPDATE PlatformOrder SET status = ? WHERE id = ?');
        $stmt->bind_param('is', $status, $orderId);
        $stmt->execute();
        $stmt->close();
        return (Array('status' => 200, 'data' => json_encode(array('message' => 'Orden marcada como preparada correctamente')), 'message' => 'Orden marcada como preparada correctamente'));
      }
      return (Array('status' => 500, 'data' => null, 'message' => 'Error al marcar la orden como preparada'));
    }

    private function renewToken(){
      $stmt = $this->conn->prepare('DELETE FROM Tokens WHERE platform = ?');
      $platform = 'UBER_EATS';
      $stmt->bind_param('s', $platform);
      $stmt->execute();
      $stmt->close();
      $this->token = $this->getTokenFromUberEats($this->client_id, $this->client_secret);
    }

    private function generateUUID() {
      $data = random_bytes(16);
      $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante RFC 4122
  
      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }     
    
    private function buildPlatformComplement($order, $itemForStorageId, $complements){
      foreach ($complements->selected_items as $complement) {
        $complementToStorage = new stdClass();
        $complementToStorage->id = $this->generateUUID();
        $complementToStorage->platformOrderItemId = $itemForStorageId;
        $complementToStorage->platformComplementId = $complement->id;
        $complementToStorage->complementName = $complement->title;
        $complementToStorage->quantity = $complement->quantity->amount;
        $complementToStorage->price = $this->getPrice($order, $complement->cart_item_id) /100000;
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
    }

      // Cerrar la declaración
      $stmt->close();
    }

    private function getPrice($order, $cartItemId){
      foreach ($order->payment->payment_detail->item_charges->price_breakdown as $item) {
        if($item->cart_item_id == $cartItemId) return $item->unit->gross->amount_e5;
      }
      return 0;
    }

    public function retrieveMenu($shopId){
      $uberStoreId = $this->getUberStoreId($shopId);
      $url = 'https://api.uber.com/v2/eats/stores/' . $uberStoreId . '/menus';
      // Inicializar cURL
      $ch = curl_init($url);

      // Establecer opciones para la solicitud cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Devolver el resultado en lugar de imprimirlo
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . $this->getToken()  // Agregar el token de acceso 
      ));

      $response = curl_exec($ch);

      if (curl_errno($ch)) {
          return (Array('status' => 500, 'data' => null, 'message' => 'Error:' . curl_error($ch)));
      }

      $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if($httpStatusCode == 401) $this->renewToken();
      //Si la orden no se encuentra es que está expirada (no se aceptó en el tiempo establecido). Cambiamos su status a EXPIRED (2)
      else if($httpStatusCode == 404 || $httpStatusCode == 400) {
        return (Array('status' => 418, 'data' => $response, 'message' => 'Error al obtener menú'));
      }
      //TODO cambiar el estado de la orden a READY
      else if ($httpStatusCode == 200) {
        return (Array('status' => 200, 'data' => json_decode($response), 'message' => 'Orden marcada como preparada correctamente'));
      }
      return (Array('status' => 500, 'data' => $response, 'message' => 'Error al marcar la orden como preparada'));
    } 
    
    private function getUberStoreId($shopId){
      $stmt = $this->conn->prepare('SELECT uberStoreId FROM Config WHERE shopId = ?');
      $stmt->bind_param('s', $shopId);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $stmt->close();
      if ($row){
        return $row['uberStoreId'];
      } else {
        return null;
      }
    }
}
