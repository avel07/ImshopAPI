<?php

namespace Cube\Api\Controllers;

class Basket extends BaseController
{

    public const PARAMS = [
        'IMSHOP_BASKET_ITEM_CODE'   => 'appId'
    ];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        parent::__construct($request);
    }

    /**
     * Добавить товар в корзину
     *
     * @param string|int $productId
     * @param string $quantity
     * 
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
     * 
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
     * 
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
     * @param \Bitrix\Sale\Basket $basketObject
     * @return object
     */
    public static function setDiscountByBasket(\Bitrix\Sale\Basket $basketObject): ?object
    {
        $basketDiscount = \Bitrix\Sale\Discount::loadByBasket($basketObject);
        $basketDiscount->calculate();
        $discountResult = $basketDiscount->getApplyResult(true);
        return $basketObject->applyDiscount($discountResult);
    }
}
