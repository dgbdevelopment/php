<?php
  $uberSignKey = "7BwCs4ruxKLVWrjBTVk2MjJkNhH6pZ7JTKMBB5bu";
  $client_secret = "7BwCs4ruxKLVWrjBTVk2MjJkNhH6pZ7JTKMBB5bu";
  $client_id = "TCpq5rbC6xjfK2Xf1Xm1Gfy0Brek5xbz";

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
    $sqlContent = file_get_contents('./tables.sql');
    if ($sqlContent === false) {
      die("Error al leer el archivo SQL");
    }
  
    // Dividir el contenido del archivo SQL en sentencias individuales
    $sqlStatements = explode(';', $sqlContent);
    
    // Ejecutar cada sentencia SQL
    foreach ($sqlStatements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            if ($conn->query($statement) === false) {
                echo "Error al ejecutar la sentencia: " . $conn->error . "<br>";
            }
        }
    }
    // $conn->query("INSERT INTO Config (shopId, uberStoreId) VALUES ('60d19d66df19737ebb4cc444', 'e517d5ec-cad6-41e2-89e8-a3270c557b05')");
  }

  $statusList = [
    "OFFERED" => 1,
    "EXPIRED" => 2,
    "ACCEPTED" => 3,
    "DENNIED" => 4,
    "CANCELLED" => 5,
    "READY" => 6
  ];

  $typeOrderList = [
    "PICKED_UP_BY_CUSTOMER" => 0,
    "DELIVERY_BY_UBER" => 1,
    "DELIVERY_BY_GLOVO" => 2,
  ];

