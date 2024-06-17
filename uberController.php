<?php

$token = getTokenFromUberEats($client_id, $client_secret)["access_token"];

function receiveRequest($body) {
  $body = json_decode($body, true);
  $eventType = $body["event_type"];
  switch ($eventType){
    case "orders.notification":
      $orderId = $body["meta"]["resource_id"];
      performOrderNotification($orderId);
      break;
  }
}

function performOrderNotification($orderId){
  global $token;
  $url = 'https://api.uber.com/v1/delivery/order/' . $orderId . "?expand=carts,deliveries,payment";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $headers = [
    'Authorization: Bearer ' . $token
  ];
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  $response = curl_exec($curl);
  $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($response !== false && $http_status === 200) {
    $response = json_decode($response);
    buildPlatformOrder($response->order);
    buildPlatformOrderItem($response->order);
  }
  curl_close($curl);
}

function buildPlatformOrder($order){
  file_put_contents('Order_log.json', json_encode($order));
  global $statusList, $conn, $typeOrderList;
  $id = $order->id;
  $name = $order->display_id;
  $date = strtotime($order->created_time) * 1000;
  $status = $statusList[$order->state];
  $paid = ($order->payment->payment_detail->order_total->gross->amount_e5) / 100000;
  $discount = ($order->payment->payment_detail->promotions->total->gross->amount_e5) / 100000;
  $clientId = $order->customers[0]->id;
  $clientName = $order->customers[0]->name->display_name;
  $clientPhone = $order->customers[0]->contact->phone->number;
  $storePlatformId = $order->store->id;
  $address = isset($order->deliveries[0]->location) ? $order->delivery->dropoff->location->street_address_line_one . ', ' . $order->delivery->dropoff->location->street_address_line_two : 'No disponible';
  $typeOrder = $typeOrderList[$order->fulfillment_type];
  $observations = isset($order->store_instructions) ? $order->store_instructions : '';
  $deliveryPlatform = $order->ordering_platform;
  
  file_put_contents('test.txt',
  'id: ' . $id . PHP_EOL . 
  'name:  ' . $name . PHP_EOL . 
  'date: ' . $date . PHP_EOL . 
  'status:  ' . $status . PHP_EOL .
  'paid:  ' . $paid . PHP_EOL .
  'discount: ' . $discount . PHP_EOL .
  'clientId: ' . $clientId . PHP_EOL .
  'clientName: '. $clientName . PHP_EOL .
  'clientPhone: '. $clientPhone . PHP_EOL .
  'address: '. $address . PHP_EOL .
  'storePlatformId: ' . $storePlatformId . PHP_EOL .
  'deliveryPlatform: ' . $deliveryPlatform . PHP_EOL .
  'typeOrder: ' . $typeOrder . PHP_EOL .
  'observations: ' . $observations . PHP_EOL
  );

  $query = "INSERT INTO PlatformOrder (id, name, date, status, paid, discount, numCommensals, clientId, clientName, clientPhone, storePlatformId, observations, address, discountCodes, typeOrder, deliveryPlatform) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Preparar y ejecutar la declaración
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssiddsssssssiss', 
        $id, $name, $date, $status, $paid, $discount, 
        $numCommensals, $clientId, $clientName, $clientPhone, 
        $storePlatformId, $observations, $address, $discountCodes, 
        $typeOrder, $deliveryPlatform);

    // Ejecutar la declaración y verificar si se insertó correctamente
    if ($stmt->execute()) {
        file_put_contents('db_log.txt', "Order inserted successfully." . PHP_EOL, FILE_APPEND);
    } else {
      file_put_contents('db_log.txt', "Error inserting order: " . json_encode($stmt) . PHP_EOL, FILE_APPEND);
    }

    // Cerrar la declaración
    $stmt->close();
}

function buildPlatformOrderItem($order){
  global $conn;
  $itemForStorage = new stdClass();
  foreach ($order->carts[0]->items as $item) {
    $itemForStorage->platformItemId = $item->cart_item_id;
    $itemForStorage->itemId = $item->id;
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
      if($tax->instance_id == $itemForStorage->id){
        $itemForStorage->vat = $tax->taxes[0]->rate * 100;
      }
    }

    $itemForStorage->total = $itemForStorage->price * $itemForStorage->quantity;

    // Preparar la consulta SQL
    $sql = "INSERT INTO PlatformOrderItem (
      platformItemId, itemId, orderId, itemName, comment, quantity, price, total, date, vat
      ) VALUES (
          ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
      )";

    // Preparar la declaración
    $stmt = $conn->prepare($sql);

    // Vincular los parámetros
    $stmt->bind_param(
    'sssssiidii',
    $itemForStorage->platformItemId,
    $itemForStorage->itemId,
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
    echo "Registro insertado exitosamente.";
    } else {
    echo "Error al insertar el registro: " . $stmt->error;
    }

    // Cerrar la declaración
    $stmt->close();
    
  }
}

function getTokenFromUberEats($client_id, $client_secret){
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
        'scope' => 'eats.order'
      )
  ));

  // Ejecutar la solicitud y obtener la respuesta
  $response = curl_exec($curl);

  // Verificar si hubo algún error
  if ($response === false) {
      $error_message = curl_error($curl);
      // Manejar el error aquí
  } else {
      // Procesar la respuesta
      // echo '<pre>'; echo 'linea 56'; print_r($response); echo '</pre>';
      return json_decode($response, true);
  }

  // Cerrar la sesión cURL
  curl_close($curl);
}