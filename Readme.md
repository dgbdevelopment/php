# Endpoints para APP:

## Recibir todas las ordenes por shopId
Method **GET**
https://betadelivery.turbopos.es/api/orders/:shopId?access_token=SHOP_ACCESS_TOKEN
El parámetro **`:shopId`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Aceptar una orden
Method **POST**
http://betadelivery.turbopos.es/api/orders/acceptOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario


## Denegar una orden
Method **POST**
http://betadelivery.turbopos.es/api/orders/denyOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Cancelar una orden
Method **POST**
http://betadelivery.turbopos.es/api/orders/cancelOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

## Marcar una orden como preparada
Method **POST**
http://betadelivery.turbopos.es/api/orders/cancelOrder/:orderId?shop_id=SHOP_ID&access_token=SHOP_ACCESS_TOKEN
El parámetro **`:orderId`** es necesario
El valor **`SHOP_ID`** es necesario
El valor **`SHOP_ACCESS_TOKEN`** es necesario

