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
    public static function getActiveStoresCollection(array $ids = []): ?object
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
}
