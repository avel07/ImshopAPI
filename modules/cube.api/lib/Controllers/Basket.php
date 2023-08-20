<?php

namespace Cube\Api\Controllers;

class Basket extends BaseController
{

    private $fields = [];

    public const PARAMS = [
        'IMSHOP_BASKET_ITEM_CODE'   => 'appId'
    ];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
        parent::__construct($request);
    }


    /**
     * Action
     * Расчет корзины. Так как сюда приходят еще и доставки и оплаты, то по сути это тот же самый заказ, 
     * только без сохранения и с отдачей доп данных.
     */
    public function calculateAction()
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);

        User::getUserById($fields['externalUserId']) ? $userId = $fields['externalUserId'] : $userId = User::getAnonimusUserId();

        // Если UserId не получено, то выводим ошибку, полученную из самой переменной.
        if (!is_int($userId)) {
            $this->addError(new \Bitrix\Main\Error('Ошибка пользователя - ' . $userId, 400));
            return null;
        }
        // Экземпляр заказа.
        $orderObject = Order::createOrder(\Cube\Api\Application::APP_PARAMS['SITE_ID'], $userId);

        // Экземпляр корзины.
        $basketObject = Basket::createBasket(\Cube\Api\Application::APP_PARAMS['SITE_ID']);

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
            $arBasketFields = $this->basketSetFields($arItem['privateId'], $arItem['quantity'], $arItem['id']);

            // Добалвяем Fields в объект корзины. Этот метод помогает избежать мучения с проверками. Все в методе.
            $addProductToBasket = $this->addItem($basketObject, $arBasketFields, ['SITE_ID' => \Cube\Api\Application::APP_PARAMS['SITE_ID'], 'USER_ID' => $userId]);
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

        // Привязываем конкретную доставку к заказу.
        if ($fields['deliveryId']) {
            Deliveries::setDeliveryById($orderObject, $basketObject, $fields['deliveryId']);
        }
        // Привязываем конкретную оплату к заказу.
        if ($fields['paymentId']) {
            Payments::setPayment($orderObject, $fields['paymentId']);
        }

        // Очищаем сразу все ранее привязанные купоны. Могут привязываться к user, fuser, sessid.
        \Bitrix\Sale\DiscountCouponsManager::clear(true);

        // Если есть промокод. Применяем его.
        if ($fields['promocode']) {
            // Если промокода не существует.
            if (!\Bitrix\Sale\DiscountCouponsManager::isExist($fields['promocode'])) {
                $this->addError(new \Bitrix\Main\Error('Ошибка применения промокода. Промокода - ' . $fields['promocode'] . ' не существует', 400));
            } else {
                // Применяем купон к заказу.
                $this->setCouponToOrder($orderObject, $basketObject, $fields['promocode']);
            }
        }
        $arResult = $this->calculateActionResponse($orderObject, $productsCollection, $fields);
        return $arResult;
    }


    /**
     * 
     * @param \Bitrix\Sale\Order $orderObject   Объект заказа
     * @param $productCollection
     * @return array
     */
    private function calculateActionResponse(\Bitrix\Sale\Order $orderObject, object $productsCollection = null, array $fields = []): ?array
    {
        $discountList = $this->getDiscountListByOrder($orderObject);
        $basketObject = $orderObject->getBasket();
        $basketItems = $basketObject->getBasketItems();
        foreach($basketItems as $key => $basketItem){
            // ID продукта с таблицы продуктов..
            $productObject = $productsCollection->getAll()[$key];
            // Массив товара, что пришел с хука.
            $fieldItem = $fields['items'][$key];
            // ID скидок, примененных к товару.
            $discountIds = $discountList[$basketItem->getProductId()];
            
            if($productObject->getQuantity() < $fieldItem['quantity']){
                $error = 'Ошибка количества. '.$fieldItem['quantity'].' шт. - недоступное количество. Доступно - '.$productObject->getQuantity().' шт.';
            }
            $arItems[] = [
                'name'                          => $basketItem->getField('NAME'),   // Имя товара.
                'id'                            => $basketItem->getProductId(),     // ID товара.
                'price'                         => $basketItem->getPrice(),         // Цена товара с учетом скидки.
                'discount'                      => $basketItem->getDiscountPrice(), // Скидка.
                'quantity'                      => $basketItem->getQuantity(),      // Количество.
                'subtotal'                      => $basketItem->getBasePrice(),     // Цена без скидки.
                'bonuses'                       => null,                            // Бонусы.
                'addons'                        => null,                            // Доп товары.
                'itemKitId'                     => null,                            // ID товарного набора (пока такого нет).
                'appliedDiscounts'              => $discountIds,                    // ID примененной скидки.
                'error'                         => $error,                          // Ошибка, если есть.
                'notice'                        => null,                            // Произвольный текст рядом с товаром.
                'unavailableDeliveryMessage'    => null,                            // Если есть проблема с доставкой.
                'warehouseId'                   => null,                            // ID склада .
            ];
            unset($error, $fieldItem, $discountIds, $productObject);
        }
        $arResult = [
            'totalPrice'            => $basketObject->getPrice(), // Цена с учетом скидок
            'appliedPromocode'      => $this->getPromocodeByOrder($orderObject) ?? null, // Промокод, если применен.
            "skipPayment"           => false,       // Пропустить оплату в заказе (false полагаю).
            'discount'              => $basketObject->getBasePrice() - $basketObject->getPrice(), // Скидка. Высчитываем вручную.
            'items'                 => $arItems,    // Массив элементов корзины.
            'bonuses'               => null,        // Массив бонусов для списания.
            'deliveryGroupsBonuses' => null,        // Бонусы разделенной корзины.
            'giftCards'             => null,        // Подарочные карты.
            'availablePromocodes'   => null,        // Доступные промокоды.
            'extraServices'         => null,        // Дополнительные услуги.
            'eula'                  => null         // Дополнительные пользовательские соглашения.

        ];
        return $arResult;
    }
    /**
     * Добавить товар в корзину
     *
     * @param string|int $productId
     * @param string $quantity
     * @return void|object
     */
    public static function addItem(\Bitrix\Sale\Basket $basketObject, array $arBasketFields = [], array $context = ['SITE_ID' => \Cube\Api\Application::APP_PARAMS['SITE_ID']])
    {
        $addProductToBasket = \Bitrix\Catalog\Product\Basket::addProductToBasket($basketObject, $arBasketFields, $context);
        return $addProductToBasket;
    }

    /**
     * 
     * @param $siteId
     * @return object
     */
    public static function createBasket(string $siteId = 's1'): ?object
    {
        return \Bitrix\Sale\Basket::create($siteId);
    }

    /** 
     * Получить FuserId по ID пользователя.
     * 
     * @param $userId
     * @return string|int
     */
    public static function getFuserId(string $userId): ?string
    {
        return \Bitrix\Sale\Fuser::getIdByUserId($userId);
    }
    /**
     * Создать кастомное свойство корзины.
     * 
     * @param string $name
     * @param string $code
     * @param string $value
     * @return object
     */
    public static function createCustomProperty(string $name = '', string $code = '', string $value = ''): ?object
    {
        $basketProperty = new \Bitrix\Sale\BasketPropertyItem([
            'NAME' => $name,
            'CODE' => $code,
            'VALUE' => $value,
        ]);
        return $basketProperty;
    }

    /**
     * Генерирует массив привязки свойств к корзине.
     * 
     * @param string $itemId
     * @param string $quantity
     * @param string $imShopItemId
     * @return array
     */
    public static function basketSetFields(string $itemId, string $quantity, string $imShopItemId): ?array
    {
        $arBasketFields = [
            'PRODUCT_ID'    => $itemId,
            'CURRENCY'      => Order::PARAMS['CURRENCY'],
            'QUANTITY'      => $quantity,
            'PROPS'         => [0 => ["NAME" => "id товара в ImShop", "CODE" => Basket::PARAMS['IMSHOP_BASKET_ITEM_CODE'], "VALUE" => $imShopItemId]]
        ];
        return $arBasketFields;
    }

    /**
     * Получить и применить все маркетинговые правила корзины.
     * 
     * @param \Bitrix\Sale\Basket $basketObject     Объект корзины
     * @return object
     */
    public static function setDiscountByBasket(\Bitrix\Sale\Basket $basketObject): ?object
    {
        $basketDiscount = \Bitrix\Sale\Discount::loadByBasket($basketObject);
        $basketDiscount->calculate();
        $discountResult = $basketDiscount->getApplyResult(true);
        return $basketObject->applyDiscount($discountResult);
    }

    /**
     * Применить купон к заказу, произвести калькуляцию и пересчет.
     * 
     * @param \Birtix\Sale\Order    $orderObject    Объект заказа
     * @param \Bitrix\Sale\Basket   $basketObject   Объект Корзины
     * @param string                $coupon         Купон
     * @return void|bool
     */
    public static function setCouponToOrder(\Bitrix\Sale\Order $orderObject, \Bitrix\Sale\Basket $basketObject, string $coupon = null): ?bool
    {
        \Bitrix\Sale\DiscountCouponsManager::init(
            \Bitrix\Sale\DiscountCouponsManager::MODE_ORDER,
            [
                "userId" => $orderObject->getUserId(),
                "orderId" => $orderObject->getId()
            ],
            true
        );
        \Bitrix\Sale\DiscountCouponsManager::add($coupon);
        return true;
    }

    /**
     * Пересчет скидок объекта корзины и объекта заказа. 
     * @param \Birtix\Sale\Order    $orderObject    Объект заказа
     * @param \Bitrix\Sale\Basket   $basketObject   Объект Корзины
     * @return void|bool
     */
    public static function recalculateOrder(\Bitrix\Sale\Order $orderObject, \Bitrix\Sale\Basket $basketObject): ?bool
    {
        $discounts = $orderObject->getDiscount();
        $discounts->setApplyResult([]);
        $discounts->setUseMode(1);
        $basketObject->refreshData(["PRICE", "COUPONS"]);
        $discounts->calculate();
        $discounts->setOrderRefresh(true);
        return true;
    }
    /**
     * Получить примененные маркетинговые правила корзины заказа. 
     * @param \Bitrix\Sale\Order    $orderObject    Объект заказа
     * @return array    ID товара => массив правил к товару.
     */
    public static function getDiscountListByOrder(\Bitrix\Sale\Order $orderObject): ?array
    {
        // Пересчитываем все для точности массива.
        self::recalculateOrder($orderObject, $orderObject->getBasket());
        $discounts = $orderObject->getDiscount();
        $arDiscounts = $discounts->getApplyResult();
        foreach ($arDiscounts['ORDER'] as $key => $arDiscount) {
            if ($arDiscount['RESULT']['BASKET']) {
                foreach ($arDiscount['RESULT']['BASKET'] as $arBasketDiscount) {
                    $arDiscountItems[$key][$arBasketDiscount['PRODUCT_ID']] = $arDiscount['DISCOUNT_ID'];
                }
            }
        }
        // Сгруппируем по ID товаров.
        $arDiscountItems = array_reduce($arDiscountItems, function($carry, $item) {
            foreach ($item as $key => $value) {
                $carry[$key][] = $value;
            }
            return $carry;
        }, []);
        return $arDiscountItems;
    }
    /**
     * Получить примененные купоны к заказу.
     * @param \Bitrix\Sale\Order    $orderObject    Объект заказа
     * @return null|string
     */
    public static function getPromocodeByOrder(\Bitrix\Sale\Order $orderObject)
    {
        // Пересчитываем все для точности массива.
        self::recalculateOrder($orderObject, $orderObject->getBasket());
        $discounts = $orderObject->getDiscount();
        $arDiscounts = $discounts->getApplyResult();
        foreach ($arDiscounts['ORDER'] as $arDiscount) {
            if ($arDiscount['COUPON_ID']) {
                return $arDiscount['COUPON_ID'];
            }
        }
    }
}
