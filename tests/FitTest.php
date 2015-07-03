<?
use adriangibbons\FitAnalysis\phpFITFileAnalysis;

error_reporting(E_ALL);
require __DIR__ . '/../php-FIT-File-Analysis.php';

class FitTest extends PHPUnit_Framework_TestCase
{
    // ...

    public function testSampleFiles()
    {
        foreach (array_diff(scandir('test_files'), array('..', '.')) as $key => $value) {
            $pFFA = new phpFITFileAnalysis('test_files/' . $value);
            $this->assertGreaterThan(0, $pFFA->data_mesgs['activity']['timestamp']);
        }
    }
}
