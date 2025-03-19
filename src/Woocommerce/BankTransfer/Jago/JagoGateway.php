<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Jago;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class JagoGateway extends BaseBankTransfer {
    public $bankCode = 'Jago';
    public $bankName = 'Bank Jago Transfer';

    public function __construct() {
        parent::__construct();
    }
}