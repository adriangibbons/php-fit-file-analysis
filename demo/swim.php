<?php
/**
 * Demonstration of the phpFITFileAnalysis class using Twitter Bootstrap framework
 * https://github.com/adriangibbons/phpFITFileAnalysis
 *
 * If you find this useful, feel free to drop me a line at Adrian.GitHub@gmail.com
 */
    require __DIR__ . '/../src/phpFITFileAnalysis.php';
try {
    $file = '/fit_files/swim.fit';
        
    $options = [
    //  'fix_data'    => [],
      'units'       => 'raw',
    //  'pace'        => false
    ];
    $pFFA = new adriangibbons\phpFITFileAnalysis(__DIR__ . $file, $options);
} catch (Exception $e) {
    echo 'caught exception: '.$e->getMessage();
    die();
}
$units = 'm';
$pool_length = $pFFA->data_mesgs['session']['pool_length'];
$total_distance = number_format($pFFA->data_mesgs['record']['distance']);
if ($pFFA->enumData('display_measure', $pFFA->data_mesgs['session']['pool_length_unit']) == 'statute') {
    $pool_length = round($pFFA->data_mesgs['session']['pool_length'] * 1.0936133);
    $total_distance = number_format($pFFA->data_mesgs['record']['distance'] * 1.0936133);
    $units = 'yd';
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
          <dd><?php echo $pFFA->manufacturer() . ' ' . $pFFA->product(); ?></dd>
          <dt>Sport: </dt>
          <dd><?php echo $pFFA->sport(); ?></dd>
          <dt>Pool length: </dt>
          <dd><?php echo $pool_length.' '.$units; ?></dd>
          <dt>Duration: </dt>
          <dd><?php echo gmdate('H:i:s', $pFFA->data_mesgs['session']['total_elapsed_time']); ?></dd>
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
            $lengths = count($pFFA->data_mesgs['length']['total_timer_time']);
            $active_length = 0;
            for ($i=0; $i<$lengths; $i++) {
                $min = floor($pFFA->data_mesgs['length']['total_timer_time'][$i] / 60);
                $sec = number_format($pFFA->data_mesgs['length']['total_timer_time'][$i] - ($min*60), 1);
                $dur = $min.':'.$sec;
                if ($pFFA->enumData('length_type', $pFFA->data_mesgs['length']['length_type'][$i]) == 'active') {
                    echo '<tr>';
                    echo '<td>'.($i+1).'</td>';
                    echo '<td>'.$dur.'</td>';
                    echo '<td>'.$pFFA->data_mesgs['length']['total_strokes'][$i].'</td>';
                    echo '<td>'.$pFFA->enumData('swim_stroke', $pFFA->data_mesgs['length']['swim_stroke'][$active_length]).'</td>';
                    echo '<td></td>';
                    echo '</tr>';
                    $active_length++;
                } else {
                    echo '<tr class="danger">';
                    echo '<td>'.($i+1).'</td>';
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
      yaxes: [ { transform: function (v) { return -v; }, inverseTransform: function (v) { return -v; }, tickFormatter: function(label, series) { return label + ' s'; } },
               { alignTicksWithAxis: 1, position: "right", } ],
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
for ($i=0; $i<$lengths; $i++) {
    if ($pFFA->enumData('length_type', $pFFA->data_mesgs['length']['length_type'][$i]) == 'active') {
        $tmp[] = '['.$i.', '.$pFFA->data_mesgs['length']['total_timer_time'][$i].']';
    }
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
for ($i=0; $i<$lengths; $i++) {
    if ($pFFA->enumData('length_type', $pFFA->data_mesgs['length']['length_type'][$i]) == 'active') {
        $tmp[] = '['.$i.', '.$pFFA->data_mesgs['length']['total_strokes'][$i].']';
    }
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