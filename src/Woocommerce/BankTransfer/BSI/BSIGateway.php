<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BSI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BSIGateway extends BaseBankTransfer {
    public $bankCode = 'BSI';
    public $bankName = 'BSI - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}