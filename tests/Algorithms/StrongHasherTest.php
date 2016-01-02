<?php

use BapCat\Hashing\StrongHasher;
use BapCat\Hashing\Algorithms\Sha256StrongHash;
use BapCat\Hashing\Algorithms\Sha256StrongHasher;

class StrongHasherTest extends  PHPUnit_Framework_TestCase {
  public function testSha256() {
    $this->doHash(new Sha256StrongHasher(), 'sha256', 'Test');
  }
  
  private function doHash(StrongHasher $hasher, $algo, $data) {
    $hash = $hasher->make($data);
    
    $this->assertInstanceOf(Sha256StrongHash::class, $hash);
    $this->assertSame(hash($algo, $data), (string)$hash);
    $this->assertTrue($hasher->check($data, $hash));
  }
}
