<?php

use BapCat\Hashing\StrongHash;
use BapCat\Hashing\StrongHasher;

class StrongHashTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->hasher = $this
      ->getMockBuilder(StrongHasher::class)
      ->setMethods(['check'])
      ->getMockForAbstractClass()
    ;
    
    $this->hasher
      ->method('check')
      ->will($this->returnCallback(function($input) {
        return $input === 'test';
      }))
    ;
  }
  
  private function makeHash($hash) {
    return $this->getMockForAbstractClass(StrongHash::class, [$hash, $this->hasher, '/^test$/']);
  }
  
  public function testConstructingWithValidHash() {
    $hash = $this->makeHash('test');
  }
  
  public function testCheck() {
    $input = 'test';
    $hash = $this->makeHash($input);
    
    $this->assertTrue($hash->check($input));
    $this->assertFalse($hash->check('bad'));
  }
}
