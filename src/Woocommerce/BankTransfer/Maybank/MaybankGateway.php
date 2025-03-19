<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Maybank;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MaybankGateway extends BaseBankTransfer {
    public $bankCode = 'BCA';
    public $bankName = 'BCA - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}