<?php

namespace Cube\Api\Controllers;

use CUser;

class User extends BaseController
{
    
    public function __construct(\Bitrix\Main\Request $request = null)
    {
        parent::__construct($request);
    }
    /**
     * Получить ID юзера.
     *
     * @param string|null $id
     * @param string $phone
     * @param string $email
     * @return void|string
     */
    public static function generateUserId($id, string $phone = '', string $email = ''): ?int
    {
        $phone = self::normalizePhone($phone);
        if (!empty($id)) {
            $userId = self::getUserById($id);
            if (!is_int($userId)) {
                $userId = self::createUser($phone, $email);
            }
        } else {
            $userId = self::getUserByPhone($phone);
            if (!is_int($userId)) {
                $userId = self::createUser($phone, $email);
            }
        }
        return $userId;
    }

    /**
     * Получить группу пользователя.
     *
     * @param string $id
     * @return void|object
     */
    public static function getUserGroups(string $userId): ?object
    {
        $userGroupObject = \Bitrix\Main\UserGroupTable::query()
            ->addFilter('USER_ID', $userId)
            ->addSelect('GROUP_ID')
            ->fetchCollection();
        return $userGroupObject;
    }

    /**
     * Регистрируем пользователя по номеру телефона.
     *
     * @param string $phone
     * @param string $email
     * @return int|string
     */
    public static function createUser($phone, $email)
    {
        $password = \Bitrix\Main\Security\Random::getString(6, true);
        $USER = new CUser;
        $arUserFields = [
            "LOGIN"             => $phone,
            "PASSWORD"          => $password,
            "CONFIRM_PASSWORD"  => $password,
            "EMAIL"             => $email,
            "ACTIVE"            => "Y",
            "PHONE_NUMBER"      => $phone
        ];
        $newUserID = $USER->add($arUserFields);
        if ($newUserID) {
            return $newUserID;
        } else {
            return $USER->LAST_ERROR;
        }
    }

    /**
     * Обновляем данные пользователя.
     * 
     * @param int $userId
     * @param string $phone
     * @param string $email
     * @return int|string
     */
    public static function updateUser(int $userId, string $phone, string $email, string $name)
    {
        $USER = new CUser;
        $arUserFields = [
            "LOGIN"             => $phone,
            "EMAIL"             => $email,
            "ACTIVE"            => "Y",
            "PHONE_NUMBER"      => $phone,
            "NAME"              => $name
        ];
        if ($USER->Update($userId, $arUserFields)) {
            return $userId;
        } else {
            return $USER->LAST_ERROR;
        }
    }


    /**
     * Проверяет на существование юзера по ID, отдает ID, если найден.
     *
     * @param string $id
     * @return void|array
     */
    public static function getUserById(string $id = ''): ?mixed
    {
        $userId = \Bitrix\Main\UserTable::query()
            ->addFilter('ID', $id)
            ->addSelect('ID')
            ->fetchObject();
        $userId ? $userId = $userId->getId() : $userId = null;
        return $userId;
    }

    /**
     * Проверяет на существование юзера по телефону, отдает ID, если найден.
     *
     * @param string $phoneNumber
     * @return void|array
     */
    public static function getUserByPhone(string $phoneNumber)
    {
        $userId = \Bitrix\Main\UserPhoneAuthTable::query()
            ->addFilter('PHONE_NUMBER', $phoneNumber)
            ->addSelect('USER_ID')
            ->fetchObject();
        $userId ? $userId = $userId->getUserId() : null;
        return $userId;
    }

    /**
     * Нормализует формат телефона, пригодный для регистрации, авторизации, поиска.
     *
     * @param string $phoneNumber
     * @return string
     */
    public static function normalizePhone(string $phoneNumber): ?string
    {
        return \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneNumber);
    }

    /**
     * Проверяет номер телефона на валидность.
     *
     * @param string $phoneNumber
     * @return bool
     */
    public static function checkPhone(string $phoneNumber): ?bool
    {
        if (!\Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($phoneNumber)->isValid()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Генерирует анонимный UserId
     *
     * @return int
     */
    public static function getAnonimusUserId(): ?int
    {
        $anonimusUserId = \CSaleUser::GetAnonymousUserID();
        return $anonimusUserId;
    }
}
