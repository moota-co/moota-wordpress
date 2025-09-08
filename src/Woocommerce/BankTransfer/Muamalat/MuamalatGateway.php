<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Muamalat;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MuamalatGateway extends BaseBankTransfer {
    public $bankCode = 'Muamalat';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Muamalat/Muamalat.png', MOOTA_FULL_PATH );
    }
}