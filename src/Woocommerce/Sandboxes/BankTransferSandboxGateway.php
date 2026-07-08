<?php

namespace Moota\MootaSuperPlugin\Woocommerce\Sandboxes;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BankTransferSandboxGateway extends BaseBankTransfer
{
    public $bankCode = 'banktransfersandbox';
    public $bankName = 'Bank Transfer (Sandbox)';
    public $icon = 'https://app.moota.co/images/icon-bank-bankTransferSandbox.png';

    public function __construct()
    {
        parent::__construct();
    }
}
