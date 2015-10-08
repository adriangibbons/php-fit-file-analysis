<?php
error_reporting(E_ALL);
if(!class_exists('adriangibbons\phpFITFileAnalysis')) {
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
}

class QuadrantAnalysisTest extends PHPUnit_Framework_TestCase
{
    private $base_dir;
    private $filename = 'power-analysis.fit';
    private $pFFA;
    
    public function setUp()
    {
        $this->base_dir = __DIR__ . '/../demo/fit_files/';
        $this->pFFA = new adriangibbons\phpFITFileAnalysis($this->base_dir . $this->filename, ['units' => 'raw']);
    }
    
    public function testQuadrantAnalysis()
    {
        $crank_length = 0.175;
        $ftp = 329;
        $selected_cadence = 90;
        $use_timestamps = false;
        
        // quadrantAnalysis() returns an array that can be used to plot CPV vs AEPF.
        $quadrant_plot = $this->pFFA->quadrantAnalysis($crank_length, $ftp, $selected_cadence, $use_timestamps);
        
        $this->assertEquals(90, $quadrant_plot['selected_cadence']);
        
        $this->assertEquals(199.474, $quadrant_plot['aepf_threshold']);
        $this->assertEquals(1.649, $quadrant_plot['cpv_threshold']);
        
        $this->assertEquals(10.48, $quadrant_plot['quad_percent']['hf_hv']);
        $this->assertEquals(10.61, $quadrant_plot['quad_percent']['hf_lv']);
        $this->assertEquals(14.00, $quadrant_plot['quad_percent']['lf_hv']);
        $this->assertEquals(64.91, $quadrant_plot['quad_percent']['lf_lv']);
        
        $this->assertEquals(1.118, $quadrant_plot['plot'][0][0]);
        $this->assertEquals(47.411, $quadrant_plot['plot'][0][1]);
        
        $this->assertEquals(0.367, $quadrant_plot['ftp-25w'][0][0]);
        $this->assertEquals(829.425, $quadrant_plot['ftp-25w'][0][1]);
        
        $this->assertEquals(0.367, $quadrant_plot['ftp'][0][0]);
        $this->assertEquals(897.634, $quadrant_plot['ftp'][0][1]);
        
        $this->assertEquals(0.367, $quadrant_plot['ftp+25w'][0][0]);
        $this->assertEquals(965.843, $quadrant_plot['ftp+25w'][0][1]);
    }
}
