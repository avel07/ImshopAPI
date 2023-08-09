<?php

namespace Cube\Api\Controllers;

use Bitrix\Main\HttpResponse;

class Deliveries extends BaseController
{
    private $fields = [];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        parent::__construct($request);
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
    }

    /**
     * Главный метод класса. Возвращает валидный для ImShop response через переопределенный ответ.
     * 
     * @return array
     */
    public function listAction(): ?array
    {
        // Полученные поля.
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);

        if (!empty($fields['externalUserId'])) {
            $userId = User::getAnonimusUserId($fields['externalUserId']);
            if (!$userId) {
                // Проверка на существование ID юзера.
                $this->addError(new \Bitrix\Main\Error('Ошибка. Неверный userId', 400));
                return null;
            }
        } else {
            // Присваиваем анонимного.
            $userId = User::getAnonimusUserId();
        }

        // Создаем объект заказа, чтобы через него пропустить список всех доставок со скидками.
        $orderObject = Order::createOrder(\Cube\Api\Application::APP_PARAMS['SITE_ID'], $userId);

        // Создаем объект корзины, чтобы пропустить через неё список всех товаров.
        $basketObject = Basket::createBasket(\Cube\Api\Application::APP_PARAMS['SITE_ID']);

        // Город, который пришел с запроса. Нам по сути нужен только его поле - CODE.
        $city = Location::getCitiesAction(Location::COUNTRY_CODE_RU, $fields['addressData']['city']);
        
        // Оптимизируем запрос. Выносим за цикл.
        foreach ($fields['items'] as $arItem) {
            $arItemsIds[] = $arItem['privateId'];
        }

        // Получаем коллекцию товаров.
        $productsCollection = Product::getProduct(['ID' => $arItemsIds], ['QUANTITY', 'IBLOCK_ELEMENT.ACTIVE']);


        if (count($productsCollection->getAll()) !== count($fields['items'])) {
            $this->addError(new \Bitrix\Main\Error('Количество пришедших товаров не совпадает с существующими на сайте. Вероятно несовпадение ID пришедшего с существующим.', 400));
            return null;
        }

        // Добавляем товары в корзину.
        foreach ($fields['items'] as $key => $arItem) {
            $productObject = $productsCollection->getAll()[$key];

            // Проверка на количественный учет.
            if (!$productObject && $productObject->getQuantity() <= 0) {
                $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Недоступное количество.', 400));
                return null;
            }
            // Проверка на активность элемента.
            if (!$productObject->getIblockElement()->getActive()) {
                $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Товар с ID - ' . $productObject->getId() . ' неактивен.', 400));
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
        $orderObject->setPersonTypeId(Order::PARAMS['PERSON_TYPE']);
        // Привязываем order к корзине. Скидки применятся автоматически.
        $orderObject->setBasket($basketObject);

        // Получаем все доставки. 
        $allShipmentsCollection = $this->getAllDeliveries(['NAME', 'CLASS_NAME', 'CODE', 'DESCRIPTION']);
        // Привязываем каждую доставку, кроме системной пустой.
        foreach ($allShipmentsCollection as $shipmentObject) {
            // Убираем пустую системную доставку.
            if ($shipmentObject->getClassName() === '\Bitrix\Sale\Delivery\Services\EmptyDeliveryService') {
                continue;
            }

            // Получаем extraServices, ID пунктов самовывоза.
            if($this->hasExtraServices($shipmentObject)){
                $stores = $this->getStoresByDelivery($shipmentObject, $city['CODE']);
                $stores = $stores['WIDTH_CODE'] ?? $stores['WITHOUT_CODE'];
            }

            // Устанавливаем доставку.
            $this->setDeliveryById($orderObject, $basketObject, $shipmentObject->getId());

            // Запишем массив объектов доставок.
            $shipmentsArray[] = $shipmentObject;
        }
        // Формируем ответ.
        $arResult = $this->deliveriesResponse($orderObject, $shipmentsArray, $stores);

        // Важно передавать нестандартный контроллер, а кастомный ответ.
        return $arResult;
    }

    /**
     * Формирование ответа для ImShop.
     * 
     * @param \Bitrix\Sale\Order $orderObject       Объект заказа
     * @param array $shipmentsArray                 Массив доставок
     * @param array $stores                         Массив пунктов выдачи заказов.
     */
    private function deliveriesResponse(\Bitrix\Sale\Order $orderObject, array $shipmentsArray, array $stores): ?array
    {
        foreach ($shipmentsArray as $key => $shipmentObject) {
            $arResult['deliveries'][$key] = [
                'id'                    => strval($shipmentObject->getId()),
                'title'                 => $shipmentObject->getName(),
                'description'           => $shipmentObject->getDescription(),
                'type'                  => 'delivery',
                'price'                 => $orderObject->getDeliveryPrice(),
                'min'                   => 3,
                'timelabel'             => 'Скоро с вами свяжется менеджер.',
                'hasPickupLocations'    => $this->hasExtraServices($shipmentObject) ? true : false,
            ];
            // Добавляем 
            if($this->hasExtraServices($shipmentObject)){
                $stores = $stores[$shipmentObject->getId()];
                foreach ($stores as $arStores){
                    $arResult['deliveries'][$key]['locations'][] = [
                        'id'        =>  $arStores['ID'],
                        'title'     =>  $arStores['TITLE'],
                        'address'   =>  $arStores['ADDRESS'],
                        'city'      =>  $arStores['UF_CITY'],
                        'price'     =>  $orderObject->getDeliveryPrice(),
                        'min'       =>  3,
                        'lat'       =>  $arStores['GPS_N'],
                        'lon'       =>  $arStores['GPS_S'],
                    ];
                }
            }
        }
        return $arResult;
    }

    /**
     * Привязать доставку к объекту заказа.
     * 
     * @param \Bitrix\Sale\Order $orderObject
     * @param \Bitrix\Sale\Basket $basketObject
     * @param string $deliveryId
     */
    public static function setDeliveryById(\Bitrix\Sale\Order $orderObject, \Bitrix\Sale\Basket $basketObject, string $deliveryId)
    {
        $obShipmentCollection = $orderObject->getShipmentCollection();
        $obShipment = $obShipmentCollection->createItem(\Bitrix\Sale\Delivery\Services\Manager::getObjectById($deliveryId));
        $shipmentItemCollection = $obShipment->getShipmentItemCollection();
        foreach ($basketObject->getBasketItems() as $basketItem) {
            $item = $shipmentItemCollection->createItem($basketItem);
            $item->setQuantity($basketItem->getQuantity());
        }
    }

    /**
     * Получить все привязанные к заказу доставки.
     * 
     * @param \Bitrix\Sale\Order $order 
     * @return void|object
     */
    private function getAllAvailableDeliveriesFromOrder(\Bitrix\Sale\Order $order): ?object
    {
        // Получение коллекции доставок для заказа
        $shipmentCollection = $order->getShipmentCollection();

        foreach ($shipmentCollection as $shipmentObject) {
            // Проверка, что доставка доступна для заказа
            if ($shipmentObject->isSystem() || !$shipmentObject->isAllowDelivery()) {
                continue;
            }

            // Получение объекта доставки
            $deliveries[] = $shipmentObject->getDelivery();
        }

        return $deliveries;
    }

    /**
     * Получить коллекцию всех активных доставок.
     * 
     * @param array @arSelect
     * @param array @arFilter
     * 
     * @return object
     */
    private function getAllDeliveries(array $arSelect = ['*'], array $arFilter = ['ACTIVE' => 'Y']): ?object
    {
        $deliveryCollection = \Bitrix\Sale\Delivery\Services\Table::query()
            ->setSelect($arSelect)
            ->setFilter($arFilter)
            ->fetchCollection();
        return $deliveryCollection;
    }

    /**
     * Получить ID доставки по особенностям ImShop.
     * 
     * @param string $deliveryId
     * @return array
     */
    public static function getDeliveryIdbyImshop(string $deliveryId): ?array
    {
        $delivery = explode('/', $deliveryId);
        $delivery = [
            'TYPE' => $delivery[0],
            'ID'   => $delivery[1]
        ];
        return $delivery;
    }

    /**
     * Получить склады по строке города и без.
     * 
     * @param object    $deliveryObject        Объект доставки
     * @param string    $city                  Cтрока город
     * 
     * @return array
     */

    public static function getStoresByDelivery(object $shipmentObject, string $city): ?array
    {
        // Получем поля extraServices. Нас интересуют выбранные пункты самовывоза.
        $arStores[$shipmentObject->getId()] = \Bitrix\Sale\Delivery\ExtraServices\Manager::getStoresFields($shipmentObject->getId(), true)['PARAMS']['STORES'];
        // Получаем коллекцию пунктов самовывоза.
        $storesCollection[$shipmentObject->getId()] = Store::getActiveStoresCollection($arStores[$shipmentObject->getId()]);
        // Проходимся по каждому пункту самовывоза.
        foreach ($storesCollection[$shipmentObject->getId()] as $shipmentId => $storesObject) {
            // Нам нужен конкретно пункт самовывоза связанные с кодом города. А код города у нас записан в description.
            if ($city === $storesObject->getDescription()) {
                // Записываем если есть совпадения.
                $storesWithCode[$shipmentObject->getId()][$shipmentId] = $storesObject->collectValues();
            } else {
                // Записываем, если нет совпадений.
                $storesWithoutCode[$shipmentObject->getId()][$shipmentId] = $storesObject->collectValues();
            }
        }
        $stores = [
            'WIDTH_CODE'    => $storesWithCode,
            'WITHOUT_CODE'  => $storesWithoutCode
        ];
        return $stores;
    }

    /**
     * Имеет ли доставка экстрасервисы
     * 
     * @param $shipmentObject
     * @return bool
     */

    public static function hasExtraServices(object $shipmentObject): ?bool
    {
        if(\Bitrix\Sale\Delivery\ExtraServices\Manager::getStoresFields($shipmentObject->getId(), true)){
            return true;
        }
        else{
            return false;
        }
    }
}
