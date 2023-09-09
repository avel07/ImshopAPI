<?php

namespace Cube\Api\Controllers;

class Product extends BaseController
{

    private $fields = [];

    public const CATALOG_IBLOCK_ID  = 11;
    public const OFFERS_IBLOCK_ID   = 12;
    public const DEFAULT_SIZE_PAGE  = 20;
    
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
        ddebug($arResult);
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

    /**
     * Получить элементы инфоблока
     * 
     * @param int                           $iblockId       ID инфоблока
     * @param array                         $arSelect       Выборка полей, свойств
     * @param array                         $arFilter       Фильтрация
     * @param int                           $page           Номер страницы
     * @param int                           $size           Количество элементов на странице
     * @param Bitrix\Main\UI\PageNavigation $pageNavigation Объект навигации
     * @return array
     */
    public static function getIblockElementsAction(int $iblockId = self::CATALOG_IBLOCK_ID, array $select = [], array $filter = [], int $page = 1, int $size = self::DEFAULT_SIZE_PAGE, \Bitrix\Main\UI\PageNavigation $pageNavigation = null)
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        $result = [
            'ITEMS' => [],
            'PAGE'  => $page,
            'SIZE'  => $size,
            'COUNT' => 0,
            'TOTAL' => 0
        ];
        // Получаем объект и далее сущность инфоблока
        $iblockObject = \Bitrix\Iblock\Iblock::wakeUp($iblockId);
        $iblockEntity = $iblockObject->getEntityDataClass();

        if (!$iblockEntity) {
            $error = new \Bitrix\Main\Error('iblock ' . $iblockId . ' не найден');
            return null;
        }
        // Получение свойств из механизма единого управления свойствами.
        // @see https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=42&LESSON_ID=1986
        $properties = empty($select) ? (\Bitrix\Iblock\Model\PropertyFeature::getListPageShowPropertyCodes($iblockId, ['CODE' => 'Y']) ?? []) : [];

        // Pagintaion
        if ($pageNavigation && $pageNavigation instanceof \Bitrix\Main\UI\PageNavigation) {
            $pageNavigation->setPageSize($size);
            $pageNavigation->setCurrentPage($page);
        }

        // prepare limit
        $qLimit = $pageNavigation ? $pageNavigation->getLimit() : $size;
        $qOffset = $pageNavigation ? $pageNavigation->getOffset() : 0;

        // Получаем объекты элемента
        $iblockCatalogQuery = $iblockEntity::query()
            ->addSelect('NAME')
            ->addSelect('CODE')
            ->setLimit($qLimit)
            ->setOffset($qOffset);

        // Если свой select, разбираем на свойства и поля
        // Запрашиваем только поля для верной пагинации
        if ($select) {
            $properties = $select['PROPERTIES'] ?? [];
            unset($select['PROPERTIES']);
            $iblockCatalogQuery->setSelect(array_diff_key($select, array_flip($properties)));
        } else {
            $iblockCatalogQuery->setSelect(self::getElementEntityAllowedList());
        }

        if ($filter) {
            $iblockCatalogQuery->setFilter($filter);
        }

        $resultQuery = $iblockCatalogQuery->exec(); // Выполняем запрос
        $countTotal = $iblockCatalogQuery->queryCountTotal(); // Общее кол-во
        $elementCollection = $resultQuery->fetchCollection(); // Получаем коллекцию

        // Если коллекцию не получили
        if (!$elementCollection->getAll()) {
            return null;
        }

        // Узнаем тип и запрашиваем свойства
        $fillProperties = [];
        $propertyFieldsEntity = array_intersect_key((array) $elementCollection->entity->getFields(), array_flip($properties));
        /** @var \Bitrix\Iblock\ORM\Fields\PropertyReference $propertyField */
        foreach ($propertyFieldsEntity as $propertyField) {
            $iblockElementPropertyEntity = $propertyField->getIblockElementProperty();
            $valueEntity = $iblockElementPropertyEntity->getValueEntity();
            $valueFields = $valueEntity->getFields();

            // Поддержка типов
            if ($valueFields) {
                if (isset($valueFields['ITEM'])) {
                    $fillProperties[] = $propertyField->getName() . '.ITEM';
                } elseif (isset($valueFields['FILE'])) {
                    $fillProperties[] = $propertyField->getName() . '.FILE';
                } else {
                    $fillProperties[] = $propertyField->getName() . '.VALUE';
                }
            }
        }
        // Заполняем свойства
        if (!empty($fillProperties)) {
            $elementCollection->fill($fillProperties);
        }
        
        foreach ($elementCollection as $elementObject) {
            /** @var \Bitrix\Iblock\ORM\ValueStorageEntity */
            $item = [];
            $item = self::convertedFields(array_diff_key($elementObject->collectValues(), array_flip($properties)));

            $propertiesValues = array_intersect_key($elementObject->collectValues(), array_flip($properties));

            $propertiesResult = self::convertedProperties($propertiesValues);
            if (!empty($propertiesResult)) {
                $item['PROPERTIES'] = $propertiesResult;
            }
            $result['ITEMS'][] = $item;
        }
        $result['COUNT'] = count($result['ITEMS']);
        $result['TOTAL'] = (int) $countTotal;
        return $result;
    }

    /**
     * Конвертация типов полей
     *
     * @param array $fields
     * @return void
     */
    private function convertedFields(array $fields)
    {
        $basePath = \Bitrix\Main\Engine\UrlManager::getInstance()->getHostUrl();
        foreach ($fields as $key => $value) {
            if ($key == 'PREVIEW_PICTURE') {
                $fields[$key] = $value ? $basePath . \CFile::GetPath($value) : null;
                continue;
            }
        }
        return $fields;
    }
    
    /**
     * Конвертация типов свойств
     *
     * @param array $propertiesCollection
     * @return void
     */
    private function convertedProperties(array $propertiesCollection)
    {
        $basePath = \Bitrix\Main\Engine\UrlManager::getInstance()->getHostUrl();
        foreach ($propertiesCollection as &$property) {
            $propertyEntity = $property->entity ? $property->entity : null;
            if($propertyEntity && $propertyEntity->hasField('ITEM')){
                if ($property instanceof \Bitrix\Main\ORM\Objectify\Collection) {
                    $property = array_map(function ($propertyObject) {
                        return $propertyObject->get('ITEM')->collectValues();
                    }, $property->getAll());
                } elseif ($property->get('ITEM')) {
                    $property = $property->get('ITEM')->collectValues();
                }
            } elseif ($propertyEntity && $propertyEntity->hasField('FILE')) { 
                if ($property instanceof \Bitrix\Main\ORM\Objectify\Collection) {
                    $property = array_map(function ($propertyObject) use ($basePath) {
                        if($productObject && $productObject->get('FILE')){
                            $fileValues = $propertyObject->get('FILE')->collectValues();
                            return $basePath . \CFile::GetFileSRC($fileValues);
                        }
                    }, $property->getAll());
                } elseif ($property->get('FILE')) {
                    if($property->get('FILE')){
                        $fileValues = $property->get('FILE')->collectValues();
                        $property = $basePath . \CFile::GetFileSRC($fileValues);
                    }
                }
            }
            else {
                // Обычное скалярное значение
                if ($property && $property instanceof \Bitrix\Main\ORM\Objectify\Collection) {
                    $property = array_map(function ($propertyObject) use ($basePath) {
                        if($productObject->get('VALUE')){
                            return $propertyObject->get('VALUE');
                        }
                    }, $property->getAll());
                }
                elseif ($property && $property->get('VALUE')){
                    $property = $property->get('VALUE');
                }
            }
        }
        unset($property);
        return $propertiesCollection;
    }

    /**
     * Список дефолтных полей элемента
     *
     * @return array
     */
    static function getElementEntityAllowedList(): array
    {
        return [
            'ID',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'PREVIEW_PICTURE',
            'PREVIEW_TEXT',
        ];
    }
}
