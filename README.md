# php-FIT-File-Reader
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
<h4>"Fix" the Data</h4>
<p>Default: no fixing.</p>
<h4>Set Units</h4>
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


<h3>Accessing the Data</h3>
<p>To-do...</p>

<h3>Where are My FIT Files?</h3>
<p>You are likely to find your .fit files in one of two locations:</p>
<ol>
<li>On the GPS device in the folder Garmin > Activities (plug it into your computer using a USB cable).</li>
<li>C:\ drive > Users > Username > Application Data > Garmin > Devices > Device Number > Activities.</li>
</ol>

<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (http://www.thisisant.com/resources/fit), which has its own 'Flexible and Interoperable Data Transfer (FIT) Protocol License Terms and Conditions'.</p>

<h3>Disclaimer</h3>
<ul>
<li>php-FIT-File-Reader and the files associated with it ("the Software") is provided "as-is” and without warranty.</li>
<li>Use of the Software and reliance on the data it provides is at your own risk. GPS-FIT is not liable for any loss (data, reputation, revenue or otherwise) you may experience.</li>