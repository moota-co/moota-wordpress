<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BTN;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BTNGateway extends BaseBankTransfer {
    public $bankCode = 'BTN';
    public $bankName = 'BTN - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}