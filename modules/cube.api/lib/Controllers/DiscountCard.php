<?php

namespace Cube\Api\Controllers;

class DiscountCard extends BaseController
{

    /**
     * Cписок групп пользователей по дисконтным картам.
     */
    public const GROUP_LIST = [
        10 => "5",
        11 => "6",
        12 => "7",
        13 => "8",
        14 => "9",
        15 => "10",
        16 => "11",
        17 => "12",
        18 => "13",
        19 => "14",
        20 => "15"
    ];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        parent::__construct($request);
    }
}
