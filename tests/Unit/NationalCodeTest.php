<?php

namespace Tests\Unit;

use App\Support\NationalCode;
use PHPUnit\Framework\TestCase;

class NationalCodeTest extends TestCase
{
    public function test_sample_code_is_valid(): void
    {
        $this->assertTrue(NationalCode::isValid(NationalCode::sample()));
    }

    public function test_code_may_start_with_zero(): void
    {
        $this->assertTrue(NationalCode::isValid('0499370899'));
    }

    public function test_invalid_checksum_is_rejected(): void
    {
        $this->assertFalse(NationalCode::isValid('1234567890'));
    }

    public function test_persian_digits_are_invalid(): void
    {
        $this->assertFalse(NationalCode::isValid('۱۲۳۴۵۶۷۸۹۱'));
    }

    public function test_short_code_is_invalid(): void
    {
        $this->assertFalse(NationalCode::isValid('049937089'));
    }
}
