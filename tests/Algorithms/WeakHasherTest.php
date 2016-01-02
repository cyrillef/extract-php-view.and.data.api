<?php

use BapCat\Hashing\WeakHasher;
use BapCat\Hashing\Algorithms\Md5WeakHash;
use BapCat\Hashing\Algorithms\Md5WeakHasher;
use BapCat\Hashing\Algorithms\Sha1WeakHash;
use BapCat\Hashing\Algorithms\Sha1WeakHasher;

class WeakHasherTest extends  PHPUnit_Framework_TestCase {
  public function testMd5() {
    $this->doHash(new Md5WeakHasher(), Md5WeakHash::class, 'md5', 'Test');
  }
  
  public function testSha1() {
    $this->doHash(new Sha1WeakHasher(), Sha1WeakHash::class, 'sha1', 'Test');
  }
  
  private function doHash(WeakHasher $hasher, $class, $algo, $data) {
    $hash = $hasher->make($data);
    
    $this->assertInstanceOf($class, $hash);
    $this->assertSame(hash($algo, $data), (string)$hash);
    $this->assertTrue($hasher->check($data, $hash));
  }
}
