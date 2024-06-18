# Endpoints para APP:

## Recibir todas las ordenes por shopId
https://betadelivery.turbopos.es/api/orders/:shopId?access_token=Shop_access_token
El parámetro **`:shopId`** es necesario
El valor **`Shop_access_token`** es necesario

## Aceptar una orden
http://betadelivery.turbopos.es/api/orders/acceptOrder/:orderId
El parámetro **`:orderId`** es necesario

## Denegar una orden
http://betadelivery.turbopos.es/api/orders/denyOrder/:orderId
El parámetro **`:orderId`** es necesario

## Cancelar una orden
http://betadelivery.turbopos.es/api/orders/cancelOrder/:orderId
El parámetro **`:orderId`** es necesario

