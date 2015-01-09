# php-FIT-File-Reader
<p>A PHP class for reading FIT files created by Garmin GPS devices.</p>
<h3>What is a FIT file?</h3>
<p>FIT or Flexible and Interoperable Data Transfer is a file format used for GPS tracks and routes. It is used by newer Garmin fitness GPS devices, including the Edge and Forerunner, that are popular with cyclists and runners.</p>
<p>The FIT format is similar to Garmin's Training Center XML (.TCX) format, which extends the GPS Exchange Format (.GPX). It enables sensor data (such as heart rate, cadence, and power) to be captured along with the GPS location and time information; as well as providing summary information for activities, sessions, and laps.</p>
<p>The key difference being that the files are much smaller, as they are binary-encoded rather than being bloated XML-like text ASCII documents. For example, a FIT file may be in the region of 250Kb in size, compared to approximately 5Mb for its equivalent TCX file. This enables them to be uploaded to fitness websites such as Garmin Connect, Strava, MapMyRide and Runkeeper etc.</p>

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
<li>The PHP class does not have hyphens (they're not allowed at the time this was written).</li>
<li>The only mandatory parameter required when creating an instance is the path to the FIT file that you want to load.</li>
</ol>
<p>There are more <b>Optional Parameters</b> that can be supplied. These are listed below.</p>
<p>The object will automatically load the FIT file and iterate through its contents. It will store any data it finds in arrays, which are accessible via the getData(...) method, for example:</p>
```php
<?php
    $chartData = $pFFR->getData();
?>
```
<p>See <b>Accessing the Data</b> below for more information.</p>

<h3>Optional Parameters</h3>
<p>To-do...</p>

<h3>Accessing the Data</h3>
<p>To-do...</p>

<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (http://www.thisisant.com/resources/fit), which has its own 'Flexible and Interoperable Data Transfer (FIT) Protocol License Terms and Conditions'.</p>
