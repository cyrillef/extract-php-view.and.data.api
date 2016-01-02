<?php

use BapCat\Hashing\FastHash;
use BapCat\Hashing\FastHasher;

class FastHashTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->hasher = $this
      ->getMockBuilder(FastHasher::class)
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
    return $this->getMockForAbstractClass(FastHash::class, [$hash, $this->hasher, '/^test$/']);
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
