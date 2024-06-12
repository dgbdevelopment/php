<?php
function receiveRequest($conn, $body) {
  $eventType = $body["event_type"];
  switch ($eventType){
    case "order.notification":
      $url = $body["resource_href"];
      performOrderNotification($conn, $url);
      break;
  }
}

function performOrderNotification($conn, $url){
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($response !== false && $http_status === 200) {
    $response = json_decode($response, true);
    $log = "response: "+ $response;
    file_put_contents('./log_'.date("j.n.Y").'.log', $log, FILE_APPEND);
  }
  curl_close($curl);
}