<?php

namespace Farm\Backend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPFrarm\Core\Database;

class DatabaseCallProcedureValidationTest extends TestCase
{
    public function testRejectsInvalidProcedureName(): void
    {
        $this->expectException(\RuntimeException::class);
        Database::callProcedure('users;DROP TABLE users');
    }
}
