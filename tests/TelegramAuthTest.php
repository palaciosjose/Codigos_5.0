<?php
use PHPUnit\Framework\TestCase;
use TelegramBot\Services\TelegramAuth;

class TelegramAuthTest extends TestCase
{
    public function testAuthenticateValidUser()
    {
        $auth = new TelegramAuth();
        $result = $auth->authenticateUser(123456, 'testuser');
        $this->assertIsArray($result);
    }
}
