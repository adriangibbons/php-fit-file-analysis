<?
use adriangibbons\FitAnalysis\phpFITFileAnalysis;

error_reporting(E_ALL);
require __DIR__ . '/../php-FIT-File-Analysis.php';

class FitTest extends PHPUnit_Framework_TestCase
{
    public function testSampleFiles()
    {
        foreach (array_diff(scandir('test_files'), array('..', '.')) as $filename) {
            echo $filename . "\n";

            $pFFA = new phpFITFileAnalysis('test_files/' . $filename, [
                'fix_data' => ['all'],
            ]);
            $this->assertGreaterThan(0, $pFFA->data_mesgs['activity']['timestamp'], "Should have timestamp set");

            if (isset($pFFA->data_mesgs['record'])) {
                $this->assertGreaterThan(0, count($pFFA->data_mesgs['record']['timestamp']), "Should have read non-zero number of records");

                // check if distance from records is between 98-102% of total distance from header
                if (is_array($pFFA->data_mesgs['record']['distance'])) {
                    $distance_difference = abs(end($pFFA->data_mesgs['record']['distance']) - $pFFA->data_mesgs['session']['total_distance'] / 1000);
                    $this->assertLessThan(0.02 * end($pFFA->data_mesgs['record']['distance']), $distance_difference, "Total distance should be similar as distance of last record");
                }

                if (isset($pFFA->data_mesgs['record']['position_lat']) && is_array($pFFA->data_mesgs['record']['position_lat'])) {
                    foreach ($pFFA->data_mesgs['record']['position_lat'] as $key => $value) {
                        if (isset($pFFA->data_mesgs['record']['position_lat'][$key - 1])) {
                            if (abs($pFFA->data_mesgs['record']['position_lat'][$key - 1] - $pFFA->data_mesgs['record']['position_lat'][$key]) > 1) {
                                $this->assertTrue(false, 'Too big jump in latitude');
                            }
                        }
                    }
                }

                if (isset($pFFA->data_mesgs['record']['position_lat']) && is_array($pFFA->data_mesgs['record']['position_long'])) {
                    foreach ($pFFA->data_mesgs['record']['position_long'] as $key => $value) {
                        if (isset($pFFA->data_mesgs['record']['position_long'][$key - 1])) {
                            if (abs($pFFA->data_mesgs['record']['position_long'][$key - 1] - $pFFA->data_mesgs['record']['position_long'][$key]) > 1) {
                                $this->assertTrue(false, 'Too big jump in longitude');
                            }
                        }
                    }
                }
            }
        }
    }
}
