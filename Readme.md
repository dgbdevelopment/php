# Endpoints para APP:

## Recibir todas las ordenes por shopId
Method **GET**
https://betadelivery.turbopos.es/api/orders/:shopId?access_token=SHOP_ACCESS_TOKEN
El parámetro **`:shopId`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Aceptar una orden
Method **POST**
https://betadelivery.turbopos.es/api/orders/acceptOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario


## Denegar una orden
Method **POST**
https://betadelivery.turbopos.es/api/orders/denyOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Cancelar una orden
Method **POST**
https://betadelivery.turbopos.es/api/orders/cancelOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario
<!-- Información de GLOVO:
We send the notification about canceled orders to the endpoint you provide us, and will not expect an answer from your side. It is not possible to cancel or refuse orders via the API.
We do not have an endpoint for the Partner to cancel an order. In order to cancel an order, the Partner must call the client or our Customer Service phone number. -->

## Marcar una orden como preparada
Method **POST**
https://betadelivery.turbopos.es/api/orders/orderReady/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Marcar una orden como despachada
Method **POST**
https://betadelivery.turbopos.es/api/orders/orderDispatched/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN&who_pickup=WHO_PICKUP
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`WHO_PICKUP`** es necesario para peticiones de GLOVO. Valores admitidos: 'customer' o 'courier' (cliente o repartidor)
El valor **`SHOP_ACCESS_TOKEN`** es necesario
<!-- Información desde GLOVO:
Do I have to send both Ready for Pickup and Out for Delivery for each order?
No, we expect only to receive the READY_FOR_PICKUP status once you’ve finished the picking process. -->

## Obtener todas las órdenes canceladas sin notificar
Method **GET**
https://betadelivery.turbopos.es/api/getCancelledOrders?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Marcar orden cancelada como notificada
Method **POST**
https://betadelivery.turbopos.es/api/orders/markCancelledAsNotified/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Obtener el menu de una tienda en una plataforma determinada
Method **GET**
https://betadelivery.turbopos.es/api/retrieveMenu/:deliveryPlatform/:shopId?access_token=SHOP_ACCESS_TOKEN
El parámetro **`:deliveryPlatform`** es necesario
  Valores permitidos: uber_eats | glovo
El parámetro **`:shopId`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

