<?php

use BapCat\Hashing\Hash;
use BapCat\Hashing\Hasher;

class HashTest extends PHPUnit_Framework_TestCase {
  private function makeHash($hash) {
    return $this->getMockForAbstractClass(Hash::class, [$hash, '/^test$/']);
  }
  
  public function testConstructingWithValidHash() {
    $hash = $this->makeHash('test');
  }
  
  public function testConstructingWithNull() {
    $this->setExpectedException(InvalidArgumentException::class);
    $hash = $this->makeHash(null);
  }
  
  public function testConstructingWithInvalidString() {
    $this->setExpectedException(InvalidArgumentException::class);
    $hash = $this->makeHash('bad');
  }
  
  public function testConstructingWithWrongDataType() {
    $this->setExpectedException(InvalidArgumentException::class);
    $hash = $this->makeHash(true);
  }
  
  public function testGetRaw() {
    $input = 'test';
    $hash = $this->makeHash($input);
    $this->assertSame($hash->raw, $input);
  }
  
  public function testToString() {
    $input = 'test';
    $hash = $this->makeHash($input);
    $this->assertSame((string)$hash, $input);
  }
  
  public function testJsonEncode() {
    $input = 'test';
    $hash = $this->makeHash($input);
    $this->assertSame(json_encode($hash), "\"$input\"");
  }
}
