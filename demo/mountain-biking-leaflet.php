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
    
    // Create an array of lat/long coordiantes for the map
    $position_lat = $pFFA->data_mesgs['record']['position_lat'];
    $position_long = $pFFA->data_mesgs['record']['position_long'];
    $lat_long_combined = [];
    foreach ($position_lat as $key => $value) {  // Assumes every lat has a corresponding long
        $lat_long_combined[] = '[' . $position_lat[$key] . ',' . $position_long[$key] . ']';
    }
    
    // Date with Google timezoneAPI removed
    $date = new DateTime('now', new DateTimeZone('UTC'));
    $date_s = $pFFA->data_mesgs['session']['start_time'];
    $date->setTimestamp($date_s);
    
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>phpFITFileAnalysis demo</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.6.0/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="anonymous" />
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
            <h3 class="panel-title"><i class="fa fa-map-marker"></i> Leaflet Map</h3>
          </div>
          <div class="panel-body">
            <div id="map" style="height:500px; padding-bottom:20px; text-align:center;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.6.0/leaflet.js" integrity="sha512-gZwIG9x3wUXg2hdXF6+rVkLF/0Vi9U8D2Ntg4Ga5I5BZpVkVxlJWbSQtXPSiUTtC0TjtGOmxa1AJPuV0CPthew==" crossorigin="anonymous"></script>
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
    
    // Leaflet maps
    var latlngs = [<?php echo implode(',', $lat_long_combined); ?>];
    var map = L.map('map').setView(<?php echo $lat_long_combined[0]; ?>, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    var polyline = L.polyline(latlngs, {color: 'red'}).addTo(map);
    map.fitBounds(polyline.getBounds());
  });
</script>
</body>
</html>