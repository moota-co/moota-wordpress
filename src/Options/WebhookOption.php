<?php

namespace Moota\MootaSuperPlugin\Options;

use Jeffreyvr\WPSettings\Options\OptionAbstract;

class WebhookOption extends OptionAbstract
{
    public $view = 'webhook-option';

    public function render()
    {
        echo '<tr valign="top">
        <th scope="row" class="titledesc">
            <label for="moota_settingsmoota_v2_api_key" class="">Webhook Url</label>
        </th>
        <td class="forminp forminp-text">
    
                        <p><b>'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://") . $_SERVER['SERVER_NAME']."/wp-json/moota-callback/webhook".'</b></p>
            
                </td>
    </tr>';
    }
}