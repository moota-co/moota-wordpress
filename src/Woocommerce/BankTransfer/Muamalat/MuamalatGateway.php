<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Muamalat;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MuamalatGateway extends BaseBankTransfer {
    public $bankCode = 'Muamalat';
    public $bankName = 'Bank Muamalat - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}