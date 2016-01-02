<?php

use BapCat\Hashing\WeakHash;
use BapCat\Hashing\WeakHasher;

class WeakHashTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->hasher = $this
      ->getMockBuilder(WeakHasher::class)
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
    return $this->getMockForAbstractClass(WeakHash::class, [$hash, $this->hasher, '/^test$/']);
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
