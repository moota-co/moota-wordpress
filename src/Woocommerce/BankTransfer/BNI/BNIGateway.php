<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BNI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BNIGateway extends BaseBankTransfer {
    public $bankCode = 'BNI';
    public $bankName = 'BNI - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}