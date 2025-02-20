<?php

namespace Moota\Moota\Data;

class CreateTransactionData {
    public static string $order_id;
    public static string $bank_account_id;
    public static CustomerData $customer;
    public static array $items;
    public static int $total;
    public static ?string $channel_id;
    public static ?string $description;
    public static ?string $note;
    public static ?string $redirect_url;
    public static ?int $expired_in_minutes;

    private function __construct(
        string $order_id,
        string $bank_account_id,
        CustomerData $customer,
        array $items,
        int $total,
        ?string $channel_id,
        ?string $description,
        ?string $note,
        ?string $redirect_url,
        ?int $expired_in_minutes
    ) {
        self::$order_id = $order_id;
        self::$bank_account_id = $bank_account_id;
        self::$customer = $customer;
        self::$items = $items;
        self::$total = $total;
        self::$channel_id = $channel_id;
        self::$description = $description;
        self::$note = $note;
        self::$redirect_url = $redirect_url;
        self::$expired_in_minutes = $expired_in_minutes;
    }

    public static function create(
        string $order_id,
        string $bank_account_id,
        CustomerData $customer,
        array $items,
        int $total,
        ?string $channel_id,
        ?string $description = null,
        ?string $note = null,
        ?string $redirect_url = null,
        ?int $expired_in_minutes
    ): CreateTransactionData
    {
        return new self(
            $order_id,
            $bank_account_id,
            $customer,
            $items,
            $total,
            $channel_id,
            $description,
            $note,
            $redirect_url,
            $expired_in_minutes
        );
    }

    public static function transform() : array
    {
        return [
            "order_id" => self::$order_id,
            "bank_account_id" => self::$bank_account_id,
            "customers" => self::$customer::transform(),
            "items" => self::$items,
            "total" => self::$total,
            "channel_id" => self::$channel_id,
            "description" => self::$description,
            "note" => self::$note,
            "redirect_url" => self::$redirect_url,
            "expired_in_minutes" => self::$expired_in_minutes
        ];
    }
}