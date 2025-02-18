<?php

namespace Moota\Moota\Data;

class CreateTransactionData {
    public static string $order_id;
    public static string $bank_account_id;
    public static ?string $channel_id;
    public static CustomerData $customer;
    public static array $items;
    public static ?string $description;
    public static ?string $note;
    public static ?string $redirect_url;
    public static int $total;
    public static ?int $expired_in_minutes;

    private function __construct(
        string $order_id,
        string $bank_account_id,
        ?string $channel_id,
        CustomerData $customer,
        array $items,
        ?string $description,
        ?string $note,
        ?string $redirect_url,
        int $total,
        int $expired_in_minutes
    ) {
        self::$order_id = $order_id;
        self::$bank_account_id = $bank_account_id;
        self::$channel_id = $channel_id;
        self::$customer = $customer;
        self::$items = $items;
        self::$description = $description;
        self::$note = $note;
        self::$redirect_url = $redirect_url;
        self::$total = $total;
        self::$expired_in_minutes = $expired_in_minutes;
    }

    public static function create(
        string $order_id,
        string $bank_account_id,
        ?string $channel_id,
        CustomerData $customer,
        array $items,
        ?string $description = null,
        ?string $note = null,
        ?string $redirect_url = null,
        int $total,
        ?int $expired_in_minutes
    ): CreateTransactionData
    {
        return new self(
            $order_id,
            $bank_account_id,
            $channel_id,
            $customer,
            $items,
            $description,
            $note,
            $redirect_url,
            $total,
            $expired_in_minutes
        );
    }

    public static function transform() : array
    {
        return [
            "order_id" => self::$order_id,
            "bank_account_id" => self::$bank_account_id,
            "channel_id" => self::$channel_id,
            "customers" => self::$customer::transform(),
            "items" => self::$items,
            "description" => self::$description,
            "note" => self::$note,
            "redirect_url" => self::$redirect_url,
            "total" => self::$total,
            "expired_in_minutes" => self::$expired_in_minutes
        ];
    }
}