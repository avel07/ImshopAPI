<?php

namespace Cube\Api\Controllers;

class DiscountCard extends BaseController
{

    /**
     * Константа списка групп пользователей по дисконтным картам.
     * ID группы => % скидки
     */
    public const GROUP_LIST = [
        5 => 10,
        6 => 11,
        7 => 12,
        8 => 13,
        9 => 14,
        10 => 15,
        11 => 16,
        12 => 17,
        13 => 18,
        14 => 19,
        15 => 20
    ];

    /**
     * Отдает в какой группе по ДК находится юзер
     * 
     * @param string|int $userId        ID пользователя
     * @return int
     */
    public static function getUserDkGroup($userId): ?int
    {
        $userGroupsCollection = User::getUserGroups($userId);
        foreach ($userGroupsCollection as $key => $userGroupObject) {
            $userGroupIds[] = $userGroupObject->getGroupId();
        }
        $arResult = (int) current(array_intersect($userGroupIds, self::GROUP_LIST));
        return $arResult;
    }

    /**
     * Отдает процент скидки по ДК
     * 
     * @param string|int $userId   ID пользователя
     * @return int
     */
    public static function getUserDkPercentDiscount($userId): ?int
    {
        $userGroupsCollection = User::getUserGroups($userId);
        foreach ($userGroupsCollection as $key => $userGroupObject) {
            $userGroupIds[] = $userGroupObject->getGroupId();
        }
        $arResult = (int) key(array_intersect(self::GROUP_LIST, $userGroupIds));
        return $arResult;
    }

}
