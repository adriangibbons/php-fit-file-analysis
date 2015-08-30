<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class SetUnitsTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'road-cycling.fit';
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
    }
    
    public function testSetUnits_validate_options_pass()
    {
        $valid_options = ['raw', 'statute', 'metric'];
        foreach($valid_options as $valid_option) {
            $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => $valid_option]);
            
            if($valid_option === 'raw') {
                $this->assertEquals(1.286, reset($pFFA->data_mesgs['record']['speed']));
            }
            if($valid_option === 'statute') {
                $this->assertEquals(2.877, reset($pFFA->data_mesgs['record']['speed']));
            }
            if($valid_option === 'metric') {
                $this->assertEquals(4.63, reset($pFFA->data_mesgs['record']['speed']));
            }
        }
    }
    
    /**
     * @expectedException Exception
     */
    public function testSetUnits_validate_options_fail()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'INVALID']);
    }
    
    public function testSetUnits_validate_pace_option_pass()
    {
        $valid_options = [true, false];
        foreach($valid_options as $valid_option) {
            $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw', 'pace' => $valid_option]);
            
            $this->assertEquals(1.286, reset($pFFA->data_mesgs['record']['speed']));
        }
    }
    
    /**
     * @expectedException Exception
     */
    public function testSetUnits_validate_pace_option_fail()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['pace' => 'INVALID']);
    }
}
