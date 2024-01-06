<?php

namespace Moota\Moota;

class MootaWebhook
{
    private string $secret;
    private string $signature;
    private string $content;
    public function __construct(string $secret, string $signature, ?string $content = "php://input")
    {
        $this->secret = $secret;
        $this->signature = $signature;
        $this->content = $content;
    }

    public function signatureCheck() : bool
    {
        $signature = hash_hmac('sha256', $this->content, $this->secret);
        return $this->secret == $signature;
    }
}