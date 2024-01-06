Moota-PHP-Sdk
===============

[Moota.co](https://moota.co) :heart: PHP Native!

[![PHP version](https://badge.fury.io/ph/moota.svg)](https://badge.fury.io/ph/mootaco/moota-php-sdk)
[![Latest Stable Version](https://poser.pugx.org/mootaco/moota-php-sdk/v/stable)](https://packagist.org/packages/mootaco/moota-php-sdk)
[![Monthly Downloads](https://poser.pugx.org/mootaco/moota-php-sdk/d/monthly)](https://packagist.org/packages/mootaco/moota-php-sdk)
[![Total Downloads](https://poser.pugx.org/mootaco/moota-php-sdk/downloads)](https://packagist.org/packages/mootaco/moota-php-sdk)

[<img src="https://github.com/moota-co/moota-php-sdk/blob/master/tests/Mocking/img/moota-php-sdk.png" width="419px" />](https://app.moota.co)

This is the Official PHP wrapper/library for Moota API V2, that is compatible with Composer.
Visit [https://moota.co](https://moota.co) for more information about the product.

## Support us [Data Transfer Object]()
* of this package only supports `php:^>=8`
* need old version  `php:^=<8` [click here](https://github.com/moota-co/moota-php-sdk/tree/v1) 
  
## 1. Installation

### 1.a Composer Installation

If you are using [Composer](https://getcomposer.org), you can install via composer CLI:

```
composer require moota-co/moota-php-sdk
```

**or**

add this require line to your `composer.json` file:

```json
{
  "require": {
    "moota-co/moota-php-sdk": "1.*"
  }
}
```

and run `composer install` on your terminal.

> **Note:** If you are using Laravel framework, in [some](https://laracasts.com/discuss/channels/general-discussion/using-non-laravel-composer-package-with-laravel?page=1#reply=461608) [case](https://stackoverflow.com/a/23675376) you also need to run `composer dumpautoload`

### 1.b Manual Instalation

If you are not using Composer, you can [clone](https://github.com/moota-co/moota-php-sdk) this repository.

Then you should require/autoload `Mutation.php` of etc class file on your code.

```php
require __DIR__ . '/../vendor/autoload.php';

// my code goes here
```

## 2. How to Use

### 2.1 General Settings

```php
// Set your API V2 Server Key 
\Moota\Moota\Config::$ACCESS_TOKEN = '<your server key>';
```

### 2.2 Choose Class Method

#### 2.2.1 Authentication

```php

$auth = new \Moota\Moota\Domain\Auth();
try {
    $getSecretToken = $auth->login(
       new \Moota\Moota\DTO\Auth\LoginData(
                'user@moota.co',
                'password_hash',
                new \Moota\Moota\DTO\Auth\ScopesData(  true )
            )
    );
    // set to \Moota\Moota\Config::$ACCESS_TOKEN = $getSecretToken['access_token'];
} catch (\Moota\Moota\Exception\MootaException $exception) {
    // handling fail authentication
}
```

#### Destroy Auth Token

```php

$getSecretToken = new \Moota\Moota\Domain\Auth();
$getSecretToken->logout();
```

#### 2.2.2 Bank Account

```php
$bank_account = new \Moota\Moota\Domain\BankAccount();
```

```php
// store bank account
try {
    $bank_account->storeBankAccount(
        new \Moota\Moota\DTO\BankAccount\BankAccountStoreData(
            "",
            Moota::BANK_TYPES[0], // list of bank type
            "loream",  //for gojek and ovo fill with mobile phone number
            "your password",
            "loream kasma",
            "16899030",
            true
        )
    );
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

body parameter storeBankAccount() reference [here](src/DTO/BankAccount/BankAccountStoreData.php)

```php
// update bank account
try {
    $bank_account->updateBankAccount(
        new \Moota\Moota\DTO\BankAccount\BankAccountUpdateData(
           "hashing_qwopejs_id",
            "jhon",  //for gojek and ovo fill with mobile phone number
            "",
            "",
            "",
            "",
            ""
            )
    );
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

body parameter updateBankAccount() reference [here](src/DTO/BankAccount/BankAccountUpdateData.php)

```php
// get list bank account
try {
    $bank_account->getBankList();
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

```php
// can't wait for new mutation data use method refresh mutation 
try {
    $bank_account->refreshMutation('<bank_id>');
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

```php
// want to remove bank account from moota ?
try {
    $bank_account->destroyBankAccount('<bank_id>');
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

```php
// want to activate my wallet account ovo or gojek please request otp code first
try {
    $otp_code = $bank_account->bankEwalletRequestOTPCode('<bank_id>');
    
    $bank_account->bankEwalletVerificationOTPCode(
        new \Moota\Moota\DTO\BankAccount\BankAccountEwalletOtpVerification('<bank_id>', '<$otp_code>')
    );
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

#### 2.2.3 Mutation

```php
$mutation = new \Moota\Moota\Domain\Mutation();
```

```php
// get my mutation
$my_mutation = $mutation->getMutations(
    new \Moota\Moota\DTO\Mutation\MutationQueryParameterData(
            'asdasd',
            '2021-09-21',
            'Testing Note Mutation',
            '2000123',
            'CR'
        )
);
```
mutation query parameter getMutations() reference [here](src/DTO/Mutation/MutationQueryParameterData.php)
```php
// Determine dummy mutation for debugging
$my_mutation = $mutation->storeMutation(
    new \Moota\Moota\DTO\Mutation\MutationStoreData(
        // fill mutation value here
    )
);
``` 
mutation parameter on storeMutation() reference [here](src/DTO/Mutation/MutationStoreData.php)

```php
// Add note mutation
$my_mutation = $mutation->addNoteMutation(
    new \Moota\Moota\DTO\Mutation\MutationNoteData(
            'hash_mutation_id',
            'Testing Note Mutation',
        )
);
``` 
mutation parameter on  addNoteMutation() reference [here](src/DTO/Mutation/MutationNoteData.php)
```php
// Debugging | try getting mutation webhook from moota
$my_mutation = $mutation->pushWebhookByMutation('<mutation_id>');
``` 
```php
/**
* need to remove mutation data ? 
* method destroy mutation can multiple remove mutation
*/
$my_mutation = $mutation->destroyMutation(
    new \Moota\Moota\DTO\Mutation\MutationDestroyData(
           ["hash_mutation_id", "hash_mutation_id"]
        )
);
``` 
mutation parameter on destroyMutation() reference [here](src/DTO/Mutation/MutationDestroyData.php)

```php
// method attach tagging to mutation 

$my_mutation = $mutation->attachTagMutation(
    new \Moota\Moota\DTO\Mutation\MutationAttachTaggingData(
            '8aolk43WJxM',
            [
                "assurance", "..."
            ]
        )
);
``` 
mutation parameter on attachTagMutation() reference [here](src/DTO/Mutation/MutationAttachTaggingData.php)

```php
// method detach tagging from mutation 

$my_mutation = $mutation->detachTagMutation(
    new \Moota\Moota\DTO\Mutation\MutationDetachTaggingData([
            '8aolk43WJxM',
            [
                "assurance", "..."
            ]
        )
);
``` 
mutation parameter on detachTagMutation() reference [here](src/DTO/Mutation/MutationDetachTaggingData.php)

```php
// method update tagging from mutation 

$my_mutation = $mutation->updateTagMutation(
    new \Moota\Moota\DTO\Mutation\MutationUpdateTaggingData([
            '8aolk43WJxM',
            [
                "assurance", "..."
            ]
        )
);
``` 
mutation parameter on updateTagMutation() reference [here](src/DTO/Mutation/MutationUpdateTaggingData.php)

#### 2.2.4 Tagging

```php
$tagging = new \Moota\Moota\Domain\Tagging(); 
```

```php
$my_tagging = $tagging->getTaggings(
    new \Moota\Moota\DTO\Tagging\TaggingQueryParameterData(
            ['assurance', 'cash'] // or leave blank doesn`t have filter
        )
); 
``` 
tagging parameter on getTaggings() reference [here](src/DTO/Tagging/TaggingQueryParameterData.php)


```php
// create first tagging like it
$my_tagging = $tagging->storeTagging(
    new \Moota\Moota\DTO\Tagging\TaggingStoreData(
            'assurance'
        )
); 
``` 
tagging parameter on storeTagging() reference [here](src/DTO/Tagging/TaggingStoreData.php)

```php
// update tagging like it
$my_tagging = $tagging->updateTagging(
    new \Moota\Moota\DTO\Tagging\TaggingUpdateData(
           'VLagzqBj42Ds',
            'assurance-car'
        )
); 
``` 
tagging parameter on updateTagging() reference [here](src/DTO/Tagging/TaggingUpdateData.php)
```php
// update tagging like it
$my_tagging = $tagging->destroyTagging('<tag_id>'); 
``` 
#### 2.2.5 Topup
you can add bank account and getting mutation from bank account must be have point please TOPUP Point First!

```php
$topup = new \Moota\Moota\Domain\Topup(); 
```

```php
// Get list payment method | targeting bank account topup point
$payment_method = $topup->getPaymentMethod();
```

```php
// Get List Amounts Point | minimum and maximum point can topup
$amounts = $topup->getListAmountPoint();
```

```php
// get my topup 
$my_topup = $topup->getListTopupPoint();
``` 
```php
// create topup point
$my_topup = $topup->createTopupPoint(
    new \Moota\Moota\DTO\Topup\CreateTopupData(
            100000,
            'bca'
        )
);
```
topup parameter on createTopupPoint() reference [here](src/DTO/Topup/CreateTopupData.php)
```php
// have a voucher code ?
$my_topup = $topup->redeemVoucher(
    new \Moota\Moota\DTO\Topup\VoucherRedeemData(
          'abcd'
        )
);
```
topup parameter on redeemVoucher() reference [here](src/DTO/Topup/VoucherRedeemData.php)

#### 2.2.6 Transaction
you can get history transaction of point here
```php
$transaction = new \Moota\Moota\Domain\Transaction();  

$history = $transaction->getHistoryTransactionPoint(
    new \Moota\Moota\DTO\Transaction\TransactionHistoryData(
             1,
             '',
            ''
        )
);
```
transaction parameter on getHistoryTransactionPoint() reference [here](src/DTO/Transaction/TransactionHistoryData.php)

#### 2.2.7 User
you can get profile information here
```php
$user = new \Moota\Moota\Domain\User();  

$my_profile = $transaction->getProfile();

$my_profile = $transaction->updateProfile(
    new \Moota\Moota\DTO\User\UserUpdateData(
            'moota',
            'email@moota.co',
            '12312312123123',
            'Jl. street no 1'
        )
);
```
User parameter on updateProfile() reference [here](src/DTO/User/UserUpdateData.php)

#### 2.2.8 Webhook

```php
$webhook = new \Moota\Moota\Domain\Webhook();  
```

```php

$response = $webhook->getListWebhooks(
    new \Moota\Moota\DTO\Webhook\WebhookQueryParameterData(
        '',
        '',
         1,
        20
    )
);
```

```php
try {
    $response = $webhook->storeWebhook(
        new \Moota\Moota\DTO\Webhook\WebhookStoreData(
                'https://app.moota.co/endpoint/webhook',
               'akjsdkj3',
                1,
               999,
                'credit',
                '', // leave blank webhook can set for all banks account
            )
    );
 } catch (\Moota\Moota\Exception\MootaException $exception) {
    print_r($exception->getPharseErrors());
 }
```

```php
$response = $webhook->removeWebhook('<webhook_id>');
```

```php
$response = $webhook->getWebhookHistory('<webhook_id>');
```
Retrieve webhook response like it
```php
$webhook = new \Moota\Moota\Domain\Webhook('<secret_token>');  

try {
    $response_payload_json = file_get_contents("php://input");
    $get_signature_from_header = getallheaders()['Signature'];
    
    $webhooks = $webhook->getResponse($get_signature_from_header, $response_payload_json);
    
    foreach ($webhooks as $webhook) {
        // code store to database 
    }
} catch (\Moota\Moota\Exception\Webhook\WebhookValidateException $exception) {
    // code handling when incoming webhook fail validation signature 
    print_r($exception->getMessage()) 
}

```


## Unit Test

### All Test
`./vendor/bin/phpunit`

### Specific Test
`./vendor/bin/phpunit --filter methodname`

## Contributing


## Questions?

If you have any questions please [open an issue](https://github.com/moota-co/moota-php-sdk/issues/new).
