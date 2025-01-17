<?php

namespace Moota\Moota\Data;

class CustomerData {
    public static string $name;
    public static ?string $email;
    public static ?string $phone;


    private function __construct(
        string $name, ?string $email, ?string $phone
    ) {
        self::$name = $name;
        self::$email = $email;
        self::$phone = $phone;
    }

    public static function create(string $name, ?string $email, ?string $phone): CustomerData {
        return new self($name, $email, $phone);
    }

    public static function transform() : array
    {
        return [
            "name" => self::$name,
            "email" => self::$email,
            "phone" => self::$phone,
        ];
    }
}