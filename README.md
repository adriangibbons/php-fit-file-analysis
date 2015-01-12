# php FIT File Reader
<p>A PHP class for reading FIT files created by Garmin GPS devices.</p>
<h3>What is a FIT file?</h3>
<p>FIT or Flexible and Interoperable Data Transfer is a file format used for GPS tracks and routes. It is used by newer Garmin fitness GPS devices, including the Edge and Forerunner, that are popular with cyclists and runners.</p>
<p>The FIT format is similar to Garmin's Training Center XML (.TCX) format, which extends the GPS Exchange Format (.GPX). It enables sensor data (such as heart rate, cadence, and power) to be captured along with the GPS location and time information; as well as providing summary information for activities, sessions, and laps.</p>
<p>The key difference is that FIT files are much smaller, as they are binary-encoded rather than being bloated XML-like ASCII documents. For example, a FIT file of 250Kb may have an equivalent TCX file of approximately 5Mb. This enables them to be uploaded to websites such as Garmin Connect, Strava, MapMyRide, Runkeeper, etc relatively quickly.</p>

<h3>Which Devices and Sensors has it been tested with?</h3>
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
<p>If you have a different device or sensor and would like to submit a file for testing, please get in touch!</p>

<h3>How do I use php-FIT-File-Reader with my PHP-driven website?</h3>
<p>It's very easy! Just download the latest version and put it somewhere appropriate (e.g. classes/).</p>
<p>Then include the file on the PHP page where you want to use it and instantiate an object of the class:</p>
```php
<?php
    include('classes/php-FIT-File-Reader.php');
    $pFFR = new phpFITFileReader('fit_files/my_fit_file.fit');
?>
```
<p>Note two things:</p>
<ol>
<li>The PHP class does not have hyphens (PHP does not allow them at the time this was written).</li>
<li>The only mandatory parameter required when creating an instance is the path to the FIT file that you want to load.</li>
</ol>
<p>There are more <b>Optional Parameters</b> that can be supplied. These are listed below.</p>
<p>The object will automatically load the FIT file and iterate through its contents. It will store any data it finds in arrays, which are accessible via the public data variable:</p>
```php
<?php
    $chartData = $pFFR->data;
?>
```
<p>See <b>Accessing the Data</b> below for more information.</p>

<h3>Optional Parameters</h3>
<p>There are two optional parameters that can be passed as an associative array when the phpFITFileReader object is instantiated. These are:</p>
<ol>
<li><em>fix_data_options</em></li>
<li><em>set_units_options</em></li>
</ol>
<p>For example:</p>
````php
<?php
    $options = [
        'fix_data_options' => ['cadence', 'distance'],
        'set_units_options' => ['statute']
    ];
    $pFFR = new phpFITFileReader('my_fit_file.fit', $options);
?>
````
<p>The two are described in more detail below.</p>
<h4>"Fix" the Data</h4>
<p>FIT files have been observed where some data points are missing for one sensor (e.g. cadence/foot pod), where information has been collected for other sensors (e.g. heart rate) at the same instant. The cause is unknown and typically only a relatively small number of data points are missing. Fixing the issue is probably unnecessary, as each datum is indexed using a timestamp. However, it may be important for your project to have the exact same number of data points for each type of data.</p>
<p><strong>Type: </strong>Array</p>
<p><strong>Values: </strong>'all', 'cadence', 'distance', 'heart_rate', 'lat_lon', 'power', 'speed'</p>
<p><strong>Examples: </strong></p>
```php
    $options = ['fix_data_options' => ['all']];  // fix cadence, distance, heart_rate, lat_lon, power, and speed data
    $options = ['fix_data_options' => ['cadence', 'distance']];  // fix cadence and distance data only
    $options = ['fix_data_options' => ['power']];  // fix power data only
```
<p>If the <em>fix_data_options</em> array is not supplied, then no "fixing" of the data is performed.</p>
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
<p>For cadence, zeros are inserted as it is thought that it is likely no data has been collected due to a lack of movement at that point in time.</p>
<p><strong>Example</strong></p>

```php
// Do not use code, just for demonstration purposes.
var_dump( $pFFR->data['record']['temperature'] );  // ['100'=>22, '101'=>22, '102'=>23, '103'=>23, '104'=>23];
var_dump( $pFFR->data['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '104'=>10.88];
```
<p>As you can see from the trivial example above, temperature data has been record for each of five timestamps (100, 101, 102, 103, and 104). However, distance information has not been recorded for timestamps 102 and 103.</p>
<p>If <em>fix_data_options</em> includes 'distance', then the class will attempt to insert data into the distance array with the indexes 102 and 103. Values are determined using a linear interpolation between indexes 101(4.01) and 104(10.88).<p>
<p>The result would be:</p>
```php
var_dump( $pFFR->data['record']['distance'] );  // ['100'=>3.62, '101'=>4.01, '102'=>6.30, '103'=>8.59, '104'=>10.88];
```
<br>
<h4>Set Units</h4>
<p>By default, <em>metric</em> units (identified in the table below) are assumed.</p>
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
<p>You can request <em>statute</em> or <em>raw</em> units instead of metric. Raw units are those were used by the device that created the FIT file and are native to the FIT standard (i.e. no transformation of values read from the file will occur).</p>
<p>To select the units you require, use one of the following:</p>
```php
    $options = ['set_units_options' => ['statute']];
    $options = ['set _ units _options' => ['raw']];
    $options = ['set _ units _options' => ['metric']];  // explicit but not necessary, same as default.
```
<br>
<h3>Accessing the Data</h3>
<p>To-do...</p>

<h3>Where are my FIT files?</h3>
<p>You are may find your .fit files in one of two locations:</p>
<ol>
<li>On the GPS device in the folder Garmin > Activities (plug it into your computer using a USB cable).</li>
<li>C:\ drive > Users > Username > Application Data > Garmin > Devices > Device Number > Activities.</li>
</ol>

<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (http://www.thisisant.com/resources/fit), which has its own 'Flexible and Interoperable Data Transfer (FIT) Protocol License Terms and Conditions'.</p>

<h3>Disclaimer</h3>
<ul>
<li>The files available here are provided "as-is" and without warranty.</li>
<li>Use of the files and/or reliance on the data contained within the files or generated by the software is at your own risk.</li>
<li>GPS-FIT is not liable for any loss (data, reputation, revenue or otherwise) you may experience.</li>
