# php-FIT-File-Reader
<p>A PHP class for reading FIT files created by Garmin GPS devices.</p>
<h3>What is a FIT file?</h3>
<p>FIT or Flexible and Interoperable Data Transfer is a file format used for GPS tracks and routes. It is used by newer Garmin fitness GPS devices, including the Edge and Forerunner, that are popular with cyclists and runners.</p>
<p>The FIT format is similar to Garmin's Training Center XML (.TCX) format, which extends the GPS Exchange Format (.GPX). It enables sensor data (such as heart rate, cadence, and power) to be captured along with the GPS location and time information; as well as providing summary information for activities, sessions, and laps.</p>
<p>The key difference being that the files are much smaller, as they are binary-encoded rather than being bloated XML-like text ASCII documents. For example, a FIT file may be in the region of 250Kb in size, compared to approximately 5Mb for its equivalent TCX file. This enables them to be uploaded to fitness websites such as Garmin Connect, Strava, MapMyRide and Runkeeper etc.</p>

<h3>Acknowledgement</h3>
<p>This class has been created using information available in a Software Development Kit (SDK) made available by ANT (http://www.thisisant.com/resources/fit), which has its own 'Flexible and Interoperable Data Transfer (FIT) Protocol License Terms and Conditions'.</p>
