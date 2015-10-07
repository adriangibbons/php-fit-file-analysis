<?php
/**
 * Demonstration of the phpFITFileAnalysis class using Twitter Bootstrap framework
 * https://github.com/adriangibbons/phpFITFileAnalysis
 *
 * If you find this useful, feel free to drop me a line at Adrian.GitHub@gmail.com
 */
require __DIR__ . '/../src/phpFITFileAnalysis.php';
    
try {
    $file = '/fit_files/power-analysis.fit';
        
    $options = [
    //	  'fix_data' => ['all'],
    //    'units' => ['metric']
    ];
    $pFFA = new adriangibbons\phpFITFileAnalysis(__DIR__ . $file, $options);
        
    // Google Time Zone API
    $date = new DateTime('now', new DateTimeZone('UTC'));
    $date_s = $pFFA->data_mesgs['session']['start_time'];
        
    $url_tz = "https://maps.googleapis.com/maps/api/timezone/json?location=".reset($pFFA->data_mesgs['record']['position_lat']).','.reset($pFFA->data_mesgs['record']['position_long'])."&timestamp=".$date_s."&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE";
        
    $result = file_get_contents("$url_tz");
    $json_tz = json_decode($result);
    if ($json_tz->status == "OK") {
        $date_s = $date_s + $json_tz->rawOffset + $json_tz->dstOffset;
    }
    $date->setTimestamp($date_s);
    
    $ftp = 329;
    $hr_metrics = $pFFA->hrMetrics(52, 185, 172, 'male');
    $power_metrics = $pFFA->powerMetrics($ftp);
    $criticalPower = $pFFA->criticalPower([2,3,5,10,30,60,120,300,600,1200,3600,7200,10800,18000]);
    $power_histogram = $pFFA->powerHistogram();
    $power_table = $pFFA->powerPartioned($ftp);
    $power_pie_chart = $pFFA->partitionData('power', $pFFA->powerZones($ftp), true, false);
    $quad_plot = $pFFA->quadrantAnalysis(0.175, $ftp);
} catch (Exception $e) {
    echo 'caught exception: '.$e->getMessage();
    die();
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
      <dd><?php echo $date->format('D, d-M-y @ g:ia'); ?>
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
          <h3 class="panel-title"><i class="fa fa-tachometer"></i> Metrics</h3></a>
        </div>
        <div class="panel-body">
          <div class="col-md-5 col-md-offset-1">
            <h4>Power</h4>
            <?php
            foreach ($power_metrics as $key => $value) {
                echo "$key: $value<br>";
            }
            ?>
          </div>
          <div class="col-md-5">
            <h4>Heart Rate</h4>
            <?php
            foreach ($hr_metrics as $key => $value) {
                echo "$key: $value<br>";
            }
            ?>
          </div>
        </div>
      </div>
      
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-line-chart"></i> Critical Power</h3></a>
        </div>
        <div class="panel-body">
          <div id="criticalPower" style="width:100%; height:300px"></div>
        </div>
      </div>
      
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Power Distribution (histogram)</h3>
        </div>
        <div class="panel-body">
          <div id="power_distribution" style="width:100%; height:300px"></div>
        </div>
      </div>
        
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-pie-chart"></i> Power Zones</h3>
        </div>
        <div class="panel-body">
          <div class="col-md-4 col-md-offset-1">
            <table id="power_zones_table" class="table table-bordered table-striped">
              <thead>
                <th>Zone</th>
                <th>Zone range</th>
                <th>% in zone</th>
              </thead>
              <tbody>
              	<?php
                    $i = 1;
                foreach ($power_table as $key => $value) {
                    echo '<tr id="'.number_format($value, 1, '-', '').'">';
                    echo '<td>'.$i++.'</td><td>'.$key.' w</td><td>'.$value.' %</td>';
                    echo '</tr>';
                }
                ?>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="col-md-4 col-md-offset-1">
            <div id="power_pie_chart" style="width:100%; height:250px"></div>
          </div>
        </div>
      </div>
      
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-line-chart"></i> Quadrant Analysis <small>Circumferential Pedal Velocity (x-axis) vs Average Effective Pedal Force (y-axis)</small></h3>
        </div>
        <div class="panel-body">
          <div id="quadrant_analysis" style="width:100%; height:600px"></div>
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
    
    var criticalPower_options = {
      lines: { show: true, fill: true, fillColor: "rgba(11, 98, 164, 0.5)", lineWidth: 1 },
      points: { show: true },
      xaxis: {
        ticks: [2,3,5,10,30,60,120,300,600,1200,3600,7200,10800,18000],
        transform: function (v) { return Math.log(v); },
        inverseTransform: function (v) { return Math.exp(v); },
		tickFormatter: function(label, series) {
          var hours = parseInt( label / 3600 ) % 24;
          var minutes = parseInt( label / 60 ) % 60;
          var seconds = label % 60;
          var result = (hours > 0 ? hours + "h" : (minutes > 0 ? minutes + "m" : seconds + 's'));
		  return result;
		}
      },
      yaxis: {
        tickFormatter: function(label, series) {
          if(label > 0) return label.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ' w';
		  else return '';
        }
      },
      grid: {
        hoverable: true,
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        }
      }
    };
	
	var criticalPower = {
      'color': 'rgba(11, 98, 164, 1)',
      'data': [
<?php
foreach ($criticalPower as $key => $value) {
    echo '['.$key.', '.$value.'], ';
}
?>
      ]
    };
    
    var markings = [{ color: "rgba(203, 75, 75, 1)", lineWidth: 2, xaxis: { from: <?php echo $power_metrics['Normalised Power']; ?>, to: <?php echo $power_metrics['Normalised Power']; ?> } }];
	    
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
        },
        markings: markings
      },
      legend: { show: false }
    };
	var power_distribution = {
      'color': 'rgba(77, 167, 77, 0.8)',
	  bars: { show: true, zero: false, barWidth: 25, fillColor: "rgba(77, 167, 77, 0.5)", lineWidth: 1 },
      'data': [
<?php
foreach ($power_histogram as $key => $value) {
    echo '['.$key.', '.$value.'], ';
}
?>
      ]
    };
	
    var power_pie_chart_options = {
      series: {
        pie: {
          radius: 1,
          show: true,
		  label: {
            show: true,
            radius: 3/4,
			formatter: labelFormatter
          }
        }
      },
      grid: { hoverable: true },
      legend: { show: false }
    };
	
    function labelFormatter(label, series) {
      return "<div style='font-size:8pt; text-align:center; padding:2px; color:#333; border-radius: 5px; background-color: #fafafa; border: 1px solid #ddd;'><strong>" + label + "</strong><br/>" + series.data[0][1] + "%</div>";
    }
    
	var power_pie_chart = [
      {
        label: "Active Recovery",
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
    
    $("<div id='tooltip_bg'></div>").css({
       position: "absolute",
       display: "none",
       "text-align": "center",
       "-moz-border-radius": "5px",
       "-webkit-border-radius": "5px",
       "border-radius": "5px",
       "border": "2px solid #fff",
       padding: "3px 7px",
       "font-size": "12px",
       "color": "#fff",
       "background-color": "#fff"
     }).appendTo("body");
     
     $("<div id='tooltip'></div>").css({
       position: "absolute",
       display: "none",
       "text-align": "center",
       "-moz-border-radius": "5px",
       "-webkit-border-radius": "5px",
       "border-radius": "5px",
       "border": "2px solid",
       padding: "3px 7px",
       "font-size": "12px",
       "color": "#555"
     }).appendTo("body");
     
     $("#criticalPower").bind("plothover", function (event, pos, item) {
       if (item) {
         var x = item.datapoint[0].toFixed(2),
         y = item.datapoint[1].toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                	
      var currentColor = item.series.color;
      var lastComma = currentColor.lastIndexOf(',');
      var newColor = currentColor.slice(0, lastComma + 1) + "0.1)";
      
      $("#tooltip").html('<strong>' + item.series.xaxis.ticks[item.dataIndex].label + '</strong><br>' + y + ' w')
        .css({top: item.pageY-45, left: item.pageX+5, "border-color": item.series.color, "background-color": newColor })
        .fadeIn(200);
      $("#tooltip_bg").html('<strong>' + item.series.xaxis.ticks[item.dataIndex].label + '</strong><br>' + y + ' w')
        .css({top: item.pageY-45, left: item.pageX+5 })
        .fadeIn(200);
      } else {
        $("#tooltip").hide();
        $("#tooltip_bg").hide();
      }
	});
    
    $.plot('#criticalPower', [criticalPower], criticalPower_options);
    
    var plot_pd = $.plot('#power_distribution', [power_distribution], power_distribution_options);
    var o = plot_pd.pointOffset({ x: <?php echo $power_metrics['Normalised Power']; ?>, y: plot_pd.height() });
    $("#power_distribution").append("<span style='background-color: #fafafa; top: 12px; color: #333; text-align: center; font-size: 12px; border: 1px solid #ddd; border-radius: 5px; padding: 3px 7px; position: absolute; left:" + (o.left + 6) + "px'><strong>normalised power</strong><br><?php echo $power_metrics['Normalised Power']; ?> w</span>");
    
    $.plot('#power_pie_chart', power_pie_chart, power_pie_chart_options);
	
	
    $("#power_pie_chart").bind("plothover", function (event, pos, obj) {
      if (!obj) {
        $("#power_zones_table tr").removeClass("danger");
        return;
      }
      $("#power_zones_table tr").removeClass("danger");
      $("#" + obj.series.data[0][1].toFixed(1).toString().replace(/\./g, '-') ).addClass("danger");
    });
    
var quad = [<?php    
$plottmp = [];
foreach ($quad_plot['plot'] as $v) {
	$plottmp[] = '[' . $v[0] . ', ' . $v[1] . ']';
}
echo implode(', ', $plottmp); ?>];
    
    var lo = [<?php
unset ($plottmp);
$plottmp = [];
foreach ($quad_plot['ftp-25w'] as $v) {
    $plottmp[] = '[' . $v[0] . ', ' . $v[1] . ']';
}
echo implode(', ', $plottmp); ?>];

    var at = [<?php
unset ($plottmp);
$plottmp = [];
foreach ($quad_plot['ftp'] as $v) {
    $plottmp[] = '[' . $v[0] . ', ' . $v[1] . ']';
}
echo implode(', ', $plottmp); ?>];
    var hi = [<?php
unset ($plottmp);
$plottmp = [];
foreach ($quad_plot['ftp+25w'] as $v) {
    $plottmp[] = '[' . $v[0] . ', ' . $v[1] . ']';
}
echo implode(', ', $plottmp); ?>];
    
    var markings = [
      {
        color: "black",
        lineWidth: 1,
        xaxis: {
          from: <?php echo $quad_plot['cpv_threshold']; ?>,
          to: <?php echo $quad_plot['cpv_threshold']; ?>
        }
      },
      {
        color: "black",
        lineWidth: 1,
        yaxis: {
          from: <?php echo $quad_plot['aepf_threshold']; ?>,
          to: <?php echo $quad_plot['aepf_threshold']; ?>
        }
      }
    ];
    
    var quadrant_analysis_options = {
      xaxis: {
        label: 'circumferential pedal velocity',
        tickFormatter: function(label, series) { return label + ' m/s'; }
      },
      yaxis: {
        max: 400,
        label: 'average effective pedal force',
        tickSize: 50,
        tickFormatter: function(label, series) {
          if(label == 0) return "";
          return label + ' N';
        }
      },
      grid: {
        borderWidth: {
          top: 0,
          right: 0,
          bottom: 0,
          left: 0
        },
        markings: markings
      },
      legend: { show: false }
    };
    
    var plot_qa = $.plot($("#quadrant_analysis"), [
        {
            data : quad,
            points : { show: true, radius: 0.25, fill : true, fillColor: "#058DC7" }
        },
        {
            data : at,
            color: "blue",
            lines: { show: true, lineWidth: 0.5 }
        },
        {
            data : lo,
            color: "red",
            lines: { show: true, lineWidth: 0.5 }
        },
        {
            data : hi,
            color: "green",
            lines: { show: true, lineWidth: 0.5 }
        }
    ], quadrant_analysis_options);
    
    $("#quadrant_analysis").append("<span style='background-color: #fafafa; top: " + (plot_qa.height() / 2 - 40) + "px; color: #333; text-align: center; font-size: 12px; border: 1px solid #ddd; border-radius: 5px; padding: 3px 7px; position: absolute; left: " + (plot_qa.width() - 140) + "px'><strong>High Force / High Velocity</strong><br><?php echo $quad_plot['quad_percent']['hf_hv']; ?> %</span>");
    $("#quadrant_analysis").append("<span style='background-color: #fafafa; top: " + (plot_qa.height() / 2 - 40) + "px; color: #333; text-align: center; font-size: 12px; border: 1px solid #ddd; border-radius: 5px; padding: 3px 7px; position: absolute; left: 50px'><strong>High Force / Low Velocity</strong><br><?php echo $quad_plot['quad_percent']['hf_lv']; ?> %</span>");
    $("#quadrant_analysis").append("<span style='background-color: #fafafa; top: " + (plot_qa.height() / 2 + 15) + "px; color: #333; text-align: center; font-size: 12px; border: 1px solid #ddd; border-radius: 5px; padding: 3px 7px; position: absolute; left: 50px'><strong>Low Force / Low Velocity</strong><br><?php echo $quad_plot['quad_percent']['lf_lv']; ?> %</span>");
    $("#quadrant_analysis").append("<span style='background-color: #fafafa; top: " + (plot_qa.height() / 2 + 15) + "px; color: #333; text-align: center; font-size: 12px; border: 1px solid #ddd; border-radius: 5px; padding: 3px 7px; position: absolute; left: " + (plot_qa.width() - 140) + "px'><strong>Low Force / High Velocity</strong><br><?php echo $quad_plot['quad_percent']['lf_hv']; ?> %</span>");
  });
</script>
</body>
</html>