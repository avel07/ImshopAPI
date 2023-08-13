<?php

namespace Cube\Api\Controllers;

class Store extends BaseController
{

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('catalog');
        parent::__construct($request);
    }

    /**
     * Получение списка активных пунктов самовывоза.
     *
     * @return object|null 
     */
    public static function getActiveStoresCollection(): ?object
    {
        $stores = \Bitrix\Catalog\StoreTable::query()
            ->addSelect('ID')
            ->addSelect('TITLE')
            ->addSelect('ADDRESS')
            ->addSelect('DESCRIPTION')
            ->addSelect('PHONE')
            ->addSelect('SCHEDULE')
            ->addSelect('GPS_N')
            ->addSelect('GPS_S')
            ->addSelect('UF_*')
            ->setFilter(['ACTIVE' => 'Y', 'ISSUING_CENTER' => 'Y'])
            ->setOrder([
                'SORT' => 'asc',
                'ID'   => 'asc'
            ])
            ->setCacheTtl(86400)
            ->cacheJoins(true)
            ->fetchCollection();
        return $stores;
    }
    /**
     * Получить пункты самовывоза по городу.
     * 
     * @param string $city
     * @return object
     */
    public static function getStoresByCity(string $city = null): ?object
    {
        $stores = \Bitrix\Catalog\StoreTable::query()
            ->addSelect('*')
            ->addSelect('UF_*')
            ->addFilter('UF_CITY', $city)
            ->addFilter('ACTIVE', 'Y')
            ->addFilter('ISSUING_CENTER', 'Y')
            ->setOrder([
                'SORT' => 'asc',
                'ID'   => 'asc'
            ])
            ->setCacheTtl(86400)
            ->cacheJoins(true)
            ->fetchCollection();
        return $stores;
    }

    /**
     * Получить товары в пунктах самовывоза по массиву ID складов и массиву ID товаров.
     * 
     * @return object
     */
    public static function getProductStoresByIds(array $storesIds = [], array $productIds = []): ?object
    {
        $stores = \Bitrix\Catalog\StoreProductTable::query()
            ->addFilter('PRODUCT_ID', $productIds)
            ->addFilter('STORE_ID', $storesIds)
            ->addSelect('AMOUNT')
            ->addSelect('STORE.TITLE')
            ->addSelect('PRODUCT.IBLOCK_ELEMENT.NAME')
            ->addSelect('PRODUCT_ID')
            ->addOrder('STORE_ID')
            ->setCacheTtl(86400)
            ->cacheJoins(true)
            ->fetchCollection();
        return $stores;
    }
}
