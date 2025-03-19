<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BRI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BRIGateway extends BaseBankTransfer {
    public $bankCode = 'BRI';
    public $bankName = 'BRI - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}