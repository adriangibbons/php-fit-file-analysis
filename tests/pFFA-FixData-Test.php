<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class FixDataTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'road-cycling.fit';
    private $filename2 = 'power-analysis.fit';
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
    }
    
    /**
     * Original road-cycling.fit before fixData() contains:
     * 
     * record message   | count()
     * -----------------+--------
     * timestamp        | 4317
     * position_lat     | 4309 <- test
     * position_long    | 4309 <- test
     * distance         | 4309 <- test
     * altitude         | 4317
     * speed            | 4309 <- test
     * heart_rate       | 4316 <- test
     * temperature      | 4317
     */
    public function testFixData_before()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename);
        
        $this->assertEquals(4309, count($pFFA->data_mesgs['record']['position_lat']));
        $this->assertEquals(4309, count($pFFA->data_mesgs['record']['position_long']));
        $this->assertEquals(4309, count($pFFA->data_mesgs['record']['distance']));
        $this->assertEquals(4309, count($pFFA->data_mesgs['record']['speed']));
        $this->assertEquals(4316, count($pFFA->data_mesgs['record']['heart_rate']));
        
        $pFFA2 = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename2);
        $this->assertEquals(3043, count($pFFA2->data_mesgs['record']['cadence']));
        $this->assertEquals(3043, count($pFFA2->data_mesgs['record']['power']));
    }
    
    /**
     * $pFFA->data_mesgs['record']['heart_rate']
     *         [805987191 => 118],
     *         [805987192 => missing],
     *         [805987193 => 117]
     */
    public function testFixData_hr_missing_key()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename);
        
        $hr_missing_key = array_diff($pFFA->data_mesgs['record']['timestamp'], array_keys($pFFA->data_mesgs['record']['heart_rate']));
        $this->assertEquals([3036 => 1437052792], $hr_missing_key);
    }
    
    public function testFixData_after()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => ['all']]);
        $this->assertEquals(4317, count($pFFA->data_mesgs['record']['position_lat']));
        $this->assertEquals(4317, count($pFFA->data_mesgs['record']['position_long']));
        $this->assertEquals(4317, count($pFFA->data_mesgs['record']['distance']));
        $this->assertEquals(4317, count($pFFA->data_mesgs['record']['speed']));
        $this->assertEquals(4317, count($pFFA->data_mesgs['record']['heart_rate']));
        
        $pFFA2 = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename2, ['fix_data' => ['cadence', 'power']]);
        $this->assertEquals(3043, count($pFFA2->data_mesgs['record']['cadence']));
        $this->assertEquals(3043, count($pFFA2->data_mesgs['record']['power']));
    }
    
    /**
     * $pFFA->data_mesgs['record']['heart_rate']
     *         [805987191 => 118],
     *         [805987192 => 117.5],
     *         [805987193 => 117]
     */
    public function testFixData_hr_missing_key_fixed()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => ['heart_rate']]);
        
        $this->assertEquals(117.5, $pFFA->data_mesgs['record']['heart_rate'][1437052792]);
    }
    
    public function testFixData_validate_options_pass()
    {
        // Positive testing
        $valid_options = ['all', 'cadence', 'distance', 'heart_rate', 'lat_lon', 'speed', 'power'];
        foreach($valid_options as $valid_option) {
            $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => [$valid_option]]);
        }
    }
    
    public function testFixData_data_every_second()
    {
        $options = [
        		'fix_data'          => ['speed'],
                'data_every_second'	=> true,
        		'units'             => 'raw',
        ];
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, $options);
        
        $this->assertEquals(6847, count($pFFA->data_mesgs['record']['speed']));
    }
    
    /**
     * @expectedException Exception
     */
    public function testFixData_validate_options_fail()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => ['INVALID']]);
    }
    
    /**
     * @expectedException Exception
     */
    public function testFixData_invalid_pace_option()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['pace' => 'INVALID']);
    }
    
    /**
     * @expectedException Exception
     */
    public function testFixData_invalid_pace_option2()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['pace' => 123456]);
    }
}
