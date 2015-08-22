<?php
error_reporting(E_ALL);
if(!class_exists('phpFITFileAnalysis')) {
    require __DIR__ . '/../src/php-FIT-File-Analysis.php';
}

class HRTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'power-analysis.fit';
    private $pFFA;
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
        $this->pFFA = new phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw']);
    }
    
    public function testHR_hr_metrics()
    {
        $hr_metrics = $this->pFFA->hr_metrics(50, 190, 170, 'male');
        
        $this->assertEquals(74, $hr_metrics['TRIMPexp']);
        $this->assertEquals(0.8, $hr_metrics['hrIF']);
    }
    
    public function testHR_hr_partioned_HRmaximum()
    {
        // Calls phpFITFileAnalysis::hr_zones_max()
        $hr_partioned_HRmaximum = $this->pFFA->hr_partioned_HRmaximum(190);
        
        $this->assertEquals(19.4, $hr_partioned_HRmaximum['0-113']);
        $this->assertEquals(33.1, $hr_partioned_HRmaximum['114-142']);
        $this->assertEquals(31.4, $hr_partioned_HRmaximum['143-161']);
        $this->assertEquals(16.1, $hr_partioned_HRmaximum['162-180']);
        $this->assertEquals(0, $hr_partioned_HRmaximum['181+']);
    }
    
    public function testHR_hr_partioned_HRreserve()
    {
        // Calls phpFITFileAnalysis::hr_zones_reserve()
        $hr_partioned_HRreserve = $this->pFFA->hr_partioned_HRreserve(50, 190);
        
        $this->assertEquals(45.1, $hr_partioned_HRreserve['0-133']);
        $this->assertEquals(5.8, $hr_partioned_HRreserve['134-140']);
        $this->assertEquals(20.1, $hr_partioned_HRreserve['141-154']);
        $this->assertEquals(15.9, $hr_partioned_HRreserve['155-164']);
        $this->assertEquals(12.5, $hr_partioned_HRreserve['165-174']);
        $this->assertEquals(0.6, $hr_partioned_HRreserve['175-181']);
        $this->assertEquals(0, $hr_partioned_HRreserve['182+']);
    }
}
