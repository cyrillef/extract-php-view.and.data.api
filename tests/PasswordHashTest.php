<?php

use BapCat\Hashing\PasswordHash;
use BapCat\Hashing\PasswordHasher;
use BapCat\Values\Password;

class PasswordHashTest extends PHPUnit_Framework_TestCase {
  public function setUp() {
    $this->hasher = $this
      ->getMockBuilder(PasswordHasher::class)
      ->setMethods(['check', 'needsRehash'])
      ->getMockForAbstractClass()
    ;
    
    $this->hasher
      ->method('check')
      ->will($this->returnCallback(function($input) {
        return $input == 'testtest';
      }))
    ;
    
    $this->hasher
      ->method('needsRehash')
      ->will($this->returnCallback(function() {
        return true;
      }))
    ;
  }
  
  private function makeHash($hash) {
    return $this->getMockForAbstractClass(PasswordHash::class, [$hash, $this->hasher, '/^testtest$/']);
  }
  
  public function testConstructingWithValidHash() {
    $hash = $this->makeHash('testtest');
  }
  
  public function testCheck() {
    $input = 'testtest';
    $hash = $this->makeHash($input);
    
    $this->assertTrue($hash->check(new Password($input)));
    $this->assertFalse($hash->check(new Password('badbadbad')));
  }
  
  public function testNeedsRehash() {
    $input = 'testtest';
    $hash = $this->makeHash($input);
    
    $this->assertTrue($hash->needsRehash());
  }
}
