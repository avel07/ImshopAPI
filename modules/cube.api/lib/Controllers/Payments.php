<?php

namespace Cube\Api\Controllers;

class Payments extends BaseController
{

    private $fields = [];

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        parent::__construct($request);
        $this->fields = \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->getInput();
    }

    /**
     * Action
     * Получить список оплат.
     * @return array
     */
    public function listAction(): ?array
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);

        // Если пользователь получен, то проверяем.
        if (!empty($fields['externalUserId'])) {
            $userId = User::getAnonimusUserId($fields['externalUserId']);
            if (!$userId) {
                // Проверка на существование ID юзера.
                $this->addError(new \Bitrix\Main\Error('Ошибка. Неверный userId', 400));
                return null;
            }
        } else {
            // Присваиваем анонимного.
            $userId = User::getAnonimusUserId();
        }

        // Оптимизируем запрос. Выносим за цикл.
        foreach ($fields['items'] as $arItem) {
            $arItemsIds[] = $arItem['privateId'];
        }

        // Получаем коллекцию товаров.
        $productsCollection = Product::getProduct(['ID' => $arItemsIds], ['QUANTITY', 'IBLOCK_ELEMENT.ACTIVE']);

        if (count($productsCollection->getAll()) !== count($fields['items'])) {
            $this->addError(new \Bitrix\Main\Error('Количество пришедших товаров не совпадает с существующими на сайте. Вероятно несовпадение ID пришедшего с существующим.', 400));
            return null;
        }

        // Создаем объект заказа, чтобы через него пропустить список всех доставок со скидками.
        $orderObject = Order::createOrder(\Cube\Api\Application::APP_PARAMS['SITE_ID'], $userId);

        // Создаем объект корзины, чтобы пропустить через неё список всех товаров.
        $basketObject = Basket::createBasket(\Cube\Api\Application::APP_PARAMS['SITE_ID']);

        // Добавляем товары в корзину.
        foreach ($fields['items'] as $key => $arItem) {
            $productObject = $productsCollection->getAll()[$key];

            // Проверка на количественный учет.
            if (!$productObject && $productObject->getQuantity() <= 0) {
                $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Недоступное количество.', 400));
                return null;
            }

            // Проверка на активность элемента.
            if (!$productObject->getIblockElement()->getActive()) {
                $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. Товар с ID - ' . $productObject->getId() . ' неактивен.', 400));
                return null;
            }

            // Поля корзины с кастомным PROPS.
            $arBasketFields = Basket::basketSetFields($arItem['privateId'], $arItem['quantity'], $arItem['id']);

            // Добалвяем Fields в объект корзины. Этот метод помогает избежать мучения с проверками. Все в методе.
            $addProductToBasket = Basket::addItem($basketObject, $arBasketFields, ['SITE_ID' => \Cube\Api\Application::APP_PARAMS['SITE_ID'], 'USER_ID' => $userId]);
            // Если есть ошибка, то покажем ее.
            if ($addProductToBasket instanceof \Bitrix\Main\Error) {
                $this->addError(new \Bitrix\Main\Error('Ошибка добавления товара в корзину при оформлении заказа. ' . $addProductToBasket, 400));
                return null;
            }
        }
        // Привязываем order к типу плательщика.
        $orderObject->setPersonTypeId(Order::PARAMS['PERSON_TYPE']);
        // Привязываем order к корзине. Скидки применятся автоматически.
        $orderObject->setBasket($basketObject);

        // Привязываем доставки.
        Deliveries::setDeliveryById($orderObject, $basketObject, Deliveries::getDeliveryIdbyImshop($fields['deliveryId'])['ID']);
        // Привязываем скидки к корзине.
        Basket::setDiscountByBasket($basketObject);

        // Получаем все доступные оплаты
        $paymentSystems = self::getAllPayments(['ID', 'NAME', 'DESCRIPTION']);

        // Формируем ответ.
        $arResult = $this->listActionResponse($paymentSystems);

        return $arResult;
    }

    /**
     * Формирование ответа списка оплат.
     * 
     * @param \Bitrix\Sale\Order $orderObject       Объект заказа
     * @param array $shipmentsArray                 Массив доставок
     * @return array
     */
    private function listActionResponse(object $paymentSystems): ?array
    {
        foreach ($paymentSystems as $paymentSystem) {
            $arResult['payments'][] = [
                'id'            => strval($paymentSystem->getId()),
                'title'         => $paymentSystem->getName(),
                'description'   => $paymentSystem->getDescription(),
                'type'          => $paymentSystem->get('IS_CASH') ? 'cash' : 'card'
            ];
        }
        return $arResult;
    }


    /**
     * Action
     * Интернет эквайринг. Отдаем ссылку на оплату.
     * 
     * @return array
     */
    public function createAction(): ?array
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);
        // Инициализируем объект заказа
        $order = \Bitrix\Sale\Order::load($fields['orderId']);
        // Коллекция оплат.
        $paymentCollection = $order->getPaymentCollection();
        // Получаем текущуюу оплату заказа.
        $paymentObject = $paymentCollection->current();
        // Проверим сразу, чтобы не было второго запроса, оплачен ли заказ.
        if($paymentObject->getField('PAID') === 'Y'){
            $this->addError(new \Bitrix\Main\Error('Ошибка формирования ссылки на оплату. Оплата имеет статус оплачено.', 400));
            return null;
        }
        // Получаем сервис оплаты.
        $payService = \Bitrix\Sale\PaySystem\Manager::getObjectById($paymentObject->getField('PAY_SYSTEM_ID'));
        // Инициализируем оплату для объекта оплаты.
        $initPayment = $payService->initiatePay($paymentObject, null, \Bitrix\Sale\PaySystem\BaseServiceHandler::STRING);
        // Проверяем ошибку оплаты. Если найдена, возвращаем сообщения ошибки.
        if(!$initPayment->isSuccess()){
            $this->addError(new \Bitrix\Main\Error('При инициализации оплаты произошли ошибки: '.$initPayment->getBuyerErrorMessages(), 400));
            return null;
        }
        $arResult = $this->createActionResponse($paymentCollection, $initPayment);
        return $arResult;
    }

    /**
     * Формирование ответа для создания оплаты в Интернет-Эквайринг.
     * 
     * @param \Bitrix\Sale\PaymentCollection $paymentCollection
     * @param \Bitrix\Sale\PaySystem\ServiceResult $initPayment
     * @return array
     */
    private function createActionResponse(\Bitrix\Sale\PaymentCollection $paymentCollection,\Bitrix\Sale\PaySystem\ServiceResult $initPayment): ?array
    {
        $paymentObject = $paymentCollection->current();
        $arResult = [
            'success'       => true,
            'paymentId'     => $paymentObject->getId(),
            'paymentUrl'    => $initPayment->getPaymentUrl(),
        ];
        return $arResult;
    }

    /**
     * Action
     * Интернет эквайринг. Проверяем оплату.
     * @return array
     */
    public function captureAction(): ?array
    {
        $fields = \Bitrix\Main\Web\Json::decode($this->fields, JSON_UNESCAPED_UNICODE);
        // Получим данные об оплате. К сожалению работает только getList.
        $paymentArray = \Bitrix\Sale\PaymentCollection::getList([
            'select' => ['*'],
            'filter' => ['=ID' => $fields['paymentId']]
        ])->fetchRaw();
        // Если заказ не оплачен, отдаем ошибку.
        if($paymentArray['PAID'] !== 'Y'){
            if($paymentArray['PS_STATUS_MESSAGE']){
                $this->addError(new \Bitrix\Main\Error('Проверка оплаты не пройдена. Заказ не оплачен. Сообщение с платежной системы: '.$paymentArray['PS_STATUS_MESSAGE'], 400));
                return null;
            } else {
                $this->addError(new \Bitrix\Main\Error('Проверка оплаты не пройдена. Заказ не оплачен. Сообщения с платежной системы нет.', 400));
                return null;
            }
        }
        $arResult = $this->captureActionResponse($paymentArray);
        return $arResult;
    }

    /**
     * Формирование ответа для проверки оплаты в Интернет-Эквайринге.
     * 
     * @param array $paymentArray   Массив данных об оплате.
     * @return array
     */
    private function captureActionResponse(array $paymentArray): ?array
    {
        $arResult = [
            'success'           => true,
            'paymentId'         => $paymentArray['ID'],
            'paymentCaptured'   => true
        ];
        return $arResult;
    }

    /**
     * Получить ID способа оплаты по особенностям ImShop.
     * 
     * @param string $paymentId         ID платежной системы.
     * @return array
     */
    public static function getPaymentIdbyImshop(string $paymentId): ?array
    {
        $paymentId = explode('/', $paymentId);
        $paymentId = [
            'TYPE' => $paymentId[0],
            'ID'   => $paymentId[1]
        ];
        return $paymentId;
    }

    /**
     * Привязать оплату к объекту заказа.
     * 
     * @param \Bitrix\Sale\Order $orderObject       Объект заказа.
     * @param string|int $paymentId                 ID платежной системы.
     */
    public static function setPayment(\Bitrix\Sale\Order $orderObject, $paymentId)
    {
        $paymentCollection = $orderObject->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $paySystemService = \Bitrix\Sale\PaySystem\Manager::getObjectById($paymentId);
        $payment->setFields([
            'PAY_SYSTEM_ID' => $paySystemService->getField("PAY_SYSTEM_ID"),
            'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
        ]);
    }
    /**
     * Получить коллекцию всех активных Оплат.
     * 
     * @param array @arSelect
     * @param array @arFilter
     * 
     * @return object
     */
    private function getAllPayments(array $arSelect = ['*'], array $arFilter = ['ACTIVE' => 'Y']): ?object
    {
        // Получаем список доступных платежных систем
        $paymentSystems = \Bitrix\Sale\PaySystem\Manager::getList([
            'select' => $arSelect,
            'filter' => $arFilter
        ])->fetchCollection();
        return $paymentSystems;
    }
}
