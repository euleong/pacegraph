<!DOCTYPE html>
<meta charset="utf-8">
<html>
<head>
<title>Pace Graph</title>
 	
<style>
.content {
  text-align: center;
}
.chart {
  fill: black;
  font: 10px sans-serif;
}

.runDate {

  text-anchor: end;
}

</style>

<script src="http://d3js.org/d3.v3.min.js"></script>

<?php
error_reporting(0);
define("ACCESS_TOKEN", "");
define("ATHLETE_ID", "2792949");
?>
</head>
<body>
	<div class="content"><svg class="chart"></svg></div>
	<?php
	/*
	$jsonStr = file_get_contents("https://www.strava.com/api/v3/athletes/".ATHLETE_ID."?access_token=".ACCESS_TOKEN);
	$userData = json_decode($jsonStr, true);
	$selectInfo = array('firstname', 'lastname', 'city');
	*/
	?>
	
	<?php
	
	define(HIGH_THRESHOLD, 1.1);
	define(LOW_THRESHOLD, 0.9);
	define(DEBUG, false);
	define(UPHILL, 50);
	define(DOWNHILL, -50);
	
	//$now = time();
	//?after=1378684800
	$json = file_get_contents("https://www.strava.com/api/v3/activities?per_page=200&access_token=".ACCESS_TOKEN);
	$activitiesData = json_decode($json, true);
	
	if (DEBUG)
	{
		echo '<table>';
	}

	$runsArray = array();
	$longestRun = 0;
	foreach ($activitiesData as $activity) {
		if ($activity['type'] === 'Run' && $activity['private'] === false) {
			$run = array();
			$run['name'] = $activity['name']; 
			$run['date'] = date('F j, Y', strtotime($activity['start_date']));
			$run['id'] = $activity['id'];
			if (DEBUG)
			{
				echo '<tr><td colspan="4">'.$activity['name'].'</td></tr>';				
			}

			$activityId = $activity['id'];
			$json = file_get_contents("https://www.strava.com/api/v3/activities/".$activityId."?access_token=".ACCESS_TOKEN);
			$activityDetail = json_decode($json, true);
			
			$splits = $activityDetail['splits_standard'];
			// make sure each activity has splits key
			$run['splits'] = array();
			
			foreach($splits as $split)
			{
				if (DEBUG)
				{
					echo '<tr>';					
				}

				$seconds = $split['moving_time'];
				if (!isset($minPace) || (isset($minPace) && $seconds < $minPace))
				{
					$minPace = $seconds;
				}
				
				if (!isset($maxPace) || (isset($maxPace) && $seconds > $maxPace))
				{
					$maxPace = $seconds;
				}
				
				$mile = metersToMiles($split['distance']);
				if (DEBUG)
				{
					echo '<td>'.$split['split'].'</td><td>'.gmdate ('i:s', $seconds).'</td><td>'.$seconds.'</td><td>'.number_format($mile, 2).'</td>';					
				}

				$pace = array();
				$pace['split'] = $split['split'];
				$pace['seconds'] = $seconds;
				$pace['pace'] = gmdate('i:s', $seconds);
				$pace['distance'] = number_format($mile, 2);
				// or find segments with significant elevation change?
				$pace['elevationDiff'] = metersToFeet($split['elevation_difference']);					

				if ($mile > LOW_THRESHOLD && $mile < HIGH_THRESHOLD) {
					$run['splits'][] = $pace;
				}
				
				if (DEBUG)
				{
					echo '</tr>';					
				}
						
			}
			if (count($run['splits']) > $longestRun)
			{
				$longestRun = count($run['splits']);
			}
			
			$runsArray[] = $run;			
		}
	}
	if (DEBUG)
	{
		echo '</table>';		
	}

	
	$runs_js = json_encode($runsArray);
 	
	?>
	<script type="text/javascript">
	var ACTIVITY_URL = "http://www.strava.com/activities/";
	var runData = <?php echo $runs_js ?>;
	var maxPace = <?php echo $maxPace ?>;
	var minPace = <?php echo $minPace ?>;
	var longestRun = <?php echo $longestRun ?>;
		
	var radius = d3.scale.linear()
		.domain([minPace, maxPace])
	    .range([0, 15]);
	
	var color = d3.scale.linear()
		.domain([minPace, (minPace + maxPace)/2, maxPace])
		.range(["black", "steelblue", "red"]);

	var width = longestRun*30 + 200, //500,
	 	height = runData.length*50,
	    rowHeight = 50;

	var chart = d3.select(".chart")
	    .attr("width", width)
	    .attr("height", height);

	var circle = chart.selectAll("g")
		.data(runData)
		.enter().append("g")
	    .attr("transform", function(d, i) { return "translate(0," + (i * rowHeight + 20) + ")"; });
	
	
	circle.append("text")
		.attr("x", 100)
		.attr("y", function(i) {return i*rowHeight + 10;})
		.attr("dy", ".35em")
		.attr("class", "runDate")
		.text(function(d) { return d.date; })
		.on("click", function (d) {
			window.open(ACTIVITY_URL + d.id);
		})
		.on("mouseover", function() {
			d3.select(this).style("cursor", "pointer");
		});
			
	circle.selectAll("g")
		.data(function(d) { return d.splits;}) 
		.enter().append("g")
		.attr("transform", function(d,i) {var x = i*30 + 150; return ("translate(" + x + ", 0)");})
		.on("mouseover", function(){
			d3.select(this).append("text")
				//.attr("x", function(d, i) {return i*30 + 150;})
				.attr("y", function(d, i) {return -10;})
				.attr("text-anchor", "middle")
				.style("fill", "black")
			    .text(function(d) {return d.pace; });
			})
        .on("mouseout", function(){
			d3.select(this).select("text").remove();
			})
		.append("circle")
		.attr("r", function(d, i) { return radius(d.seconds);})
	 	.style("fill", function(d, i) {return color(d.seconds);});
				
	</script>
	<?php
	function metersToMiles($meters) {
		return $meters * 0.00062137;
	}
	
	function metersToFeet($meters) {
		return $meters * 3.2808;
	}
	
	?>

</body>
</html>