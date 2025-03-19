<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Jenius;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class JeniusGateway extends BaseBankTransfer {
    public $bankCode = 'Jenius';
    public $bankName = 'Bank Jenius';

    public function __construct() {
        parent::__construct();
    }
}