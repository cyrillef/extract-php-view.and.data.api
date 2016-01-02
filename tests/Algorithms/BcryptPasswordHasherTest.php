<?php

use BapCat\Hashing\Algorithms\BcryptPasswordHash;
use BapCat\Hashing\Algorithms\BcryptPasswordHasher;
use BapCat\Hashing\PasswordHasher;
use BapCat\Values\Password;

class BcryptPasswordHashTester extends PHPUnit_Framework_TestCase {
  public function testBcrypt() {
    $hasher = new BcryptPasswordHasher();
    $this->doHash($hasher, PASSWORD_DEFAULT, 'Test test');
  }
  
  private function doHash(PasswordHasher $hasher, $algo, $password) {
    $password = new Password($password);
    
    $hash = $hasher->make($password);
    
    $this->assertInstanceOf(BcryptPasswordHash::class, $hash);
    $this->assertTrue(password_verify((string)$password, (string)$hash));
    
    $hash = new BcryptPasswordHash(password_hash((string)$password, $algo), $hasher);
    $this->assertTrue($hasher->check($password, $hash));
    $this->assertFalse($hasher->needsRehash($hash));
    
    $hash = new BcryptPasswordHash(password_hash((string)$password, $algo, ['cost' => 9]), $hasher);
    $this->assertTrue($hasher->needsRehash($hash));
  }
}
