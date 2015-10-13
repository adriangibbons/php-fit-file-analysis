<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class PowerTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'power-analysis.fit';
    private $pFFA;
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
        $this->pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw']);
    }
    
    public function testPower_criticalPower_values()
    {
        $time_periods = [2,5,10,60,300,600,1200,1800,3600];
        $cps = $this->pFFA->criticalPower($time_periods);
        
        array_walk($cps, function(&$v) { $v = round($v, 2); });
        
        $this->assertEquals(551.50, $cps[2]);
        $this->assertEquals(542.20, $cps[5]);
        $this->assertEquals(527.70, $cps[10]);
        $this->assertEquals(452.87, $cps[60]);
        $this->assertEquals(361.99, $cps[300]);
        $this->assertEquals(328.86, $cps[600]);
        $this->assertEquals(260.52, $cps[1200]);
        $this->assertEquals(221.81, $cps[1800]);
    }
    
    public function testPower_criticalPower_time_period_max()
    {
        // 14400 seconds is 4 hours and longer than file duration so should only get one result back (for 2 seconds)
        $time_periods = [2,14400];
        $cps = $this->pFFA->criticalPower($time_periods);
        
        $this->assertEquals(1, count($cps));
    }
    
    public function testPower_powerMetrics()
    {
        $power_metrics = $this->pFFA->powerMetrics(350);
        
        $this->assertEquals(221, $power_metrics['Average Power']);
        $this->assertEquals(671, $power_metrics['Kilojoules']);
        $this->assertEquals(285, $power_metrics['Normalised Power']);
        $this->assertEquals(1.29, $power_metrics['Variability Index']);
        $this->assertEquals(0.81, $power_metrics['Intensity Factor']);
        $this->assertEquals(56, $power_metrics['Training Stress Score']);
    }
    
    public function testPower_power_partitioned()
    {
        // Calls phpFITFileAnalysis::powerZones();
        $power_partioned = $this->pFFA->powerPartioned(350);
        
        $this->assertEquals(45.2, $power_partioned['0-193']);
        $this->assertEquals(10.8, $power_partioned['194-263']);
        $this->assertEquals(18.1, $power_partioned['264-315']);
        $this->assertEquals(17.9, $power_partioned['316-368']);
        $this->assertEquals(4.2, $power_partioned['369-420']);
        $this->assertEquals(3.3, $power_partioned['421-525']);
        $this->assertEquals(0.4, $power_partioned['526+']);
    }
    
    public function testPower_powerHistogram()
    {
        // Calls phpFITFileAnalysis::histogram();
        $power_histogram = $this->pFFA->powerHistogram(100);
        
        $this->assertEquals(374, $power_histogram[0]);
        $this->assertEquals(634, $power_histogram[100]);
        $this->assertEquals(561, $power_histogram[200]);
        $this->assertEquals(1103, $power_histogram[300]);
        $this->assertEquals(301, $power_histogram[400]);
        $this->assertEquals(66, $power_histogram[500]);
        $this->assertEquals(4, $power_histogram[600]);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_criticalPower_no_power()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . 'road-cycling.fit');
        
        $time_periods = [2,14400];
        $cps = $pFFA->criticalPower($time_periods);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_powerMetrics_no_power()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . 'road-cycling.fit');
        
        $power_metrics = $pFFA->powerMetrics(350);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_powerHistogram_no_power()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . 'road-cycling.fit');
        
        $power_metrics = $pFFA->powerHistogram(100);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_powerHistogram_invalid_bucket_width()
    {
        $power_histogram = $this->pFFA->powerHistogram('INVALID');
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_power_partitioned_no_power()
    {
        $pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . 'road-cycling.fit');
        
        $power_partioned = $pFFA->powerPartioned(350);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_power_partitioned_not_array()
    {
        $power_histogram = $this->pFFA->partitionData('power', 123456);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_power_partitioned_not_numeric()
    {
        $power_histogram = $this->pFFA->partitionData('power', [200, 400, 'INVALID']);
    }
    
    /**
     * @expectedException Exception
     */
    public function testPower_power_partitioned_not_ascending()
    {
        $power_histogram = $this->pFFA->partitionData('power', [400, 200]);
    }
}
