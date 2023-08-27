<?php

namespace Cube\Api\Controllers;

class User extends BaseController
{

    private $fields = [];
    
    // Ограничение на количество заказов в выборке.
    public const ORDERS_LIMIT = 10;

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        parent::__construct($request);
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
    }


    /**
     * Action
     * Список заказов пользователя. (ограничено 10 шт.)
     */
    public function ordersListAction()
    {
        \Bitrix\Main\Loader::includeModule('sale');
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);

        if(!$this->getUserById($fields['userIdentifier'])){
            $this->addError(new \Bitrix\Main\Error('Пользователя '.$fields['userIdentifier'].' не существует', 400));
            return null;            
        }

        // Коллекция заказов
        $ordersCollection = Order::getOrdersByUserId($fields['userIdentifier']);
        if(empty($ordersCollection->getAll())){
            $this->addError(new \Bitrix\Main\Error('У пользователя '.$fields['userIdentifier'].' заказов нет', 400));
            return null;
        }

        // Список ID заказов
        $orderIds = $ordersCollection->getIdList();

        // Коллекция свойств заказов 
        $propertyCollection = Order::getOrdersPropertiesByOrders($orderIds);
        
        // Коллекция корзин
        $basketArray = Basket::getBasketsByOrderIds($orderIds);

        // Сгруппируем товары корзины по ID инфоблока для оптимизации и определения ТП или Товар.
        $iblockItems = [];
        foreach ($basketArray as $basketItem) {
            $iblockId = $basketItem['IBLOCK_ID'];
            $iblockItems[$iblockId][] = $basketItem['PRODUCT_ID'];
        }

        // Базовый URL.
        $basePath = \Bitrix\Main\Engine\UrlManager::getInstance()->getHostUrl();
        // Проходимся по ID инфоблоков.
        foreach ($iblockItems as $iblockId => $productIds) {
            $catalogItemsResult = Product::getIblockElementsAction(
                $iblockId,
                [],
                ['ID' => $productIds],
            );
            // Особенности структуры - Ссылку на картинку получать из товара и привязывать к ТП.
            foreach($catalogItemsResult['ITEMS'] as $key => $catalogItemResult){
                if($catalogItemResult['PROPERTIES']['CML2_LINK']){
                    $previewPicture = Product::getProduct(['ID' => $catalogItemResult['PROPERTIES']['CML2_LINK']], ['IBLOCK_ELEMENT.PREVIEW_PICTURE']);
                    $previewPicture = $previewPicture->getByPrimary($catalogItemResult['PROPERTIES']['CML2_LINK'])->getIblockElement()->getPreviewPicture();
                    $catalogItemsResult['ITEMS'][$key]['PREVIEW_PICTURE'] = $basePath . \CFile::GetPath($previewPicture);
                }
            }
            // Делаем ключи по ID товара для дальнейшей привязки.
            if ($catalogItemsResult && isset($catalogItemsResult['ITEMS'])) {
                $catalogItemsData[] = array_combine(array_column($catalogItemsResult['ITEMS'], 'ID'), $catalogItemsResult['ITEMS']);
            }
        }
        // Правильно разгруппировываем, чтобы не было привязок к инфоблоку
        $iblockResult = array_reduce($catalogItemsData, fn($carry, $item) => $carry + $item, []);

        if ($iblockResult) {
            // Корзина является массивом, поэтому записываем сюда все товары со свойствами.
            foreach ($basketArray as &$item) {
                $productId = $item['PRODUCT_ID'];
                $itemData  = $iblockResult[$productId];
                $item['IBLOCK_FIELDS'] = $itemData ?? null;
            }
            unset($item);
        }
        $arResult = $this->ordersListActionResponse($ordersCollection, $propertyCollection, $basketArray);
        return $arResult;
    }

    /**
     * @param $basketCollection     ORM коллекция заказов
     * @param array $basketArray    Массив корзин, привязанных к заказу. Тут уже есть все товары со свойствами.
     * 
     * @return array
     */
    private function ordersListActionResponse(\Bitrix\Sale\Internals\EO_Order_Collection $ordersCollection, \Bitrix\Sale\Internals\EO_OrderPropsValue_Collection $propertyCollection, array $basketArray = [])
    {
        // Подготавливаем свойства заказов.
        foreach ($propertyCollection as $propertyObject){
            $propertyValues[$propertyObject->getOrderId()][$propertyObject->getCode()] = $propertyObject->collectValues();
            unset($propertyObject);
        }

        // Подготавливаем корзины заказов.
        foreach ($basketArray as $item) {
            $orderID = $item['ORDER_ID'];
            if (!$basketOrderArray[$orderID]) {
                $basketOrderArray[$orderID] = [];
            }
            $basketOrderArray[$orderID][] = $item;
        }

        foreach ($ordersCollection as $orderObject) {
            // Свойства конкретного заказа.
            $orderProperties = $propertyValues[$orderObject->getId()];

            // Корзина конкретного заказа.
            $basketOrder = $basketOrderArray[$orderObject->getId()];

            // Информация о заказе.
            $orderData = [
                'id'                    => $orderObject->getId(),
                'publicId'              => $orderObject->getAccountNumber(),
                'status'                => $orderObject->getStatus()->getName(),
                'statusMessage'         => null,    // Подробное описание статуса заказа
                'price'                 => $orderObject->getPrice(),
                'deliveryPrice'         => $orderObject->getPriceDelivery(),
                'itemsPrice'            => $orderObject->getPrice() - $orderObject->getDiscountValue(),
                'createdOn'             => $orderObject->getDateInsert()->getTimestamp(),
                'receiptUrl'            => null,    // Ссылка на PDF чек. (у нас этого нет. Это есть только в платежке)
                'updatedOn'             => $orderObject->getDateUpdate()->getTimestamp(),
                'inProgress'            => true,    // Активность заказа (всегда true)
                'usedBonuses'           => null,    // Кол-во использованных бонусов.
                'appliedDiscount'       => $orderObject->getDiscountValue(),  // Примененная скидка - тут уточнить ID скидки или что?
                'retryPaymentMethod'    => $orderObject->getPayment()->getId(),
                'cancellable'           => false,   // bool флаг для отмены заказа
                'trackingUrl'           => null,    // Трек ссылка
                'publicDeliveryDetails' => [        // Опциональные данные для доставки.
                    'title'             => $orderObject->getShipment()->getDeliveryName(),
                    'details'           => 'Описание доставки',
                    'buyerName'         => $orderProperties['FIO']['VALUE'],
                    'buyerEmail'        => $orderProperties['EMAIL']['VALUE'],
                    'buyerPhone'        => $orderProperties['PHONE']['VALUE'],
                    'deliveryDate'      => null,    // ожидаемая дата доставки в формате YYYY-MM-DD. Такое нам не приходит
                    'deliveryTime'      => null,    // Человеческий формат времени доставки. Такое тоже не приходит
                    'trackingUrl'       => null,    // Ссылка на трекинг
                ],
                'publicPaymentDetails'  => [        // Данные по оплате
                    'paid'              => $orderObject->getPayed(),
                    'paymentComment'    => null,    // Комментарий к платежу
                    'receiptUrl'        => null,    // Ссылка на чек. Такое не приходит.
                ],
                'ratingRequired'        => false,   // bool флаг нужно ли брать оценку с юзера?
                'rating'                => null,    // Рейтинг с хука оценки качества обслуживания
                'ratingComment'         => null     // Комментарий с хука оценки качества обслуживания
            ];
            
            // Информация о товарах заказа
            foreach ($basketOrder as $basketOrderItem){
                $orderData['items'][] = [
                    'privateId'             => $basketOrderItem['PRODUCT_ID'],
                    'configurationId'       => null,  // Идентификатор товарного предложения. По идее не нужен. 
                    'name'                  => $basketOrderItem['NAME'],
                    'image'                 => $basketOrderItem['IBLOCK_FIELDS']['PREVIEW_PICTURE'],
                    'price'                 => $basketOrderItem['BASE_PRICE'],
                    'quantity'              => $basketOrderItem['QUANTITY'],
                    'discount'              => $basketOrderItem['DISCOUNT_PRICE'],
                    'subtotal'              => $basketOrderItem['PRICE'],
                    'removed'               => false // Если товар удален из корзины заказа. К нам такое не приходит.
                ];
            }
            $arResult[] = $orderData;
        }
        return $arResult;
    }
    /**
     * Получить ID юзера.
     *
     * @param string|null $id
     * @param string $phone
     * @param string $email
     * @return void|string
     */
    public static function generateUserId($id, string $phone = '', string $email = ''): ?int
    {
        $phone = self::normalizePhone($phone);
        if (!empty($id)) {
            $userId = self::getUserById($id);
            if (!is_int($userId)) {
                $userId = self::createUser($phone, $email);
            }
        } else {
            $userId = self::getUserByPhone($phone);
            if (!is_int($userId)) {
                $userId = self::createUser($phone, $email);
            }
        }
        return $userId;
    }

    /**
     * Получить группу пользователя.
     *
     * @param string $id
     * @return void|object
     */
    public static function getUserGroups(string $userId): ?object
    {
        $userGroupObject = \Bitrix\Main\UserGroupTable::query()
            ->addFilter('USER_ID', $userId)
            ->addSelect('GROUP_ID')
            ->fetchCollection();
        return $userGroupObject;
    }

    /**
     * Регистрируем пользователя по номеру телефона.
     *
     * @param string $phone
     * @param string $email
     * @return int|string
     */
    public static function createUser($phone, $email)
    {
        $password = \Bitrix\Main\Security\Random::getString(6, true);
        $USER = new \CUser;
        $arUserFields = [
            "LOGIN"             => $phone,
            "PASSWORD"          => $password,
            "CONFIRM_PASSWORD"  => $password,
            "EMAIL"             => $email,
            "ACTIVE"            => "Y",
            "PHONE_NUMBER"      => $phone
        ];
        $newUserID = $USER->add($arUserFields);
        if ($newUserID) {
            return $newUserID;
        } else {
            return $USER->LAST_ERROR;
        }
    }

    /**
     * Обновляем данные пользователя.
     * 
     * @param int $userId
     * @param string $phone
     * @param string $email
     * @return int|string
     */
    public static function updateUser(int $userId, string $phone, string $email, string $name)
    {
        $USER = new \CUser;
        $arUserFields = [
            "LOGIN"             => $phone,
            "EMAIL"             => $email,
            "ACTIVE"            => "Y",
            "PHONE_NUMBER"      => $phone,
            "NAME"              => $name
        ];
        if ($USER->Update($userId, $arUserFields)) {
            return $userId;
        } else {
            return $USER->LAST_ERROR;
        }
    }


    /**
     * Проверяет на существование юзера по ID, отдает ID, если найден.
     *
     * @param string $id
     * @return void|array
     */
    public static function getUserById(string $id = '')
    {
        $userId = \Bitrix\Main\UserTable::query()
            ->addFilter('ID', $id)
            ->addSelect('ID')
            ->fetchObject();
        $userId ? $userId = $userId->getId() : $userId = null;
        return $userId;
    }

    /**
     * Проверяет на существование юзера по телефону, отдает ID, если найден.
     *
     * @param string $phoneNumber
     * @return void|array
     */
    public static function getUserByPhone(string $phoneNumber)
    {
        $userId = \Bitrix\Main\UserPhoneAuthTable::query()
            ->addFilter('PHONE_NUMBER', $phoneNumber)
            ->addSelect('USER_ID')
            ->fetchObject();
        $userId ? $userId = $userId->getUserId() : null;
        return $userId;
    }

    /**
     * Нормализует формат телефона, пригодный для регистрации, авторизации, поиска.
     *
     * @param string $phoneNumber
     * @return string
     */
    public static function normalizePhone(string $phoneNumber): ?string
    {
        return \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneNumber);
    }

    /**
     * Проверяет номер телефона на валидность.
     *
     * @param string $phoneNumber
     * @return bool
     */
    public static function checkPhone(string $phoneNumber): ?bool
    {
        if (!\Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($phoneNumber)->isValid()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Генерирует анонимный UserId
     *
     * @return int
     */
    public static function getAnonimusUserId(): ?int
    {
        $anonimusUserId = \CSaleUser::GetAnonymousUserID();
        return $anonimusUserId;
    }
}
