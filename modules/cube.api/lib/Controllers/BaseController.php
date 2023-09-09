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
     * Исключаем ключи data, errors и status в ответе. Отправляем только data.
     * 
     *  @param \Bitrix\Main\Response    $response       Данные ответа.
     *  @return \Bitrix\Main\Response                   Сам переопределенный ответ в json.
     */
    final function finalizeResponse(\Bitrix\Main\Response $response): ?\Bitrix\Main\Response
    {
        $data = \Bitrix\Main\Web\Json::decode($response->getContent());
        $data['data'] ? $data = \Bitrix\Main\Web\Json::encode($data['data'], JSON_UNESCAPED_UNICODE) : $data = \Bitrix\Main\Web\Json::encode($data['errors'], JSON_UNESCAPED_UNICODE);
        return $response->setContent($data);
    }

    /**
     * Добавляем стандартную ошибку по PSR формату. Логируем все ошибки..
     * 
     * @param \Bitrix\Main\Error $error         Объект ошибки.
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

    /**
     * Временное решение из за ошибок в ядре (current user может быть пусто)
     * TODO: Удалить, как обновим проект до PHP 8+ и обновим Битрикс!
     * 
	 * @param Controller|string $controller     Контроллер
	 * @param string            $actionName     Название метода
	 * @param array|null        $parameters     Доп параметры к методу контроллера
	 *
	 * @return HttpResponse|mixed
	 * @throws SystemException
	 */
	public function forward($controller, string $actionName, array $parameters = null)
	{
		if (is_string($controller))
		{
			$controller = new $controller;
		}

		// override parameters
		$controller->request = $this->getRequest();
		$controller->setScope($this->getScope());;

		// run action
		$result = $controller->run(
			$actionName,
			$parameters === null ? $this->getSourceParametersList() : [$parameters]
		);

		$this->addErrors($controller->getErrors());

		return $result;
	}
}
