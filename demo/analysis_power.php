<?php
	require('classes/php-FIT-File-Analysis.php');
	
	try {
		$file = '';
		
		$options = [
	//		'fix_data' => ['all'],
			'units' => ['metric']
		];
		$pFFA = new phpFITFileAnalysis($file, $options);
		
		// Google Time Zone API
		$date = new DateTime("1989-12-31", new DateTimeZone("UTC"));  // timestamp[s]: seconds since UTC 00:00:00 Dec 31 1989
		$date_s = $date->getTimestamp() + $pFFA->data_mesgs['session']['start_time'];
		
		$url_tz = "https://maps.googleapis.com/maps/api/timezone/json?location=".reset($pFFA->data_mesgs['record']['position_lat']).','.reset($pFFA->data_mesgs['record']['position_long'])."&timestamp=".$date_s."&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE";
		
		$result = file_get_contents("$url_tz");
		$json_tz = json_decode($result);
		if($json_tz->status == "OK") {
			$date_s = $date_s + $json_tz->rawOffset + $json_tz->dstOffset;
		}
		$date->setTimestamp($date_s);
		
		$power_histogram = $pFFA->power_histogram();
		$power_table = $pFFA->power_partioned(312);
		$power_pie_chart = $pFFA->partition_data('power', $pFFA->power_zones(312), true, false);
	}
	catch(Exception $e) {
		echo 'caught exception: '.$e->getMessage();
		die();
	}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>php-FIT-File-Analysis demo</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
<link rel="stylesheet" href="css/style.css">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body>
<div class="jumbotron">
  <div class="container">
    <h2><strong>php-FIT-File-Analysis </strong><small>A PHP class for analysing FIT files created by Garmin GPS devices.</small></h2>
    <p>This is a demonstration of the phpFITFileAnalysis class available on <a class="btn btn-default btn-lg" href="https://github.com/adriangibbons/php-FIT-File-Analysis" target="_blank" role="button"><i class="fa fa-github"></i> GitHub</a></p>
  </div>
</div>
<div class="container">
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
  <div class="row">
    <div class="col-md-10 col-md-offset-1">
        
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Power Distribution (histogram)</h3>
        </div>
        <div class="panel-body">
          <div id="power_distribution" style="width:100%; height:300px"></div>
        </div>
      </div>
        
      <div class="panel panel-default">
        <div class="panel-heading" role="tab" id="headingThree">
          <h3 class="panel-title"><i class="fa fa-pie-chart"></i> Power Zones</h3>
        </div>
        <div class="panel-body">
          <div class="col-md-6 col-md-offset-1">
            <table class="table table-bordered table-striped">
              <tbody>
              	<?php
					foreach($power_table as $key => $value) {
						echo '<tr>';
						echo '<td>'.$key.' w</td><td>'.$value.' %</td>';
						echo '</tr>';
					}
				?>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="col-md-4">
            <div id="power_pie_chart" style="width:100%; height:250px"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.pie.min.js"></script>
<script type="text/javascript">
  $(document).ready( function() {
    
	var power_distribution_options = {
      points: { show: false },
      xaxis: {
        show: true,
        min: 0,
        tickSize: 100,
        tickFormatter: function(label, series) { return label.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ' w'; }
      },
      yaxis: {
        min: 0,
        label: 'time in zone',
        tickSize: 300,
        tickFormatter: function(label, series) {
          if(label == 0) return "";
          return (label / 60) + ' min';
        }
      },
      grid: {
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        }
      },
      legend: { show: false }
    };
	var power_distribution = {
      'color': 'rgba(77, 167, 77, 0.8)',
	  bars: { show: true, zero: false, barWidth: 25, fillColor: "rgba(77, 167, 77, 0.5)", lineWidth: 1 },
      'data': [
<?php
	foreach($power_histogram as $key => $value) {
		echo '['.$key.', '.$value.'], ';
	}
?>
      ]
    };
	
    var power_pie_chart_options = {
      series: {
        pie: {
          radius: 1,
          innerRadius: 0.5,
          show: true,
          label: { show: false }
        }
      },
      legend: { show: false }
    };
    
	var power_pie_chart = [
      {
        label: "Active_Recovery",
        data: <?php echo $power_pie_chart[0]; ?>,
        "color": "rgba(217, 83, 79, 0.2)"
      },
      {
        label: "Endurance",
        data: <?php echo $power_pie_chart[1]; ?>,
        "color": "rgba(217, 83, 79, 0.35)"
      },
      {
        label: "Tempo",
        data: <?php echo $power_pie_chart[2]; ?>,
        "color": "rgba(217, 83, 79, 0.5)"
      },
      {
        label: "Threshold",
        data: <?php echo $power_pie_chart[3]; ?>,
        "color": "rgba(217, 83, 79, 0.65)"
      },
      {
        label: "VO2max",
        data: <?php echo $power_pie_chart[4]; ?>,
        "color": "rgba(217, 83, 79, 0.7)"
      },
      {
        label: "Anaerobic",
        data: <?php echo $power_pie_chart[5]; ?>,
        "color": "rgba(217, 83, 79, 0.85)"
      },
      {
        label: "Neuromuscular",
        data: <?php echo $power_pie_chart[6]; ?>,
        "color": "rgba(217, 83, 79, 1)"
      }
    ];
    
    $.plot('#power_distribution', [power_distribution], power_distribution_options);
    
    $.plot('#power_pie_chart', power_pie_chart, power_pie_chart_options);
  });
</script>
</body>
</html>