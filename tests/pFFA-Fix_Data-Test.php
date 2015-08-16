<?php
error_reporting(E_ALL);
require __DIR__ . '/../src/php-FIT-File-Analysis.php';

class FitTest extends PHPUnit_Framework_TestCase
{
	private $base_dir;
	private $filename = 'road-cycling.fit';
	
	public function setUp()
	{
		$this->base_dir = __DIR__ . '/../demo/fit_files/';
	}
	
	/*
	 * Original road-cycling.fit before fix_data() contains:
	 * 
	 * record message	| count()
	 * -----------------+--------
	 * timestamp		| 4317
	 * position_lat		| 4309 <- test
	 * position_long	| 4309 <- test
	 * distance			| 4309 <- test
	 * altitude			| 4317
	 * speed			| 4309 <- test
	 * heart_rate		| 4316 <- test
	 * temperature		| 4317
	 */
	public function testFixData_before()
	{
		$pFFA = new phpFITFileAnalysis($this->base_dir . $this->filename);
		
		$this->assertEquals(4309, count($pFFA->data_mesgs['record']['position_lat']));
		$this->assertEquals(4309, count($pFFA->data_mesgs['record']['position_long']));
		$this->assertEquals(4309, count($pFFA->data_mesgs['record']['distance']));
		$this->assertEquals(4309, count($pFFA->data_mesgs['record']['speed']));
		$this->assertEquals(4316, count($pFFA->data_mesgs['record']['heart_rate']));
	}
	
	/*
	 * $pFFA->data_mesgs['record']['heart_rate']
	 * 		[805987191 => 118],
	 * 		[805987192 => missing],
	 * 		[805987193 => 117]
	 */
	public function testFixData_hr_missing_key()
	{
		$pFFA = new phpFITFileAnalysis($this->base_dir . $this->filename);
		
		$hr_missing_key = array_diff($pFFA->data_mesgs['record']['timestamp'], array_keys($pFFA->data_mesgs['record']['heart_rate']));
		$this->assertEquals([3036 => 805987192], $hr_missing_key);
	}
	
	public function testFixData_after()
	{
		$pFFA = new phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => ['all']]);
		
		$this->assertEquals(4317, count($pFFA->data_mesgs['record']['position_lat']));
		$this->assertEquals(4317, count($pFFA->data_mesgs['record']['position_long']));
		$this->assertEquals(4317, count($pFFA->data_mesgs['record']['distance']));
		$this->assertEquals(4317, count($pFFA->data_mesgs['record']['speed']));
		$this->assertEquals(4317, count($pFFA->data_mesgs['record']['heart_rate']));
	}
	
	/*
	 * $pFFA->data_mesgs['record']['heart_rate']
	 * 		[805987191 => 118],
	 * 		[805987192 => 117.5],
	 * 		[805987193 => 117]
	 */
	public function testFixData_hr_missing_key_fixed()
	{
		$pFFA = new phpFITFileAnalysis($this->base_dir . $this->filename, ['fix_data' => ['heart_rate']]);
		
		$this->assertEquals(117.5, $pFFA->data_mesgs['record']['heart_rate'][805987192]);
	}
}
?>