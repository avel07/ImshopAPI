<?php

namespace Cube\Api\Controllers;

class Location extends BaseController
{
    public const COUNTRY_CODE_RU = '0000028023';
    public const COUNTRY_CODE_UA = '0000000364';
    public const COUNTRY_CODE_KZ = '0000000276';
    public const COUNTRY_CODE_BY = '0000000001';

    public const MSK_CITY = 'Москва';

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        parent::__construct($request);
    }


    /**
     * Получение списока городов по строке города и стране. 
     *
     * @param string $code      код страны
     * @param string $city      название города
     * @return array|null 
     */
    public static function getCitiesAction(string $code = self::COUNTRY_CODE_RU, string $city = self::MSK_CITY): ?array
    {
        $result = [];

        // Разрешенные страны для получения городов
        $allowCountries = [
            self::COUNTRY_CODE_RU,
            self::COUNTRY_CODE_UA,
            self::COUNTRY_CODE_KZ,
            self::COUNTRY_CODE_BY
        ];

        if (!in_array($code, $allowCountries)) {
            return null;
        }

        // Обязательно включаем кеш. Запросы часто будут по одному и тому же городу.
        $location = \Bitrix\Sale\Location\LocationTable::query()
            ->where('TYPE.CODE', 'CITY')
            ->where('NAME.LANGUAGE_ID', 'ru')
            ->where('PARENT.NAME.LANGUAGE_ID', 'ru')
            ->where('PARENTS.NAME.LANGUAGE_ID', 'ru')
            ->where('PARENTS.TYPE.CODE', 'COUNTRY')
            ->where('PARENTS.CODE', $code)
            ->where('NAME.NAME', $city)
            ->addSelect('ID')
            ->addSelect('CODE')
            ->addSelect('NAME.NAME')
            ->addSelect('PARENT.NAME')
            ->addSelect('PARENT.TYPE.CODE')
            ->addSelect('PARENTS.NAME')
            ->setOrder([
                'SORT' => 'asc',
                'ID'   => 'asc'
            ])
            ->setCacheTtl(86400)
            ->cacheJoins(true)
            ->fetchObject();
        if ($location !== null) {
            $parentType = $location->get('PARENT')->get('TYPE')->get('CODE');
            // Верхние уровни
            $parents = [];
            if ($parentType !== 'COUNTRY') {
                $parents[] = $location->get('PARENT')->get('NAME')->get('NAME');
            }
            // Страна
            $parents[] = $location->get('PARENTS')->get('NAME')->get('NAME');

            $result = [
                'CODE' => $location->get('CODE'),
                'NAME' => $location->get('NAME')->get('NAME'),
                'PARENT' => implode(', ', $parents)
            ];
        } else {
            return null;
        }

        return $result;
    }

    /**
     * Получить страны
     *
     * @return array|null
     */
    public function getCountiesAction(): ?array
    {
        \Bitrix\Main\Loader::includeModule('sale');

        $locations = \Bitrix\Sale\Location\LocationTable::query()
            ->where('TYPE.CODE', 'COUNTRY')
            ->where('NAME.LANGUAGE_ID', 'ru')
            ->addSelect('ID')
            ->addSelect('CODE')
            ->addSelect('NAME.NAME')
            ->setOrder([
                'SORT' => 'asc',
            ])
            ->setCacheTtl(86400)
            ->cacheJoins(true)
            ->fetchCollection();

        if ($locations !== null && $locations->count() > 0) {
            foreach ($locations as $location) {
                $result[] = [
                    'code' => $location->get('CODE'),
                    'name' => $location->get('NAME')->get('NAME'),
                ];
            }
        } else {
            $this->addError(new \Bitrix\Main\Error('Ошибка при получении местоположений', 400));
            return null;
        }

        return $result;
    }
}
