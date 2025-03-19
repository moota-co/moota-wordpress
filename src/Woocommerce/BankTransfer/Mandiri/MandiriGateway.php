<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Mandiri;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MandiriGateway extends BaseBankTransfer {
    public $bankCode = 'Mandiri';
    public $bankName = 'Bank Mandiri - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}