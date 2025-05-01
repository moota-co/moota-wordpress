<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Maybank;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MaybankGateway extends BaseBankTransfer {
    public $bankCode = 'Maybank';
    public $bankName = 'Maybank - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}