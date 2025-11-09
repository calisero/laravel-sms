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
        $mockClient = Mockery::mock(\Calisero\Sms\CaliseroClient::class);
        $mockResponse = (object) [
            'phone' => '+40712345678',
            'status' => 'unverified',
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ];

        $mockClient->shouldReceive('sendVerification')
            ->once()
            ->with(Mockery::on(function ($params) {
                return '+40712345678' === $params['phone'];
            }))
            ->andReturn($mockResponse);

        $this->app->instance(\Calisero\Sms\CaliseroClient::class, $mockClient);

        $response = Calisero::sendVerification([
            'to' => '+40712345678',
        ]);

        $this->assertEquals('+40712345678', $response->phone);
        $this->assertEquals('unverified', $response->status);
    }

    public function test_send_verification_throws_exception_on_error(): void
    {
        $mockClient = Mockery::mock(\Calisero\Sms\CaliseroClient::class);

        $mockClient->shouldReceive('sendVerification')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->app->instance(\Calisero\Sms\CaliseroClient::class, $mockClient);

        $this->expectException(\Exception::class);

        Calisero::sendVerification([
            'to' => '+40712345678',
        ]);
    }

    public function test_check_verification_valid_code(): void
    {
        $mockClient = Mockery::mock(\Calisero\Sms\CaliseroClient::class);
        $mockResponse = (object) [
            'phone' => '+40712345678',
            'status' => 'verified',
            'verified_at' => now()->toIso8601String(),
        ];

        $mockClient->shouldReceive('checkVerification')
            ->once()
            ->with(Mockery::on(function ($params) {
                return '+40712345678' === $params['phone']
                    && '123456' === $params['code'];
            }))
            ->andReturn($mockResponse);

        $this->app->instance(\Calisero\Sms\CaliseroClient::class, $mockClient);

        $result = Calisero::checkVerification([
            'to' => '+40712345678',
            'code' => '123456',
        ]);

        $this->assertEquals('verified', $result->status);
        $this->assertNotNull($result->verified_at);
    }

    public function test_check_verification_invalid_code(): void
    {
        $mockClient = Mockery::mock(\Calisero\Sms\CaliseroClient::class);
        $mockResponse = (object) [
            'phone' => '+40712345678',
            'status' => 'unverified',
            'verified_at' => null,
        ];

        $mockClient->shouldReceive('checkVerification')
            ->once()
            ->with(Mockery::on(function ($params) {
                return '+40712345678' === $params['phone']
                    && '999999' === $params['code'];
            }))
            ->andReturn($mockResponse);

        $this->app->instance(\Calisero\Sms\CaliseroClient::class, $mockClient);

        $result = Calisero::checkVerification([
            'to' => '+40712345678',
            'code' => '999999',
        ]);

        $this->assertEquals('unverified', $result->status);
        $this->assertNull($result->verified_at);
    }
}
