<?php

namespace App\Tests\Service;

use App\Auth\Service\TemporaryPasswordGenerator;
use PHPUnit\Framework\TestCase;

class TemporaryPasswordGeneratorTest extends TestCase
{
    public function testGenerateReturnsStrongPasswordWithExpectedLength(): void
    {
        $generator = new TemporaryPasswordGenerator();

        $password = $generator->generate();

        $this->assertSame(16, strlen($password));
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[^a-zA-Z0-9]/', $password);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]/', $password);
    }

    public function testGenerateNeverStartsWithSymbol(): void
    {
        $generator = new TemporaryPasswordGenerator();

        for ($i = 0; $i < 200; $i++) {
            $password = $generator->generate();
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]/', $password);
        }
    }
}
