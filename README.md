# php FIT File Reader
<p>A PHP class for reading FIT files created by Garmin GPS devices.</p>
<p>A live demonstration can be found <a href="http://www.adriangibbons.com/php-FIT-File-Reader-demo/" target="new">here</a>.</p>
<h3>What is a FIT file?</h3>
<p>FIT or Flexible and Interoperable Data Transfer is a file format used for GPS tracks and routes. It is used by newer Garmin fitness GPS devices, including the Edge and Forerunner series, which are popular with cyclists and runners.</p>
<p>The FIT format is similar to Garmin's Training Center XML (.TCX) file format, which extends the GPS Exchange Format (.GPX). It enables sensor data (such as heart rate, cadence, and power) to be captured along with GPS location and time information; as well as providing summary information for an activity and its related sessions and laps.</p>
<p>FIT files are binary-encoded rather than being bloated XML-like ASCII documents. This means that they have a much smaller file size, but are not human-readable with a text editor such as Notepad. For example, a FIT file of 250Kb may have an equivalent TCX file of approximately 5Mb. This enables them to be uploaded to websites such as Garmin Connect, Strava, MapMyRide, Runkeeper, etc relatively quickly.</p>
<br>
<h3>Which Devices and Sensors has php-FIT-File-Reader been tested with?</h3>
<h4>Devices</h4>
<ul>
<li>Garmin Edge 500</li>
<li>Garmin Forerunner 310XT</li>
<li>Garmin Forerunner 910XT</li>
</ul>
<h4>Sensors</h4>
<ul>
<li>Garmin Cadence and Speed</li>
<li>Garmin Foot Pod (cadence)</li>
<li>Garmin Heart Rate Strap (soft material version)</li>
<li>Stages Power Meter</li>
</ul>
<p>If you have a different device or sensor and would like to submit a file for testing, please get in touch or create an issue on the GitHub project page!</p>
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
<br>
<h3>Accessing the Data</h3>
<p>Data read by the class are stored in associative arrays, which are accessible via the public data variable:</p>
```php
$pFFR->data
```
<p>The array indexes are the names of the messages and fields that they contain. For example:</p>
```php
// Contains an array of all heart_rate data read from the file, indexed by timestamp
$pFFR->data['record']['heart_rate']
// Contains an integer identifying the number of laps stored in the first recorded session
$pFFR->data['session'][0]['num_laps']
```
<strong>OK, but how do I know what messages and fields are in my file?</strong>
<p>You could either iterate through the $pFFR->data array, or take a look at the debug information you can dump to a webpage:</p>
```php
// Option 1. Iterate through the $pFFR->data array
foreach($pFFR->data as $mesg_key => $mesg) {  // Iterate the array and output the messages
    echo "Found Message: $mesg_key<br>";
    foreach($mesg as $field_key => $field) {  // Iterate each message and output the fields
        echo "\tFound Field: $field_key<br>";
    }
    echo "<br>";
}

// Option 2. Show the debug information
$pFFR->show_debug_info();  // Quite a lot of info...
```
<strong>How about some real-world examples?</strong>
```php
// Get Max and Avg Speed
echo "Maximum Speed: ".max($pFFR->data['record']['speed'])."<br>";
echo "Average Speed: ".( array_sum($pFFR->data['record']['speed']) / count($pFFR->data['record']['speed']) );

// Put HR data into a JavaScript array for use in a Chart
echo "var chartData = [";
    foreach( $pFFR->data['record']['heart_rate'] as $timestamp => $hr_value ) {
        echo "[$timestamp,$hr_value],";
    }
echo "];";
```
<br>
<h3>Optional Parameters</h3>
<p>There are two optional parameters that can be passed as an associative array when the phpFITFileReader object is instantiated. These are:</p>
<ol>
<li>fix_data</li>
<li>set_units</li>
</ol>
<p>For example:</p>
````php
$options = [
    'fix_data' => ['cadence', 'distance'],
    'set_units' => ['statute']
];
$pFFR = new phpFITFileReader('my_fit_file.fit', $options);
````
<p>The two are described in more detail below.</p>
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
<td>timestamp</td><td>11310</td><td>0</td>
</tr>
<tr>
<td>position_lat</td><td>11285</td><td>25</td>
</tr>
<tr>
<td>position_long</td><td>11285</td><td>25</td>
</tr>
<tr>
<td>altitude</td><td>11310</td><td>0</td>
</tr>
<tr>
<td>heart_rate</td><td>11299</td><td>11</td>
</tr>
<tr>
<td>cadence</td><td>9837</td><td>1473</td>
</tr>
<tr>
<td>distance</td><td>11285</td><td>25</td>
</tr>
<tr>
<td>speed</td><td>11285</td><td>25</td>
</tr>
<tr>
<td>power</td><td>11151</td><td>159</td>
</tr>
<tr>
<td>temperature</td><td>11310</td><td>0</td>
</tr>
</tbody>
</table>
<p>As illustrated above, the types of data most susceptible to missing data points are: position_lat, position_long, altitude, heart_rate, cadence, distance, speed, and power.</p>
<p>With the exception of cadence information, missing data points are "fixed" by inserting interpolated values.</p>
<p>For cadence, zeroes are inserted as it is thought that it is likely no data has been collected due to a lack of movement at that point in time.</p>
<p><strong>Interpolation of missing data points</strong></p>
```php
// Do not use code, just for demonstration purposes
var_dump( $pFFR->data['record']['temperature'] );  // ['100'=>22, '101'=>22, '102'=>23, '103'=>23, '104'=>23];
var_dump( $pFFR->data['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '104'=>10.88];
```
<p>As you can see from the trivial example above, temperature data have been recorded for each of five timestamps (100, 101, 102, 103, and 104). However, distance information has not been recorded for timestamps 102 and 103.</p>
<p>If <em>fix_data</em> includes 'distance', then the class will attempt to insert data into the distance array with the indexes 102 and 103. Values are determined using a linear interpolation between indexes 101(4.01) and 104(10.88).<p>
<p>The result would be:</p>
```php
var_dump( $pFFR->data['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '102'=>6.30, '103'=>8.59, '104'=>10.88];
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
$options = ['set_units' => ['statute']];
$options = ['set_units' => ['raw']];
$options = ['set_units' => ['metric']];  // explicit but not necessary, same as default
```
<br>
<h3>Where are my FIT files?</h3>
<p>You may find your FIT files in one of two locations:</p>
<ol>
<li>On the GPS device in the folder Garmin > Activities (plug it into your computer using a USB cable).</li>
<li>C:\ drive > Users > Username > Application Data > Garmin > Devices > Device Number > Activities.</li>
</ol>
<br>
<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (<a href="http://www.thisisant.com/resources/fit" target="new">thisisant.com</a>).</p>
<p>As a minimum, I'd recommend reading the three PDFs included in the SDK:</p>
<ol>
<li>FIT File Types Description</li>
<li>FIT SDK Introductory Guide</li>
<li>Flexible & Interoperable Data Transfer (FIT) Protocol</li>
</ol>
<p>Following these, the 'Profile.xls' spreadsheet and then the Java/C/C++ examples.</p>
