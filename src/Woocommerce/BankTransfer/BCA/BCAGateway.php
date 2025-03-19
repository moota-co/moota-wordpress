<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BCA;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BCAGateway extends BaseBankTransfer {
    public $bankCode = 'bca';
    public $bankName = 'BCA - Bank Transfer';
    public $icon = 'https://api.moota.co/images/icon-bank-bca.png';
    
    public function __construct() {
        parent::__construct();
    }
}