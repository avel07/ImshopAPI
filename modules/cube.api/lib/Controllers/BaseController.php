<?php

namespace Cube\Api\Controllers;

use Bitrix\Main\Engine\ActionFilter;

/**
 * Наследник класса контроллеров битрикса
 *
 * Исключения тоже обрабатываются в json
 * При включении debug в .settings.php показывается вывод вид ошибки
 *
 */
class BaseController extends \Bitrix\Main\Engine\Controller
{

    /**
     * Переопределяем префильтры
     *
     * Удаляем csrf и обязательную авторизацию
     *
     * @return void
     */
    protected function getDefaultPreFilters()
    {
        return [
            new ActionFilter\HttpMethod(
                [
                    ActionFilter\HttpMethod::METHOD_GET,
                    ActionFilter\HttpMethod::METHOD_POST
                ]
            ),
            new ActionFilter\Csrf(false),
        ];
    }

    /**
     * 
     * Переопределяем стандартный ответ от контроллера через HttpResponse
     * Исключаем ключи data, errors и status в ответе.
     * 
     *  @param array $data    данные для response
     * 
     *  @return Response      ответ в чистом json
     * */
    final protected function setResponse(array $data): ?\Bitrix\Main\Response
    {
        $response = new \Bitrix\Main\HttpResponse();
        $response->addHeader('Content-Type', 'application/json');
        $response->flush(\Bitrix\Main\Web\Json::encode($data));
        return $response;
    }

    /**
     * Добавляем стандартную ошибку по PSR-4 формату. Логируем все ошибки..
     * 
     * @param \Bitrix\Main\Error $error         Объект ошибки.
     * 
     * @return \Bitrix\Main\Diag\FileLogger     Файл лога.
     */

    protected function addError(\Bitrix\Main\Error $error)
    {
        $now = new \Bitrix\Main\Type\DateTime();
        parent::addError($error);
        $message = (new \Bitrix\Main\Diag\LogFormatter())->format("{log}\n{trace}\n{delimiter}\n", [
            'log' => [
                'Ошибка' => [
                    'date' => $now->toString(),
                    'text' => $error
                ]
            ],
            'trace' => \Bitrix\Main\Diag\Helper::getBackTrace(20, DEBUG_BACKTRACE_IGNORE_ARGS, 2)
        ]);
        (new \Bitrix\Main\Diag\FileLogger(\Bitrix\Main\Application::getDocumentRoot() . "/local/modules/cube.api/log/errors.log"))->debug($message);
    }
}
