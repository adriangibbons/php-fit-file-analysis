<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class HRTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'power-analysis.fit';
    private $pFFA;
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
        $this->pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw']);
    }
    
    public function testHR_hrMetrics()
    {
        $hr_metrics = $this->pFFA->hrMetrics(50, 190, 170, 'male');
        
        $this->assertEquals(74, $hr_metrics['TRIMPexp']);
        $this->assertEquals(0.8, $hr_metrics['hrIF']);
    }
    
    public function testHR_hrPartionedHRmaximum()
    {
        // Calls phpFITFileAnalysis::hrZonesMax()
        $hr_partioned_HRmaximum = $this->pFFA->hrPartionedHRmaximum(190);
        
        $this->assertEquals(19.4, $hr_partioned_HRmaximum['0-113']);
        $this->assertEquals(33.1, $hr_partioned_HRmaximum['114-142']);
        $this->assertEquals(31.4, $hr_partioned_HRmaximum['143-161']);
        $this->assertEquals(16.1, $hr_partioned_HRmaximum['162-180']);
        $this->assertEquals(0, $hr_partioned_HRmaximum['181+']);
    }
    
    public function testHR_hrPartionedHRreserve()
    {
        // Calls phpFITFileAnalysis::hrZonesReserve()
        $hr_partioned_HRreserve = $this->pFFA->hrPartionedHRreserve(50, 190);
        
        $this->assertEquals(45.1, $hr_partioned_HRreserve['0-133']);
        $this->assertEquals(5.8, $hr_partioned_HRreserve['134-140']);
        $this->assertEquals(20.1, $hr_partioned_HRreserve['141-154']);
        $this->assertEquals(15.9, $hr_partioned_HRreserve['155-164']);
        $this->assertEquals(12.5, $hr_partioned_HRreserve['165-174']);
        $this->assertEquals(0.6, $hr_partioned_HRreserve['175-181']);
        $this->assertEquals(0, $hr_partioned_HRreserve['182+']);
    }
}
