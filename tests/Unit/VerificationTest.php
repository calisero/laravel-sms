<?php

declare(strict_types=1);

namespace Calisero\LaravelSms\Tests\Unit;

use Calisero\LaravelSms\Facades\Calisero;
use Calisero\LaravelSms\Tests\TestCase;
use Mockery;

class VerificationTest extends TestCase
{
    public function test_send_verification_with_default_params(): void
    {
        $mockVerificationService = Mockery::mock(\Calisero\Sms\Services\VerificationService::class);
        $mockResponse = Mockery::mock(\Calisero\Sms\Dto\CreateVerificationResponse::class);
        $mockVerification = Mockery::mock(\Calisero\Sms\Dto\Verification::class);

        $mockVerification->shouldReceive('getPhone')->andReturn('+40712345678');
        $mockVerification->shouldReceive('getStatus')->andReturn('unverified');
        $mockResponse->shouldReceive('getData')->andReturn($mockVerification);

        $mockVerificationService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request instanceof \Calisero\Sms\Dto\CreateVerificationRequest
                    && '+40712345678' === $request->getPhone();
            }))
            ->andReturn($mockResponse);

        $mockSdkClient = Mockery::mock(\Calisero\Sms\SmsClient::class);
        $mockSdkClient->shouldReceive('verifications')->andReturn($mockVerificationService);

        $wrapperClient = new \Calisero\LaravelSms\SmsClient($mockSdkClient);
        $this->app->instance(\Calisero\LaravelSms\Contracts\SmsClient::class, $wrapperClient);

        $response = Calisero::sendVerification([
            'to' => '+40712345678',
        ]);

        $this->assertEquals('+40712345678', $response->getData()->getPhone());
        $this->assertEquals('unverified', $response->getData()->getStatus());
    }

    public function test_send_verification_throws_exception_on_error(): void
    {
        $mockVerificationService = Mockery::mock(\Calisero\Sms\Services\VerificationService::class);

        $mockVerificationService->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $mockSdkClient = Mockery::mock(\Calisero\Sms\SmsClient::class);
        $mockSdkClient->shouldReceive('verifications')->andReturn($mockVerificationService);

        $wrapperClient = new \Calisero\LaravelSms\SmsClient($mockSdkClient);
        $this->app->instance(\Calisero\LaravelSms\Contracts\SmsClient::class, $wrapperClient);

        $this->expectException(\Exception::class);

        Calisero::sendVerification([
            'to' => '+40712345678',
        ]);
    }

    public function test_check_verification_valid_code(): void
    {
        $mockVerificationService = Mockery::mock(\Calisero\Sms\Services\VerificationService::class);
        $mockResponse = Mockery::mock(\Calisero\Sms\Dto\GetVerificationResponse::class);
        $mockVerification = Mockery::mock(\Calisero\Sms\Dto\Verification::class);

        $mockVerification->shouldReceive('getPhone')->andReturn('+40712345678');
        $mockVerification->shouldReceive('getStatus')->andReturn('verified');
        $mockVerification->shouldReceive('getVerifiedAt')->andReturn(now()->toIso8601String());
        $mockResponse->shouldReceive('getData')->andReturn($mockVerification);

        $mockVerificationService->shouldReceive('validate')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request instanceof \Calisero\Sms\Dto\VerificationCheckRequest
                    && '+40712345678' === $request->getPhone()
                    && '123456' === $request->getCode();
            }))
            ->andReturn($mockResponse);

        $mockSdkClient = Mockery::mock(\Calisero\Sms\SmsClient::class);
        $mockSdkClient->shouldReceive('verifications')->andReturn($mockVerificationService);

        $wrapperClient = new \Calisero\LaravelSms\SmsClient($mockSdkClient);
        $this->app->instance(\Calisero\LaravelSms\Contracts\SmsClient::class, $wrapperClient);

        $result = Calisero::checkVerification([
            'to' => '+40712345678',
            'code' => '123456',
        ]);

        $this->assertEquals('verified', $result->getData()->getStatus());
        $this->assertNotNull($result->getData()->getVerifiedAt());
    }

    public function test_check_verification_invalid_code(): void
    {
        $mockVerificationService = Mockery::mock(\Calisero\Sms\Services\VerificationService::class);
        $mockResponse = Mockery::mock(\Calisero\Sms\Dto\GetVerificationResponse::class);
        $mockVerification = Mockery::mock(\Calisero\Sms\Dto\Verification::class);

        $mockVerification->shouldReceive('getPhone')->andReturn('+40712345678');
        $mockVerification->shouldReceive('getStatus')->andReturn('unverified');
        $mockVerification->shouldReceive('getVerifiedAt')->andReturn(null);
        $mockResponse->shouldReceive('getData')->andReturn($mockVerification);

        $mockVerificationService->shouldReceive('validate')
            ->once()
            ->with(Mockery::on(function ($request) {
                return $request instanceof \Calisero\Sms\Dto\VerificationCheckRequest
                    && '+40712345678' === $request->getPhone()
                    && '999999' === $request->getCode();
            }))
            ->andReturn($mockResponse);

        $mockSdkClient = Mockery::mock(\Calisero\Sms\SmsClient::class);
        $mockSdkClient->shouldReceive('verifications')->andReturn($mockVerificationService);

        $wrapperClient = new \Calisero\LaravelSms\SmsClient($mockSdkClient);
        $this->app->instance(\Calisero\LaravelSms\Contracts\SmsClient::class, $wrapperClient);

        $result = Calisero::checkVerification([
            'to' => '+40712345678',
            'code' => '999999',
        ]);

        $this->assertEquals('unverified', $result->getData()->getStatus());
        $this->assertNull($result->getData()->getVerifiedAt());
    }
}
