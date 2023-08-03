<?php

namespace Cube\Api\Controllers;

class Product extends BaseController
{

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('catalog');
        parent::__construct($request);
    }

    /**
     * Получить объект товара.
     *
     * @param string $arFilter
     * @param array $arSelect
     * @return object
     */
    public static function getProduct(array $arFilter = [''], array $arSelect = ['']): ?object
    {
        $productsCollection = \Bitrix\Catalog\ProductTable::query()
            ->setFilter($arFilter)
            ->setSelect($arSelect)
            ->fetchCollection();
        return $productsCollection;
    }

    /**
     * Получить количество товара.
     *
     * @param string $productId
     * @return string
     */
    public static function getQuantity(string $productId): ?string
    {
        $productObject = \Bitrix\Catalog\ProductTable::query()
            ->addFilter('ID', $productId)
            ->addSelect('QUANTITY')
            ->fetchObject();
        return $productObject->getQuantity();
    }
}
