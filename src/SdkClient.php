<?php

namespace Calisero\LaravelSms;

use Calisero\Sms\Http\HttpClient;
use Calisero\Sms\Services\AccountService;
use Calisero\Sms\Services\MessageService;
use Calisero\Sms\Services\OptOutService;
use Calisero\Sms\Services\VerificationService;

/**
 * The Calisero SDK's services over an HttpClient of our own making.
 *
 * Same accessors as \Calisero\Sms\SmsClient, whose constructor is private: its
 * create() fixes the base URI and the timeouts, so it cannot honour the package
 * configuration. Built by ClientFactory::make().
 */
class SdkClient
{
    private MessageService $messageService;

    private OptOutService $optOutService;

    private AccountService $accountService;

    private VerificationService $verificationService;

    public function __construct(HttpClient $httpClient)
    {
        $this->messageService = new MessageService($httpClient);
        $this->optOutService = new OptOutService($httpClient);
        $this->accountService = new AccountService($httpClient);
        $this->verificationService = new VerificationService($httpClient);
    }

    public function messages(): MessageService
    {
        return $this->messageService;
    }

    public function optOuts(): OptOutService
    {
        return $this->optOutService;
    }

    public function accounts(): AccountService
    {
        return $this->accountService;
    }

    public function verifications(): VerificationService
    {
        return $this->verificationService;
    }
}
