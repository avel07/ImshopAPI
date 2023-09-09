<?php
namespace Cube\Api\Controllers;
use Client;
use Dsmska;

class Sms extends BaseController
{

    protected const LOGIN = '1682681';
    protected const PASSWORD = 'rd1@eobuv';
    protected const OPERATOR = 1;
    protected const TARIF = 1;
    protected const ACTION = 'post_sms';
    protected const SENDER = 'eobuv';

    public function __construct(\Bitrix\Main\Request $request = null)
    {
        parent::__construct($request);
    }

    /**
     * Отправка SMS кода для ImShop при помощи API модуля disprove.smska 
     * 
     * @param string $phoneNumber       Номер телефона
     * @param string $code              Код
     * @return bool|string
     */
    public function sendMessage(string $phoneNumber, string $message)
    {
        if(!\Bitrix\Main\Loader::includeModule('disprove.smska')){
            $this->addError(new \Bitrix\Main\Error('Ошибка подключения модуля для отправки SMS - disprove.smska', 400));
            return null;
        }
        $dsmska = new Dsmska();
        // Создаем экземпляр клиента для модуля
        $client = new Client(["LOGIN"=> self::LOGIN, "PASSWORD"=> self::PASSWORD, "OPERATOR"=> self::OPERATOR, "TARIF"=> self::TARIF]);
        // Входящие параметры
        $post_params = [
        'action' => self::ACTION, 
        'sender' => self::SENDER,
        'target' => $phoneNumber,
        'message' => $message
        ];
        // Отправляем сообщение.
        $sendPost = $client->makeRequest("sms",$post_params);
        // Получаем ID сообщения.
        $sendPostMessId = (int)$xml->result->sms["id"];
        // Массив чтобы получить данные по сообщению (статус)
        $post_params = ["action"=>"status", "sms_id"=> $sendPostMessId];
        // Отправляем запрос на получение данных по SMS
        $sendPost = $client->makeRequest("status",$post_params);
        // Получаем статус сообщения
        $sendPostStatus = $sendPost->MESSAGES->MESSAGE->SMSSTC_CODE;
        // Обрабатываем ошибку
        if($sendPostStatus === 'error'){
            $error = $sendPost->MESSAGES->MESSAGE->SMS_STATUS;
            $this->addError(new \Bitrix\Main\Error('Ошибка отправки SMS - ' . $error, 400));
            return $error;
        }
        else{
            return true;
        }
    }

}

?>