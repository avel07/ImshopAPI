<?php

namespace Cube\Api\Controllers;

class Order extends BaseController
{

    private $fields = [];

    public const PARAMS = [
        'CURRENCY'                      => 'RUB',   // Дефолтный тип валюты.
        'PERSON_TYPE'                   => 1,       // Тип плательщика.
        'IMSHOP_BASKET_ITEM_CODE'       => 'appId'
    ];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Main\Loader::includeModule('catalog');
        parent::__construct($request);
        
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
    }

    public function createAction(): ?array
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);
        
        // Проходимся по всем заказам массива.
        foreach ($fields['orders'] as $arOrder) {

            // Проверка на существование телефона или email. (должны быть обязательными).
            if (empty($arOrder['phone'] || empty($arOrder['email']))) {
                $this->addError(new \Bitrix\Main\Error('Ошибка получения обязательных данных для пользователя', 400));
                return null;
            }
            // Проверка на валидность номера телефона.
            if (!User::checkPhone($arOrder['phone'])) {
                $this->addError(new \Bitrix\Main\Error('Ошибка. Некорректный номер телефона', 400));
                return null;
            }
            // Проверка на валидность email.
            if (!check_email($arOrder['email'])) {
                $this->addError(new \Bitrix\Main\Error('Ошибка. Некорректный email', 400));
                return null;
            }

            // Получить UserId
            $userId = User::generateUserId($arOrder['externalUserId'], $arOrder['phone'], $arOrder['email']);

            // Если UserId не получено, то выводим ошибку, полученную из самой переменной.
            if (!is_int($userId)) {
                $this->addError(new \Bitrix\Main\Error('Ошибка пользователя - ' . $userId, 400));
                return null;
            }

            // Экземпляр заказа.
            $orderObject = $this->createOrder(\Cube\Api\Application::APP_PARAMS['SITE_ID'], $userId);

            // Экземпляр корзины.
            $basketObject = Basket::createBasket(\Cube\Api\Application::APP_PARAMS['SITE_ID']);


            // Оптимизируем запрос. Выносим за цикл.
            foreach ($arOrder['items'] as $arItem) {
                $arItemsIds[] = $arItem['privateId'];
            }

            // Получаем коллекцию товаров.
            $productsCollection = Product::getProduct(['ID' => $arItemsIds], ['QUANTITY', 'IBLOCK_ELEMENT.ACTIVE']);

            if (count($productsCollection->getAll()) !== count($arOrder['items'])) {
                $this->addError(new \Bitrix\Main\Error('Количество пришедших товаров не совпадает с существующими на сайте. Вероятно несовпадение ID пришедшего с существующим.', 400));
                return null;
            }

            // Добавляем товары в корзину.
            foreach ($arOrder['items'] as $key => $arItem) {
                $productObject = $productsCollection->getAll()[$key];
                
                // Проверка на количественный учет.
                if (!$productObject && $productObject->getQuantity() <= 0) {
                    $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Недоступное количество.', 400));
                    return null;
                }

                // Проверка на активность элемента.
                if (!$productObject->getIblockElement()->getActive()) {
                    $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Товар с ID - '.$productObject->getId().' неактивен.', 400));
                    return null;
                }

                // Поля корзины с кастомным PROPS.
                $arBasketFields = Basket::basketSetFields($arItem['privateId'], $arItem['quantity'], $arItem['id']);

                // Добалвяем Fields в объект корзины. Этот метод помогает избежать мучения с проверками. Все в методе.
                $addProductToBasket = Basket::addItem($basketObject, $arBasketFields, ['SITE_ID' => \Cube\Api\Application::APP_PARAMS['SITE_ID'], 'USER_ID' => $userId]);
                // Если есть ошибка, то покажем ее.
                if ($addProductToBasket instanceof \Bitrix\Main\Error) {
                    $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. ' . $addProductToBasket, 400));
                    return null;
                }
            }
            // Привязываем order к типу плательщика.
            $orderObject->setPersonTypeId(self::PARAMS['PERSON_TYPE']);
            // Привязываем order к корзине. Скидки применятся автоматически.
            $orderObject->setBasket($basketObject);

            // Привязываем конкретную доставку к заказу.
            Deliveries::setDeliveryById($orderObject, $basketObject, Deliveries::getDeliveryIdbyImshop($arOrder['delivery'])['ID']);
            // Привязываем конкретную оплату к заказу.
            Payments::setPayment($orderObject, Payments::getPaymentIdbyImshop($arOrder['payment']));

            // Привязываем свойства заказа.
            $propertyCollection = $orderObject->getPropertyCollection();
            $propertyCollection->getPhone()->setValue($arOrder['phone']);
            $propertyCollection->getUserEmail()->setValue($arOrder['email']);
            $propertyCollection->getPayerName()->setValue($arOrder['name']);
            $propertyCollection->getProfileName()->setValue($arOrder['name']);
            $propertyCollection->getAddress()->setValue($arOrder['address']);
            $propertyCollection->getAddress()->setValue($arOrder['address']);

            $orderObject->setField('USER_DESCRIPTION', $arOrder['deliveryComment']);
            $orderObject->doFinalAction(true);

            $resultOrder = $orderObject->save();
            if (!$resultOrder->isSuccess()) {
                $this->addError(new \Bitrix\Main\Error('Ошибка создания нового заказа - ' . $resultOrder->getErrors(), 400));
                return null;
            }
            // Обновляем данные пользователя после оформления заказа.
            $updateUser = User::updateUser($userId, User::normalizePhone($arOrder['phone']), $arOrder['email'], $arOrder['name']);
            if (!is_int($updateUser)) {
                $this->addError(new \Bitrix\Main\Error('Ошибка обновления данных пользователя - ' . $updateUser, 400));
                return null;
            }
            $arResult = $this->orderResponse($orderObject, $arOrder['uuid']);
        }
        return $arResult;
    }

    /**
     * 
     * @param string $siteId        ID сайта
     * @param string $userId        ID пользователя
     * @return object
     */
    public static function createOrder(string $siteId = 's1', string $userId): ?object
    {
        return \Bitrix\Sale\Order::create($siteId, $userId);
    }

    /**
     * Формирование ответа для ImShop.
     * 
     * @param \Bitrix\Sale\Order $orderObject       Объект заказа
     * @param string $uuId                          Id заказа в ImShop
     */
    private function orderResponse(\Bitrix\Sale\Order $orderObject, string $uuId): ?array
    {
        foreach ($orderObject->getBasket()->getBasketItems() as $basketItem) {
            // Получение коллекции свойств товара в корзине
            $propertyCollection = $basketItem->getPropertyCollection();

            // Поиск свойства по его имени
            foreach ($propertyCollection as $property) {
                if ($property->getField('CODE') === Basket::PARAMS['IMSHOP_BASKET_ITEM_CODE']) {
                    $imShopId = $property->getField('VALUE');
                }
            }
            $basketItems[] = [
                'name'              => $basketItem->getField('NAME'),
                'id'                => $basketItem->getField('PRODUCT_ID'), // Тут надо ввести ID товара, который придет!
                'privateId'         => $basketItem->getField('PRODUCT_ID'),
                'configurationId'   => $basketItem->getField('PRODUCT_ID'),
                'price'             => $basketItem->getPrice(),
                'quantity'          => $basketItem->getQuantity(),
                'discount'          => $basketItem->getFinalPrice() - $basketItem->getPrice() * $basketItem->getQuantity(),
                'subtotal'          => $basketItem->getFinalPrice(),
                'id'                => $imShopId
            ];
            $arResult = [
                'message' => 'Ваш заказ успешно принят. Номер вашего заказа - ' . $orderObject->getId(),
                'orders' => [
                    'success'   => true,
                    'id'        => $orderObject->getId(),
                    'publicId'  => $orderObject->getId(),
                    'uuid'      => $uuId,
                    'items'     => $basketItems
                ]
            ];
        }
        return $arResult;
    }
}
