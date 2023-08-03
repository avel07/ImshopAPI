<?php

use Bitrix\Main\Routing\Controllers\PublicPageController;
return function(\Bitrix\Main\Routing\RoutingConfigurator $routes)
{
    // Оформление заказа
    $routes->post('/api/orders/create', [Cube\Api\Controllers\Order::class, 'create']);

    // Список всех доставок
    $routes->post('/api/deliveries', [Cube\Api\Controllers\Deliveries::class, 'list']);

    // Список всех оплат
    $routes->post('/api/payments', [Cube\Api\Controllers\Payments::class, 'list']);

    // Наличие товаров
    // $routes->post('/api/products/availability', [Cube\Api\Controllers\Deliveries::class, 'create']);

    // Расчет корзины
    // $routes->post('/api/basket/calculate', [Cube\Api\Controllers\Deliveries::class, 'create']);

    // Список заказов пользователя
    // $routes->post('/api/user/orders', [Cube\Api\Controllers\Deliveries::class, 'create']);
};