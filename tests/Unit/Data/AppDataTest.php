<?php

namespace Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use WP2\Update\Data\AppData;

class AppDataTest extends TestCase
{
    public function testEncryptionAndDecryption()
    {
        $appData = new AppData();
        $encrypted = $appData->encrypt('test-data');
        $decrypted = $appData->decrypt($encrypted);

        $this->assertEquals('test-data', $decrypted);
    }

    public function testLegacyMetadataHandling()
    {
        $appData = new AppData();
        $legacyData = $appData->handleLegacyMetadata(['key' => 'value']);

        $this->assertArrayHasKey('key', $legacyData);
    }
}