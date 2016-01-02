<?php

use BapCat\Hashing\Hasher;

class HasherTest extends PHPUnit_Framework_TestCase {
  private $hasher;
  
  public function setUp() {
    $this->hasher = $this
      ->getMockBuilder(Hasher::class)
      ->setMethods(['make'])
      ->getMockForAbstractClass()
    ;
    
    $this->hasher
      ->method('make')
      ->will($this->returnCallback(function($data) {
        return $data;
      }))
    ;
  }
  
  public function testSalt() {
    $this->assertEquals(32, strlen($this->hasher->salt()));
    $this->assertEquals(10, strlen($this->hasher->salt(10)));
    $this->assertNotEquals($this->hasher->salt(), $this->hasher->salt());
  }
  
  public function testRandom() {
    $this->assertNotEquals($this->hasher->random(), $this->hasher->random());
  }
}
