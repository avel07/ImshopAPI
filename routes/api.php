<?php

use Bitrix\Main\Routing\Controllers\PublicPageController;
/**
 * Метод для роутинга
 * 
 * @param \Bitrix\Main\Routing\RoutingConfigurator $routes  Роутинг конфигуратор
 * @return void
 */
return function(\Bitrix\Main\Routing\RoutingConfigurator $routes)
{
    // Оформление заказа (Проверено, отлажено)
    $routes->post('/api/orders/create', [\Cube\Api\Controllers\Order::class, 'create']);

    // Список всех доставок (Проверено, отлажено)
    $routes->post('/api/deliveries', [\Cube\Api\Controllers\Deliveries::class, 'list']);

    // Список всех оплат (Проверено, отлажено)
    $routes->post('/api/payments', [\Cube\Api\Controllers\Payments::class, 'list']);

    // Создание платежной системы. (Проверено, отлажено)
    $routes->post('/api/payments/create', [\Cube\Api\Controllers\Payments::class, 'create']);

    // Проверка платежа. (Не проверено, не отлажено, так как ниразу не оплачивали)
    $routes->post('/api/payments/capture', [\Cube\Api\Controllers\Payments::class, 'capture']);

    // Наличие товаров (Не знаю проверено ли, ошибок не было)
    $routes->post('/api/products/availability', [\Cube\Api\Controllers\Product::class, 'availability']);

    // Расчет корзины (Проверено, отлажено)
    $routes->post('/api/basket/calculate', [\Cube\Api\Controllers\Basket::class, 'calculate']);

    // Список заказов пользователя (Не проверено, не отлажено)
    $routes->post('/api/user/orders', [\Cube\Api\Controllers\User::class, 'ordersList']);
    
    // Данные по пользователю (Не проверено, не отлажено)
    $routes->post('/api/user/get', [\Cube\Api\Controllers\User::class, 'get']);

    // Редактирование пользователя (Не проверено, не отлажено)
    $routes->post('/api/user/edit', [\Cube\Api\Controllers\User::class, 'edit']);

    // Удаление пользователя (Не проверено, не отлажено)
    $routes->post('/api/user/remove', [\Cube\Api\Controllers\User::class, 'remove']);

    // Вебхук отправки OTP (Не проверено, не отлажено)
    $routes->post('/api/user/otp/send', [\Cube\Api\Controllers\User::class, 'otpSend']);

    // Вебхук проверки OTP (Не проверено, не отлажено)
    $routes->post('/api/user/otp/check', [\Cube\Api\Controllers\User::class, 'otpCheck']);

};