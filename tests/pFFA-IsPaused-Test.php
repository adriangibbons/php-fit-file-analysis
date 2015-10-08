<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class IsPausedTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'power-analysis.fit';
    private $pFFA;
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
        $this->pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw']);
    }
    
    public function testIsPaused()
    {
        // isPaused() returns array of booleans using timestamp as key.
        $is_paused = $this->pFFA->isPaused();
        
        // Assert number of timestamps
        $this->assertEquals(3190, count($is_paused));
        
        // Assert an arbitrary element/timestamps is true
        $this->assertEquals(true, $is_paused[1437477706]);
        
        // Assert an arbitrary element/timestamps is false
        $this->assertEquals(false, $is_paused[1437474517]);
    }
}
