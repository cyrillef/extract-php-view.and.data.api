<?php

use BapCat\Hashing\FastHasher;
use BapCat\Hashing\Algorithms\Crc32FastHash;
use BapCat\Hashing\Algorithms\Crc32FastHasher;

class FastHashTester extends  PHPUnit_Framework_TestCase {
  public function testCrc32() {
    $this->doHash(new Crc32FastHasher(), 'crc32', 'Test');
  }
  
  private function doHash(FastHasher $hasher, $algo, $data) {
    $hash = $hasher->make($data);
    
    $this->assertInstanceOf(Crc32FastHash::class, $hash);
    $this->assertSame(hash($algo, $data), (string)$hash);
    $this->assertTrue($hasher->check($data, $hash));
  }
}
