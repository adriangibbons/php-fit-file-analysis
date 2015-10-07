<?php
/**
 * Demonstration of the phpFITFileAnalysis class using Twitter Bootstrap framework
 * https://github.com/adriangibbons/phpFITFileAnalysis
 *
 * Not intended to be demonstration of how to best use Google APIs, but works for me!
 *
 * If you find this useful, feel free to drop me a line at Adrian.GitHub@gmail.com
 */
require __DIR__ . '/../src/phpFITFileAnalysis.php';
require __DIR__ . '/libraries/PolylineEncoder.php'; // https://github.com/dyaaj/polyline-encoder
require __DIR__ . '/libraries/Line_DouglasPeucker.php'; // https://github.com/gregallensworth/PHP-Geometry

try {
    $file = '/fit_files/mountain-biking.fit';
        
    $options = [
    // Just using the defaults so no need to provide
    //		'fix_data'	=> [],
    //		'units'		=> 'metric',
    //		'pace'		=> false
    ];
    $pFFA = new adriangibbons\phpFITFileAnalysis(__DIR__ . $file, $options);
} catch (Exception $e) {
    echo 'caught exception: '.$e->getMessage();
    die();
}
    
    // Google Static Maps API
    $position_lat = $pFFA->data_mesgs['record']['position_lat'];
    $position_long = $pFFA->data_mesgs['record']['position_long'];
    $lat_long_combined = [];
    
foreach ($position_lat as $key => $value) {  // Assumes every lat has a corresponding long
    $lat_long_combined[] = [$position_lat[$key],$position_long[$key]];
}
    
    $delta = 0.0001;
do {
    $RDP_LatLng_coord = simplify_RDP($lat_long_combined, $delta);  // Simplify the array of coordinates using the Ramer-Douglas-Peucker algorithm.
    $delta += 0.0001;  // Rough accuracy somewhere between 4m and 12m depending where in the World coordinates are, source http://en.wikipedia.org/wiki/Decimal_degrees
    
    $polylineEncoder = new PolylineEncoder();  // Create an encoded string to pass as the path variable for the Google Static Maps API
    foreach ($RDP_LatLng_coord as $RDP) {
        $polylineEncoder->addPoint($RDP[0], $RDP[1]);
    }
    $map_encoded_polyline = $polylineEncoder->encodedString();
    
    $map_string = '&path=color:red%7Cenc:'.$map_encoded_polyline;
} while (strlen($map_string) > 1800);  // Google Map web service URL limit is 2048 characters. 1800 is arbitrary attempt to stay under 2048
    
    $LatLng_start = implode(',', $lat_long_combined[0]);
    $LatLng_finish = implode(',', $lat_long_combined[count($lat_long_combined)-1]);
    
    $map_string .= '&markers=color:red%7Clabel:F%7C'.$LatLng_finish.'&markers=color:green%7Clabel:S%7C'.$LatLng_start;
    
    
    // Google Time Zone API
    $date = new DateTime('now', new DateTimeZone('UTC'));
    $date_s = $pFFA->data_mesgs['session']['start_time'];
    
    $url_tz = 'https://maps.googleapis.com/maps/api/timezone/json?location='.$LatLng_start.'&timestamp='.$date_s.'&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE';
    
    $result = file_get_contents($url_tz);
    $json_tz = json_decode($result);
if ($json_tz->status == 'OK') {
    $date_s = $date_s + $json_tz->rawOffset + $json_tz->dstOffset;
} else {
    $json_tz->timeZoneName = 'Error';
}
    $date->setTimestamp($date_s);
    
    
    // Google Geocoding API
    $location = 'Error';
    $url_coord = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$LatLng_start.'&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE';
    $result = file_get_contents($url_coord);
    $json_coord = json_decode($result);
if ($json_coord->status == 'OK') {
    foreach ($json_coord->results[0]->address_components as $addressPart) {
        if ((in_array('locality', $addressPart->types)) && (in_array('political', $addressPart->types))) {
            $city = $addressPart->long_name;
        } elseif ((in_array('administrative_area_level_1', $addressPart->types)) && (in_array('political', $addressPart->types))) {
            $state = $addressPart->short_name;
        } elseif ((in_array('country', $addressPart->types)) && (in_array('political', $addressPart->types))) {
            $country = $addressPart->long_name;
        }
    }
    $location = $city.', '.$state.', '.$country;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>phpFITFileAnalysis demo</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body>
<div class="jumbotron">
  <div class="container">
    <h2><strong>phpFITFileAnalysis </strong><small>A PHP class for analysing FIT files created by Garmin GPS devices.</small></h2>
    <p>This is a demonstration of the phpFITFileAnalysis class available on <a class="btn btn-default btn-lg" href="https://github.com/adriangibbons/phpFITFileAnalysis" target="_blank" role="button"><i class="fa fa-github"></i> GitHub</a></p>
  </div>
</div>
<div class="container">
  <div class="row">
    <div class="col-md-6">
      <dl class="dl-horizontal">
        <dt>File: </dt>
        <dd><?php echo $file; ?></dd>
        <dt>Device: </dt>
        <dd><?php echo $pFFA->manufacturer() . ' ' . $pFFA->product(); ?></dd>
        <dt>Sport: </dt>
        <dd><?php echo $pFFA->sport(); ?></dd>
      </dl>
    </div>
    <div class="col-md-6">
      <dl class="dl-horizontal">
        <dt>Recorded: </dt>
        <dd>
<?php
    echo $date->format('D, d-M-y @ g:ia');
?>
        </dd>
        <dt>Duration: </dt>
        <dd><?php echo gmdate('H:i:s', $pFFA->data_mesgs['session']['total_elapsed_time']); ?></dd>
        <dt>Distance: </dt>
        <dd><?php echo max($pFFA->data_mesgs['record']['distance']); ?> km</dd>
      </dl>
    </div>
  </div>
  <div class="col-md-2">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Messages</h3>
      </div>
      <div class="panel-body">
<?php
    // Output all the Messages read in the FIT file.
foreach ($pFFA->data_mesgs as $mesg_key => $mesg) {
    if ($mesg_key == 'record') {
        echo '<strong><mark><u>';
    }
    echo $mesg_key.'<br>';
    if ($mesg_key == 'record') {
        echo '</u></mark></strong>';
    }
}
?>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title">Record Fields</h3>
      </div>
      <div class="panel-body">
<?php
    // Output all the Fields found in Record messages within the FIT file.
foreach ($pFFA->data_mesgs['record'] as $mesg_key => $mesg) {
    if ($mesg_key == 'speed' || $mesg_key == 'heart_rate') {
        echo '<strong><mark><u>';
    }
    echo $mesg_key.'<br>';
    if ($mesg_key == 'speed' || $mesg_key == 'heart_rate') {
        echo '</strong></mark></u>';
    }
}
?>
      </div>
    </div>
  </div>
  <div class="col-md-10">
    <div class="row">
      <div class="col-md-12">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><a href="http://www.flotcharts.org/" target="_blank"><i class="fa fa-pie-chart"></i> Flot Charts</a> <small><i class="fa fa-long-arrow-left"></i> click</small></h3>
          </div>
          <div class="panel-body">
            <div class="col-md-12">
              <div id="speed" style="width:100%; height:75px; margin-bottom:8px"></div>
              <div id="heart_rate" style="width:100%; height:75px; margin-bottom:8px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <div class="panel panel-default">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-map-marker"></i> Google Map</h3>
          </div>
          <div class="panel-body">
            <div id="gmap" style="padding-bottom:20px; text-align:center;">
              <strong>Google Geocoding API: </strong><?php echo $location; ?><br>
              <strong>Google Time Zone API: </strong><?php echo $json_tz->timeZoneName; ?><br><br>
              <img src="https://maps.googleapis.com/maps/api/staticmap?size=640x480&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE<?php echo $map_string; ?>" alt="Google map" border="0">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-12"><hr></div>
  <div class="col-md-10 col-md-offset-2">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-bug"></i> Debug Information</h3>
      </div>
      <div class="panel-body">
        <?php $pFFA->showDebugInfo(); ?>
      </div>
    </div>
  </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script type="text/javascript">
  $(document).ready( function() {
    var speed_options = {
      lines: { show: true, fill: true, fillColor: "rgba(11, 98, 164, 0.4)", lineWidth: 1 },
      points: { show: false },
      xaxis: {
        show: false
      },
      yaxis: {
        max: 35,
        tickFormatter: function(label, series) {
          return label + ' kmh';
        }
      },
      grid: {
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        }
      }
    };
	var speed = {
      'color': 'rgba(11, 98, 164, 0.8)',
      'data': [
<?php
    $tmp = [];
foreach ($pFFA->data_mesgs['record']['speed'] as $key => $value) {
    $tmp[] = '['.$key.', '.$value.']';
}
    echo implode(', ', $tmp);
?>
      ]
    };
	
	var heart_rate_options = {
      lines: { show: true, fill: true, fillColor: 'rgba(255, 0, 0, .4)', lineWidth: 1 },
      points: { show: false },
      xaxis: {
        show: false
      },
      yaxis: {
        min: 80,
        tickFormatter: function(label, series) {
          return label + ' bpm';
        }
      },
      grid: {
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        }
      }
    };
	var heart_rate = {
      'color': 'rgba(255, 0, 0, 0.8)',
      'data': [
<?php
    unset($tmp);
    $tmp = [];
foreach ($pFFA->data_mesgs['record']['heart_rate'] as $key => $value) {
    $tmp[] = '['.$key.', '.$value.']';
}
    echo implode(', ', $tmp);
?>
      ]
    };
        	
    $.plot('#speed', [speed], speed_options);
	$.plot('#heart_rate', [heart_rate], heart_rate_options);
  });
</script>
</body>
</html>