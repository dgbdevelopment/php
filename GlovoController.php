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
        // file_put_contents('LastOrderFromGlovo_log.json', json_encode($order));
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

        // file_put_contents('GlovoVariables.txt',
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

    public function cancelOrder($order) {
      $order = json_decode($order);
      $id = $order->order_id;
      $query = "UPDATE PlatformOrder SET status = ? WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $stmt->bind_param('is', $this->statusList['CANCELLED'], $id);
      if ($stmt->execute()) {
        file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Order " . $id . " updated successfully." . PHP_EOL, FILE_APPEND);
      } else {
        file_put_contents('db_log.txt', date("d/m/Y-H:i:s") . " -> Error updating order: " . $stmt->error . PHP_EOL, FILE_APPEND);
      }
      $stmt->close();    
    }

    private function generateUUID() {
      $data = random_bytes(16);
      $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante RFC 4122
  
      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
}
?>
