# php FIT File Reader
<p>A PHP class for reading FIT files created by Garmin GPS devices.</p>
<p>A live demonstration can be found <a href="http://www.adriangibbons.com/php-FIT-File-Reader-demo/" target="new">here</a>.</p>
<h3>What is a FIT file?</h3>
<p>FIT or Flexible and Interoperable Data Transfer is a file format used for GPS tracks and routes. It is used by newer Garmin fitness GPS devices, including the Edge and Forerunner series, which are popular with cyclists and runners.</p>
<p>Visit the FAQ page within the Wiki for more information.</p>
<br>
<h3>How do I use php-FIT-File-Reader with my PHP-driven website?</h3>
<p>Download the class from GitHub and put it somewhere appropriate (e.g. classes/). A conscious effort has been made to keep everything in a single file.</p>
<p>Then include the file on the PHP page where you want to use it and instantiate an object of the class:</p>
```php
<?php
    include('classes/php-FIT-File-Reader.php');
    $pFFR = new phpFITFileReader('fit_files/my_fit_file.fit');
?>
```
<p>Note two things:</p>
<ol>
<li>The PHP class name does not have hyphens.</li>
<li>The only mandatory parameter required when creating an instance is the path to the FIT file that you want to load.</li>
</ol>
<p>There are more <b>Optional Parameters</b> that can be supplied. These are described in more detail further down this page.</p>
<p>The object will automatically load the FIT file and iterate through its contents. It will store any data it finds in arrays, which are accessible via the public data variable.
<br><br>
<h3>Accessing the Data</h3>
<p>Data read by the class are stored in associative arrays, which are accessible via the public data variable:</p>
```php
$pFFR->data_mesgs
```
<p>The array indexes are the names of the messages and fields that they contain. For example:</p>
```php
// Contains an array of all heart_rate data read from the file, indexed by timestamp
$pFFR->data_mesgs['record']['heart_rate']
// Contains an integer identifying the number of laps
$pFFR->data_mesgs['session']['num_laps']
```
<strong>OK, but how do I know what messages and fields are in my file?</strong>
<p>You could either iterate through the $pFFR->data_mesgs array, or take a look at the debug information you can dump to a webpage:</p>
```php
// Option 1. Iterate through the $pFFR->data_mesgs array
foreach($pFFR->data_mesgs as $mesg_key => $mesg) {  // Iterate the array and output the messages
    echo "<strong>Found Message: $mesg_key</strong><br>";
    foreach($mesg as $field_key => $field) {  // Iterate each message and output the fields
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Found Field: $mesg_key -> $field_key<br>";
    }
    echo "<br>";
}

// Option 2. Show the debug information
$pFFR->show_debug_info();  // Quite a lot of info...
```
<strong>How about some real-world examples?</strong>
```php
// Get Max and Avg Speed
echo "Maximum Speed: ".max($pFFR->data_mesgs['record']['speed'])."<br>";
echo "Average Speed: ".( array_sum($pFFR->data_mesgs['record']['speed']) / count($pFFR->data_mesgs['record']['speed']) )."<br>";

// Put HR data into a JavaScript array for use in a Chart
echo "var chartData = [";
    foreach( $pFFR->data_mesgs['record']['heart_rate'] as $timestamp => $hr_value ) {
        echo "[$timestamp,$hr_value],";
    }
echo "];";
```
<strong>Enumerated Data</strong>
<p>The FIT protocol makes use of enumerated data types. Where these values have been identified in the FIT SDK, they have been included in the class as a private variable: $enum_data.</p>
<p>A public function is available, which will return the enumerated value for a given message type. For example:</p>
```php
// Access data stored within the private class variable $enum_data
// $pFFR->get_enum_data($type, $value)
// e.g.
echo $pFFR->get_enum_data('sport', 2));  // returns 'cycling'
echo $pFFR->get_enum_data('manufacturer', $this->data_mesgs['device_info']['manufacturer']);  // returns 'Garmin';
echo $pFFR->get_manufacturer();  // Short-hand for above
```
<p>In addition, public functions provide a short-hand way to access commonly used enumerated data:</p>
<ul>
<li>get_manufacturer()</li>
<li>get_product()</li>
<li>get_sport()</li>
<li>get_sub_sport()</li>
<li>get_swim_stroke()</li>
</ul>
<h3>Optional Parameters</h3>
<p>There are three optional parameters that can be passed as an associative array when the phpFITFileReader object is instantiated. These are:</p>
<ol>
<li>fix_data</li>
<li>set_units</li>
<li>pace</li>
</ol>
<p>For example:</p>
````php
$options = [
    'fix_data'  => ['cadence', 'distance'],
    'set_units' => 'statute',
    'pace'      => true
];
$pFFR = new phpFITFileReader('my_fit_file.fit', $options);
````
<p>The optional parameters are described in more detail below.</p>
<h4>"Fix" the Data</h4>
<p>FIT files have been observed where some data points are missing for one sensor (e.g. cadence/foot pod), where information has been collected for other sensors (e.g. heart rate) at the same instant. The cause is unknown and typically only a relatively small number of data points are missing. Fixing the issue is probably unnecessary, as each datum is indexed using a timestamp. However, it may be important for your project to have the exact same number of data points for each type of data.</p>
<p><strong>Recognised values: </strong>'all', 'cadence', 'distance', 'heart_rate', 'lat_lon', 'power', 'speed'</p>
<p><strong>Examples: </strong></p>
```php
$options = ['fix_data' => ['all']];  // fix cadence, distance, heart_rate, lat_lon, power, and speed data
$options = ['fix_data' => ['cadence', 'distance']];  // fix cadence and distance data only
$options = ['fix_data' => ['lat_lon']];  // fix position data only
```
<p>If the <em>fix_data</em> array is not supplied, then no "fixing" of the data is performed.</p>
<p>A FIT file might contain the following:</p>
<table>
<thead>
<th></th>
<th># Data Points</th>
<th>Delta (c.f. Timestamps)</th>
</thead>
<tbody>
<tr>
<td>timestamp</td><td>10251</td><td>0</td>
</tr>
<tr>
<td>position_lat</td><td>10236</td><td>25</td>
</tr>
<tr>
<td>position_long</td><td>10236</td><td>25</td>
</tr>
<tr>
<td>altitude</td><td>10251</td><td>0</td>
</tr>
<tr>
<td>heart_rate</td><td>10251</td><td>0</td>
</tr>
<tr>
<td>cadence</td><td>9716</td><td>535</td>
</tr>
<tr>
<td>distance</td><td>10236</td><td>25</td>
</tr>
<tr>
<td>speed</td><td>10236</td><td>25</td>
</tr>
<tr>
<td>power</td><td>10242</td><td>9</td>
</tr>
<tr>
<td>temperature</td><td>10251</td><td>0</td>
</tr>
</tbody>
</table>
<p>As illustrated above, the types of data most susceptible to missing data points are: position_lat, position_long, altitude, heart_rate, cadence, distance, speed, and power.</p>
<p>With the exception of cadence information, missing data points are "fixed" by inserting interpolated values.</p>
<p>For cadence, zeroes are inserted as it is thought that it is likely no data has been collected due to a lack of movement at that point in time.</p>
<p><strong>Interpolation of missing data points</strong></p>
```php
// Do not use code, just for demonstration purposes
var_dump( $pFFR->data_mesgs['record']['temperature'] );  // ['100'=>22, '101'=>22, '102'=>23, '103'=>23, '104'=>23];
var_dump( $pFFR->data_mesgs['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '104'=>10.88];
```
<p>As you can see from the trivial example above, temperature data have been recorded for each of five timestamps (100, 101, 102, 103, and 104). However, distance information has not been recorded for timestamps 102 and 103.</p>
<p>If <em>fix_data</em> includes 'distance', then the class will attempt to insert data into the distance array with the indexes 102 and 103. Values are determined using a linear interpolation between indexes 101(4.01) and 104(10.88).<p>
<p>The result would be:</p>
```php
var_dump( $pFFR->data_mesgs['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '102'=>6.30, '103'=>8.59, '104'=>10.88];
```
<br>
<h4>Set Units</h4>
<p>By default, <strong>metric</strong> units (identified in the table below) are assumed.</p>
<table>
<thead>
<th></th>
<th>Metric<br><em>(DEFAULT)</em></th>
<th>Statute</th>
<th>Raw</th>
</thead>
<tbody>
<tr>
<td>Speed</td><td>kilometers per hour</td><td>miles per hour</td><td>meters per second</td>
</tr>
<tr>
<td>Distance</td><td>kilometers</td><td>miles</td><td>meters</td>
</tr>
<tr>
<td>Altitude</td><td>meters</td><td>feet</td><td>meters</td>
</tr>
<tr>
<td>Latitude</td><td>degrees</td><td>degrees</td><td>semicircles</td>
</tr>
<tr>
<td>Longitude</td><td>degrees</td><td>degrees</td><td>semicircles</td>
</tr>
<tr>
<td>Temperature</td><td>celsius (&#8451;)</td><td>fahrenheit (&#8457;)</td><td>celsius (&#8451;)</td>
</tr>
</tbody>
</table>
<p>You can request <strong>statute</strong> or <strong>raw</strong> units instead of metric. Raw units are those were used by the device that created the FIT file and are native to the FIT standard (i.e. no transformation of values read from the file will occur).</p>
<p>To select the units you require, use one of the following:</p>
```php
$options = ['set_units' => 'statute'];
$options = ['set_units' => 'raw'];
$options = ['set_units' => 'metric'];  // explicit but not necessary, same as default
```
<h4>Pace</h4>
<p>If required by the user, pace can be provided instead of speed. Depending on the units requested, pace will either be in minutes per kilometre (min/km) for metric units; or minutes per mile (min/mi) for statute.</p>
<p>To select pace, use the following option:</p>
```php
$options = ['pace' => true];
```
<p>Pace values will be decimal minutes. To get the seconds, you may wish to do something like:</p>
```php
foreach($pFFR->data_mesgs['record']['speed'] as $key => $value) {
    $min = floor($value);
    $sec = round(60 * ($value - $min));
    echo "pace: $min min $sec sec<br>";
}
```
Note that if 'raw' units are requested then this parameter has no effect on the speed data, as it is left untouched from what was read-in from the file.
<br><br>
<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (<a href="http://www.thisisant.com/resources/fit" target="new">thisisant.com</a>).</p>
<p>As a minimum, I'd recommend reading the three PDFs included in the SDK:</p>
<ol>
<li>FIT File Types Description</li>
<li>FIT SDK Introductory Guide</li>
<li>Flexible & Interoperable Data Transfer (FIT) Protocol</li>
</ol>
<p>Following these, the 'Profile.xls' spreadsheet and then the Java/C/C++ examples.</p>
