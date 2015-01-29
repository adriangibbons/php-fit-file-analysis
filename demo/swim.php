<?php
	/*
	 * Demonstration of the phpFITFileReader class using Twitter Bootstrap framework
	 * https://github.com/adriangibbons/php-FIT-File-Reader
	 *
	 * If you find this useful, feel free to drop me a line at Adrian.GitHub@gmail.com
	 */
	require('classes/php-FIT-File-Reader.php');
	require('libraries/PolylineEncoder.php');		// https://github.com/dyaaj/polyline-encoder
	require('libraries/Line_DouglasPeucker.php');	// https://github.com/gregallensworth/PHP-Geometry
	try {
		$file = 'fit_files/GitHub_swim_demo.FIT';
		
		$options = [
	//		'fix_data'	=> [],
			'set_units'	=> 'raw',
	//		'pace'		=> false
		];
		$pFFR = new phpFITFileReader($file, $options);
	}
	catch(Exception $e) {
		echo 'caught exception: '.$e->getMessage();
		die();
	}
	
	$units = 'm';
	$pool_length = $pFFR->data_mesgs['session']['pool_length'];
	$total_distance = number_format($pFFR->data_mesgs['record']['distance']);
	if($pFFR->get_enum_data('display_measure', $pFFR->data_mesgs['session']['pool_length_unit']) == 'statute') {
		$pool_length = round($pFFR->data_mesgs['session']['pool_length'] * 1.0936133);
		$total_distance = number_format($pFFR->data_mesgs['record']['distance'] * 1.0936133);
		$units = 'yd';
	}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>php-FIT-File-Reader demo</title>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body>
<div class="jumbotron">
  <div class="container">
    <h2><strong>php-FIT-File-Reader </strong><small>A PHP class for reading FIT files created by Garmin GPS devices.</small></h2>
    <p>This is a demonstration of the phpFITFileReader class available on <a class="btn btn-default btn-lg" href="https://github.com/adriangibbons/php-FIT-File-Reader" target="_blank" role="button"><i class="fa fa-github"></i> GitHub</a></p>
  </div>
</div>
<div class="container">
  <div class="col-md-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-file-code-o"></i> FIT File info</h3>
      </div>
      <div class="panel-body">
        <dl class="dl-horizontal">
          <dt>File: </dt>
          <dd><?php echo $file; ?></dd>
          <dt>Device: </dt>
          <dd><?php echo $pFFR->get_manufacturer() . ' ' . $pFFR->get_product(); ?></dd>
          <dt>Sport: </dt>
          <dd><?php echo $pFFR->get_sport(); ?></dd>
          <dt>Pool length: </dt>
          <dd><?php echo $pool_length.' '.$units; ?></dd>
          <dt>Duration: </dt>
          <dd><?php echo gmdate('H:i:s', $pFFR->data_mesgs['session']['total_elapsed_time']); ?></dd>
          <dt>Total distance: </dt>
          <dd><?php echo $total_distance.' '.$units; ?></dd>
        </dl>
      </div>
    </div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Lap Time vs. Number of Strokes</h3>
      </div>
      <div class="panel-body">
        <div id="lap_times" style="width:100%; height:200px; margin-bottom:8px"></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-tags"></i> Length Message fields</h3>
      </div>
      <div class="panel-body">
        <table class="table table-condensed table-striped">
          <thead>
            <th>Length</th>
            <th>Time (min:sec)</th>
            <th># Strokes</th>
            <th>Stroke</th>
          </thead>
          <tbody>
            <?php
				$lengths = count($pFFR->data_mesgs['length']['timestamp']);
				for($i=0; $i<$lengths; $i++) {
					$min = floor($pFFR->data_mesgs['length']['total_timer_time'][$i] / 60);
					$sec = number_format($pFFR->data_mesgs['length']['total_timer_time'][$i] - ($min*60), 1);
					$dur = $min.':'.$sec;
					if($pFFR->get_enum_data('length_type', $pFFR->data_mesgs['length']['length_type'][$i]) == 'active') {
						echo '<tr>';
						echo '<td>'.($i+1).'</td>';
						echo '<td>'.$dur.'</td>';
						echo '<td>'.$pFFR->data_mesgs['length']['total_strokes'][$i].'</td>';
						echo '<td>'.$pFFR->get_enum_data('swim_stroke', $pFFR->data_mesgs['length']['swim_stroke'][$i]).'</td>';
						echo '</tr>';
					}
					else {
						echo '<tr class="danger">';echo '<td>'.($i+1).'</td>';
						echo '<td>'.$dur.'</td>';
						echo '<td>-</td>';
						echo '<td>Rest</td>';
						echo '</tr>';
					}
				}
			?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script type="text/javascript">
  $(document).ready( function() {
    var chart_options = {
      xaxis: {
        show: false
      },
	  yaxes: [ { min: 15, max: 35, tickFormatter: function(label, series) { return label + ' s'; } },
	           { min: 6, max: 14, alignTicksWithAxis: 1, position: "right", } ],
      grid: {
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        }
      }
    };
	var lap_times = {
      'color': 'rgba(255, 0, 0, 1)',
      'label': 'Lap Time',
      'data': [
<?php
	$tmp = [];
	for($i=0; $i<$lengths; $i++) {
		if($pFFR->get_enum_data('length_type', $pFFR->data_mesgs['length']['length_type'][$i]) == 'active')
			$tmp[] = '['.$i.', '.$pFFR->data_mesgs['length']['total_timer_time'][$i].']';
	}
	echo implode(', ', $tmp);
?>
      ],
      lines: { show: true, fill: false, lineWidth: 2 },
      points: { show: false }
    };
    
	var num_strokes = {
      'color': 'rgba(11, 98, 164, 0.5)',
	  'label': 'Number of Strokes',
      'data': [
<?php
	$tmp = [];
	for($i=0; $i<$lengths; $i++) {
		if($pFFR->get_enum_data('length_type', $pFFR->data_mesgs['length']['length_type'][$i]) == 'active')
			$tmp[] = '['.$i.', '.$pFFR->data_mesgs['length']['total_strokes'][$i].']';
	}
	echo implode(', ', $tmp);
?>
      ],
      bars: { show: true, fill: true, fillColor: "rgba(11, 98, 164, 0.3)", lineWidth: 1 },
      points: { show: false },
	  yaxis: 2
    };
        	
    $.plot('#lap_times', [lap_times, num_strokes], chart_options);
  });
</script>
</body>
</html>