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
    
    $crank_length = 0.175;
    $ftp = 329;
    $selected_cadence = 90;
    
    $json = $pFFA->getJSON($crank_length, $ftp, ['all'], $selected_cadence);
    
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
<link rel="stylesheet" type="text/css" href="css/dc.css">
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
    <div class="col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">
            <i class="fa fa-bar-chart"></i> Quadrant Analysis
            <button id="reset-button" type="button" class="btn btn-primary btn-xs pull-right">Reset all filters</button>
          </h3>
        </div>
        <div class="panel-body">
          <div id="quadrant-analysis-scatter-chart"></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Google Map</h3>
        </div>
        <div class="panel-body">
          <div class="embed-responsive embed-responsive-4by3">
            <div id="google-map" class="embed-responsive-item"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="row">
    <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Laps</h3>
        </div>
        <div class="panel-body">
          <div id="lap-row-chart"></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Cadence histogram</h3>
        </div>
        <div class="panel-body">
          <div id="cad-bar-chart"></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Heart Rate histogram</h3>
        </div>
        <div class="panel-body">
          <div id="hr-bar-chart"></div>
        </div>
      </div>
    </div>
  </div>
  
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/d3.js"></script>
<script type="text/javascript" src="js/crossfilter.js"></script>
<script type="text/javascript" src="js/dc.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=geometry&key=AIzaSyDlPWKTvmHsZ-X6PGsBPAvo0nm1-WdwuYE"></script>
<script type="text/javascript">
$(function(){
    var ride_data = <?php echo $json; ?>;
    console.log(ride_data);
    
    var segmentOverlay;
    var mapOptions = {
            zoom: 8
        };
    var map = new google.maps.Map(document.getElementById('google-map'), mapOptions);
    
    var routeCoordinates = [];
    $.each(ride_data.data, function(k, v) {
        if(v.position_lat !== null && v.position_long !== null) {
            routeCoordinates.push(new google.maps.LatLng(v.position_lat, v.position_long));
        }
    });
    
    var routePath = new google.maps.Polyline({
        path: routeCoordinates,
        geodesic: true,
        strokeColor: '#FF0000',
        strokeOpacity: 0.8,
        strokeWeight: 3
    });
    routePath.setMap(map);
    
    var bounds = new google.maps.LatLngBounds();
    for (var i = 0; i < routeCoordinates.length; i++) {
        bounds.extend(routeCoordinates[i]);
    }
    map.fitBounds(bounds);
    
    google.maps.event.addDomListener(window, "resize", function() {
        var center = map.getCenter();
        google.maps.event.trigger(map, "resize");
        map.setCenter(center); 
    });

    function updateOverlay(data) {
        if(typeof segmentOverlay !== 'undefined') {
            segmentOverlay.setMap(null);
        }
        var segmentCoords = [];
        $.each(data, function(k, v) {
            if(v.position_lat !== null && v.position_long !== null) {
                segmentCoords.push(new google.maps.LatLng(v.position_lat, v.position_long));
            }
        });
            
        segmentOverlay = new google.maps.Polyline({
            path: segmentCoords,
            geodesic: true,
            strokeColor: '#0000FF',
            strokeOpacity: 0.8,
            strokeWeight: 3
        });
        segmentOverlay.setMap(map);
        
        var bounds = new google.maps.LatLngBounds();
        for (var i = 0; i < segmentCoords.length; i++) {
            bounds.extend(segmentCoords[i]);
        }
        
        map.fitBounds(bounds);
    }
    
    var qaScatterChart = dc.seriesChart("#quadrant-analysis-scatter-chart"),
        lapRowChart = dc.rowChart("#lap-row-chart"),
        cadBarChart  = dc.barChart("#cad-bar-chart"),
        hrBarChart  = dc.barChart("#hr-bar-chart");
    
    var ndx,
        tsDim, latlngDim, qaDim, lapDim, cadDim, hrDim,
                          qaGrp, lapGrp, cadGrp, hrGrp;
    
    ndx = crossfilter(ride_data.data);
    
    tsDim = ndx.dimension(function(d) {return d.timestamp;});
    
    latlngDim = ndx.dimension(function(d) {return [d.position_lat, d.position_long];});
    
    qaDim = ndx.dimension(function(d) {return [d.cpv, d.aepf, d.lap];});
    qaGrp = qaDim.group().reduceSum(function(d) { return d.lap; });
    
    lapDim = ndx.dimension(function(d) {return d.lap;});
    lapGrp = lapDim.group();
    
    cadDim = ndx.dimension(function(d) {return d.cadence;});
    cadGrp = cadDim.group();
    
    hrDim = ndx.dimension(function(d) {return d.heart_rate;});
    hrGrp = hrDim.group();
    
    // Quadrant Analysis chart
    var subChart = function(c) {
        return dc.scatterPlot(c)
            .symbolSize(5)
            .highlightedSize(8)
    };
    
    qaScatterChart
        .width(550)
        .height(388)
        .chart(subChart)
        .x(d3.scale.linear().domain([0,2.5]))
        .brushOn(false)
        .yAxisLabel("Average Effective Pedal Force (N)")
        .xAxisLabel("Circumferential Pedal Velocity (m/s)")
        .elasticY(true)
        .dimension(qaDim)
        .group(qaGrp)
        .seriesAccessor(function(d) {return "Lap: " + d.key[2];})
        .keyAccessor(function(d) {return d.key[0];})
        .valueAccessor(function(d) {return d.key[1];})
        .legend(dc.legend().x(450).y(50).itemHeight(13).gap(5).horizontal(1).legendWidth(70).itemWidth(70));
    qaScatterChart.margins().left += 20;
    qaScatterChart.margins().bottom += 10;
    
    var hght = (lapGrp.size() * 40) > 76 ? lapGrp.size() * 40 : 76;
    // Lap chart
    lapRowChart
        .width(375).height(hght)
        .dimension(lapDim)
        .group(lapGrp)
        .elasticX(true)
        .gap(2)
        .label(function(d) {
            var hours = parseInt(d.value / 3600) % 24;
            var minutes = parseInt(d.value / 60 ) % 60;
            var seconds = d.value % 60;
            return 'Lap ' + d.key + ' (' + ((hours > 0 ? hours + 'h ' : '') + (minutes < 10 ? "0" + minutes : minutes) + "m " + (seconds  < 10 ? "0" + seconds : seconds) + 's)');
        });
    
    // Cadence chart
    cadBarChart
        .width(375).height(150)
        .dimension(cadDim)
        .group(cadGrp)
        .x(d3.scale.linear().domain([40,cadDim.top(1)[0].cadence]))
        .elasticY(true);
    cadBarChart.margins().left = 45;
    
    cadBarChart.yAxis()
        .tickFormat(function(d) {
            var hours = parseInt(d / 3600) % 24;
            var minutes = parseInt(d / 60 ) % 60;
            var seconds = d % 60;
                return (hours > 0 ? hours + ':' : '') + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds  < 10 ? "0" + seconds : seconds);
        })
        .ticks(4);
    
    // HR chart
    hrBarChart
        .width(375).height(150)
        .dimension(hrDim)
        .group(hrGrp)
        .x(d3.scale.linear().domain([hrDim.bottom(1)[0].heart_rate,hrDim.top(1)[0].heart_rate]))
        .elasticY(true);
    hrBarChart.margins().left = 45;
    
    hrBarChart.yAxis()
        .tickFormat(function(d) {
            var hours = parseInt(d / 3600) % 24;
            var minutes = parseInt(d / 60 ) % 60;
            var seconds = d % 60;
            return (hours > 0 ? hours + ':' : '') + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds  < 10 ? "0" + seconds : seconds);
        })
        .ticks(4);
    
    dc.renderAll();
    
    lapRowChart.on('filtered', function(chart, filter){
        updateOverlay(tsDim.bottom(Infinity));
    });
    
    qaScatterChart.on('renderlet', function(chart) {
        var horizontal_line = [{x: chart.x().range()[0], y: chart.y()(ride_data.aepf_threshold)}, 
                               {x: chart.x().range()[1], y: chart.y()(ride_data.aepf_threshold)}];
        var vertical_line = [{x: chart.x()(ride_data.cpv_threshold), y: chart.y().range()[0]}, 
                             {x: chart.x()(ride_data.cpv_threshold), y: chart.y().range()[1]}];
        var line = d3.svg.line()
            .x(function(d) { return d.x; })
            .y(function(d) { return d.y; });
        //    .interpolate('linear');
        
        var path = chart.select('g.chart-body').selectAll('path.aepf_threshold').data([horizontal_line]);
        path.enter().append('path').attr('class', 'aepf_threshold').attr('stroke', 'black');
        path.attr('d', line);
        
        var path2 = chart.select('g.chart-body').selectAll('path.cpv_threshold').data([vertical_line]);
        path2.enter().append('path').attr('class', 'cpv_threshold').attr('stroke', 'black');
        path2.attr('d', line);
    });
    
    $('#reset-button').on('click', function(e) {
        e.preventDefault(); // preventing default click action
        dc.filterAll();
        dc.redrawAll();
        if(typeof segmentOverlay !== 'undefined') {
            segmentOverlay.setMap(null);
        }
    });
});
</script>
</body>
</html>