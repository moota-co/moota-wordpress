<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BJB;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BJBGateway extends BaseBankTransfer {
    public $bankCode = 'BJB';
    public $bankName = 'BJB - Bank Transfer';

    public function __construct() {
        parent::__construct();
    }
}