<?php

namespace Cube\Api\Controllers;

class Prices extends BaseController
{
    public const DEFAULT_USER_GROUP = [2];   // Все пользователи
    public const DEFAULT_PRICE_GROUP_ID = 7; // Тип цен BASE

    /**
     * Получение цен для товаров
     *
     * @return array|null
     */
    public static function listAction(array $ids, array $groups = self::DEFAULT_USER_GROUP, $priceGroupId = self::DEFAULT_PRICE_GROUP_ID): ?array
    {
        \Bitrix\Main\Loader::includeModule('catalog');

        $priceCollection = \Bitrix\Catalog\PriceTable::query()
            ->whereIn('PRODUCT_ID', $ids)
            ->where('CATALOG_GROUP_ID', $priceGroupId)
            ->addSelect('ID')
            ->addSelect('CATALOG_GROUP_ID')
            ->addSelect('PRICE')
            ->addSelect('CURRENCY')
            ->addSelect('PRODUCT_ID')
            ->addSelect('ELEMENT.IBLOCK_ID')
            ->fetchCollection();

        $isNeedDiscounts = \Bitrix\Catalog\Product\Price\Calculation::isAllowedUseDiscounts();

        foreach ($priceCollection as $price) {
            $productId = $price->get('PRODUCT_ID');
            if ($isNeedDiscounts == true && $price->get('PRICE') > 0) {
                // Получаем скидки на товар
                $discountList = \CCatalogDiscount::GetDiscount($productId, $price->get('ELEMENT')->get('IBLOCK_ID'), $price->get('CATALOG_GROUP_ID'), $groups);
                // Купоны не считаем
                foreach ($discountList as $key => $discount) {
                    if (!empty($discount['COUPON'])) {
                        unset($discountList[$key]);
                    }
                }

                // Применяем скидки и получаем итоговую цену
                $discountResult = \CCatalogDiscount::applyDiscountList($price->get('PRICE'), $price->get('CURRENCY'), $discountList);

                $discountPrice = $discountResult['PRICE'];
            } else {
                // Простая цена.
                $discountPrice = $price->get('PRICE');
            }

            // Правила округления цен
            $basePrice = \Bitrix\Catalog\Product\Price::roundPrice($price->get('CATALOG_GROUP_ID'), $price->get('PRICE'), $price->get('CURRENCY'));
            $discountPrice = \Bitrix\Catalog\Product\Price::roundPrice($price->get('CATALOG_GROUP_ID'), $discountPrice, $price->get('CURRENCY'));

            $priceItem = [];
            $priceItem['productId']       = $productId;
            $priceItem['basePrice']       = $basePrice;
            $priceItem['basePriceFormat'] = \CCurrencyLang::CurrencyFormat($basePrice, $price->get('CURRENCY'));
            $priceItem['price']           = $discountPrice;
            $priceItem['priceFormat']     = \CCurrencyLang::CurrencyFormat($discountPrice, $price->get('CURRENCY'));

            $arResult[] = $priceItem;
        }

        return $arResult;
    }
}
