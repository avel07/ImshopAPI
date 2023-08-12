<?php require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");?>
<?$request = \Bitrix\Main\Context::getCurrent()->getRequest();?>
<?
// Туда сюда пишем код.
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
global $APPLICATION;
$APPLICATION->SetTitle('IMSHOP API тесты');
\Bitrix\Main\UI\Extension::load("ui.buttons");
\Bitrix\Main\UI\Extension::load("ui.alerts");
?>
    <div class="buttons__block">
        <button class="ui-btn ui-btn-lg" data-action="order">Оформить заказ</button>
        <button class="ui-btn ui-btn-lg" data-action="deliveries">Получить список доставок</button>
        <button class="ui-btn ui-btn-lg" data-action="payments">Получить список оплат</button>
        <button class="ui-btn ui-btn-lg" data-action="createPayment">Получить список созданных оплат</button>
        <button class="ui-btn ui-btn-lg" data-action="capturePayment">Проверить созданную оплату</button>
    </div>
    <div class="ui-alert" style="display: none;"></div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    const stringify = (obj, prefix) => {
        const pairs = [];
        for (const key in obj) {
            if (!Object.prototype.hasOwnProperty.call(obj, key)) {
                continue;
            }
            const value = obj[key];
            const enkey = encodeURIComponent(key);
            let pair;
            if (typeof value === 'object') {
                pair = stringify(value, prefix ? `${prefix}[${enkey}]` : enkey);
            } else {
                pair = `${prefix ? `${prefix}[${enkey}]` : enkey}=${encodeURIComponent(value)}`;
            }
            pairs.push(pair);
        }
        return pairs.join('&');
    };
    const orderData = {
        "device": {
            "platform": "ios"
        },
        "installId": "9e201bab-1ccf-47d1-82e0-90d71ee5fd2c",
        "orders": [{
            "uuid": "2d81f6e5-01cb-44ca-abb0-ded88c1fc982",
            "externalUserId": null,
            "hasPreorderItems": true,
            "groupId": "2d81f6e5-01cb-44ca-abb0-ded88c1fc982",
            "createdOn": "2018-10-15 12:54:18.356089",
            "updatedOn": "2018-10-15 12:54:18.356089",
            "status": "placed",
            "name": "Тестовое имя",
            "phone": "+7 (928) 008-7326",
            "email": "test@mail.ru",
            "anotherRecipientData": {
                "name": "Галина",
                "phone": "+7 (996) 687-5884"
            },
            "country": "RU",
            "city": "Москва",
            "address": "ул Тестовая, д 1",
            "addressData": {
                "city": "Москва",
                "region": "Москва",
                "street": "Тестовая",
                "house": "1",
                "building": null,
                "apt": null,
                "zip": "127001",
                "kladr": "7700000000000",
                "city_kladr": "7700000000000",
                "fias_code": "77000000000000000000000",
                "fias_id": "0c5b2444-70a0-4932-980c-b4dc0d3f02b5"
            },
            "price": 28065.00,
            "deliveryPrice": 250.00,
            "authorizedBonuses": 300,
            "promocode": null,
            "appliedDiscount": 0,
            "loyaltyCard": null,
            "delivery": "boxberry/2",
            "deliveryName": "Доставка в пункт самовывоза Боксберри",
            "pickupLocationId": "db30c598-2717-4adc-8e0c-9341786ba1f4",
            "pickupLoactionSnapshot": {},
            "payment": "internal/3",
            "paymentName": "При получении товара",
            "paymentProcessed": false,
            "paymentId": "87da77a2-de03-40a0-91b7-eadabce5db22",
            "paymentGateway": "yandex",
            "externalIds": { "webhook": "66510", "retailcrm": "1456AA", "bitrix": "614" },
            "deliveryComment": "Домофон не работает",
            "customSectionValues": {
                "secionName1": {
                    "field1": "тест",
                    "fieldN": true
                }
            },
            "items": [
                // {
                //   "name": "0214K BLACK Сумка жен.кожа черный",
                //   "id": "00a03026-412a-54fe-a9df-dcf9325f8618",
                //   "privateId": "124124412412",
                //   "configurationId": "124213214142",
                //   "price": 5190,
                //   "quantity": 1,
                //   "discount": 0,
                //   "subtotal": 5190
                // },
                {
                  "name": "915 DD COCOA Сумка жен.экокожа бежевый",
                  "id": "605e0108-dc95-5dab-95a2-7f459da6aade",
                  "privateId": "8189",
                  "configurationId": "8189",
                  "price": 2390,
                  "quantity": 1,
                  "discount": 0,
                  "subtotal": 2390
                },
                {
                  "name": "873 DD BLACK Сумка жен.экокожа черный",
                  "id": "3ccd380e-1f40-5056-8a7a-ef6e8a9582b5",
                  "privateId": "8201",
                  "configurationId": "8201",
                  "price": 4050,
                  "quantity": 2,
                  "discount": 0,
                  "subtotal": 9100
                },
            ]
        }]
    };
    let orderButton = document.querySelector('[data-action="order"]');
    orderButton.addEventListener('click', async (e) => {
        let response = await fetch('/api/orders/create', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json'
            },
            body: JSON.stringify(orderData)
          });
          
          let result = await response.json();
          console.log(result);
    })
    
    const deliveriesData = {
        "externalUserId": null,
        "country": "RU",
        "hasPreorderItems": true,
        "promocode": null,
        "bonusesSpent": 123,
        "position": "checkout",
        'skipPickupLocations': true,
        "addressData": {
            "apt": null,
            "area": null,
            "areaFias": null,
            "areaKladr": null,
            "building": null,
            "city": "Пятигорск",
            "cityFias": "140e31da-27bf-4519-9ea0-6185d681d44e",
            "cityKladr": "5500000100000",
            "city_kladr": "5500000100000",
            "fias": "140e31da-27bf-4519-9ea0-6185d681d44e",
            "fiasCode": "55000001000000000000000",
            "fias_code": "55000001000000000000000",
            "fias_id": "140e31da-27bf-4519-9ea0-6185d681d44e",
            "house": null,
            "houseFias": null,
            "houseKladr": null,
            "kladr": "5500000100000",
            "lat": "54.98",
            "lon": "73.36",
            "region": "Омская",
            "regionFias": "05426864-466d-41a3-82c4-11e61cdc98ce",
            "regionKladr": "5500000000000",
            "settlement": null,
            "settlementFias": null,
            "settlementKladr": null,
            "settlementWithType": null,
            "street": null,
            "streetFias": null,
            "streetKladr": null,
            "value": "г Омск",
            "zip": "644000"
        },
        "items": [
            {
                "name": "Тестовый товар 2",
                "id": "605e0108-dc95-5dab-95a2-7f459da6aade",
                "privateId": "30856",
                "configurationId": "30856",
                "quantity": 2
            }
        ]
    };
    let deliveriesButton = document.querySelector('[data-action="deliveries"]');
    deliveriesButton.addEventListener('click', async (e) => {
        let response = await fetch('/api/deliveries', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json'
            },
            body: JSON.stringify(deliveriesData)
          });
          let result = await response.json();
          console.log(result);
    });

    const paymentsData = {
        "installId": "69f294cd-3502-471a-9b12-598c117fd46d",
          "city": "Москва",
          "addressFiasId": "0c5b2444-70a0-4932-980c-b4dc0d3f02b5",
          "selectedDateIntervalId": null,
          "userData": {},
          "addressFull": "г Москва",
          "items": [
            {
                "name": "915 DD COCOA Сумка жен.экокожа бежевый",
                "id": "605e0108-dc95-5dab-95a2-7f459da6aade",
                "privateId": "8189",
                "configurationId": "8189",
                "price": 2390,
                "quantity": 1,
                "discount": 0,
                "subtotal": 2390
            },
            {
                "name": "873 DD BLACK Сумка жен.экокожа черный",
                "id": "3ccd380e-1f40-5056-8a7a-ef6e8a9582b5",
                "privateId": "8201",
                "configurationId": "8201",
                "price": 4050,
                "quantity": 2,
                "discount": 0,
                "subtotal": 9100
            },
          ],
          "pickupLocationId": "MSK65",
          "bonusesSpent": 0,
          "country": "RU",
          "regionFiasId": "0c5b2444-70a0-4932-980c-b4dc0d3f02b5",
          "loyaltyCard": null,
          "deliveryId": "zxc/2",
          "cityFiasId": "0c5b2444-70a0-4932-980c-b4dc0d3f02b5",
          "clientVersion": "6.20.0",
          "promocode": null,
          "externalUserId": "17420"
    };
    let paymentsButton = document.querySelector('[data-action="payments"]');
    paymentsButton.addEventListener('click', async (e) => {
        let response = await fetch('/api/payments', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json'
            },
            body: JSON.stringify(paymentsData)
          });
          let result = await response.json();
          console.log(result);
    });
    const createPayment = {
        "command": "create",
        "paymentMethodId": "sberbank/card",
        "orderUuid": "cbc85e53-8067-4f13-a708-11d9aea71bf3",
        "orderId": "6941",
        "returnUrl": "imshop://payment/failed"
    }
    let createPaymentButton = document.querySelector('[data-action="createPayment"]');
    createPaymentButton.addEventListener('click', async (e) => {
        let response = await fetch('/api/payments/create', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json'
            },
            body: JSON.stringify(createPayment)
          });
          let result = await response.json();
          console.log(result);
    });

    const capturePayment = {
        "command": "capture",
        "paymentMethodId": "sberbank/card",
        "paymentId": "6956"
    }
    let capturePaymentButton = document.querySelector('[data-action="capturePayment"]');
    capturePaymentButton.addEventListener('click', async (e) => {
        let response = await fetch('/api/payments/capture', {
            method: 'POST',
            headers: {
                'Content-Type':'application/json'
            },
            body: JSON.stringify(capturePayment)
          });
          let result = await response.json();
          console.log(result);
    });

})
    </script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
