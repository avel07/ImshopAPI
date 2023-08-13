<?php

namespace Cube\Api\Controllers;

class Product extends BaseController
{

    private $fields = [];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('catalog');
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
        parent::__construct($request);
    }


    /**
     * Action
     * Получить количество товара на активных складах. Приходит массив ID товаров и строка города.
     * @return array
     */
    public function availabilityAction(): ?array
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);
        // Коллекция складов по городу.
        $storesCollection = Store::getStoresByCity($fields['city']);
        if(empty($storesCollection->getAll())){
            // Если отображать все склады.
            // $storesCollection = Store::getActiveStoresCollection();
            $this->addError(new \Bitrix\Main\Error('В данном городе нет складов самовывоза.', 400));
            return null;
        }
        // Получем ID складов, которые уже отфильтрованы по городу.
        foreach($storesCollection as $storeObject){
            $storesIds[] = $storeObject->getId();
        }

        // Получаем кол-во товаров по ID города и ID товаров.
        $storesProductCollection = Store::getProductStoresByIds($storesIds, $fields['configurationIds']);

        $arResult = $this->availabilityActionResponse($storesCollection, $storesProductCollection);
        return $arResult;
    }

    /**
     * Формирование ответа для кол-ва товара на активных складах.
     * 
     * @param object $storesCollection          Коллекция складов.
     * @param object $storesProductCollection   Коллекция товаров в складах.
     * @return array
     */
    private function availabilityActionResponse(object $storesCollection, object $storesProductCollection): ?array
    {
        // Записываем информацию по складам
        foreach ($storesCollection as $storeObject){
            $arResult['warehouses'][] = [
                'warehouseId'   => $storeObject->getId(),
                'name'          => $storeObject->getTitle(),
                'address'       => $storeObject->getAddress(),
                'city'          => $storeObject->get('UF_CITY'),
                'lat'           => $storeObject->get('GPS_N'),
                'lon'           => $storeObject->get('GPS_S'),
                'online'        => true,
            ];
        }
        // Записываем информацию по товарам на этих складах.
        foreach ($storesProductCollection as $storeProductObject){
            $arResult['availability'][] = [
                'id'            => $storeProductObject->getProductId(),
                'warehouseId'   => $storeProductObject->getStore()->getId(),
                'remark'        => $storeProductObject->getStore()->getDescription(),
                'quantity'      => $storeProductObject->getAmount()
            ];
        }
        return $arResult;
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
     * Получить общее количество товара.
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
