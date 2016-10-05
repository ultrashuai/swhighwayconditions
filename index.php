<?PHP

	$availStates = array('ca'=>'California', 'nv'=>'Nevada', 'ut'=>'Utah', 'az'=>'Arizona', 'nm'=>'New Mexico');
	
	$caURL = 'http://ca511.dot.ca.gov/data/chp.kml';
	$caURL2 = 'http://ca511.dot.ca.gov/data/lcs.kml';
	$nvURL = 'http://nvroads.com/services/MapServiceProxy.asmx/GetIncidentsXML?includeIncidents=true&includeCongestion=true&includeConstruction=true&includeDetour=true&includeWeather=false&includeSpecialEvents=true&includeRestrictions=true';
	$azURL = 'http://www.az511.com/traffic/';
	$utURL = 'http://udottraffic.utah.gov/KmlFile.aspx?kmlFileType=Event';
	//$utEvents = array('Event'=>'i', 'Project'=>'c', 'LaneClosure'=>'c');
	$coURL = '';
	$nmURL = 'http://servicev3.nmroads.com/RealMapWAR/GetEventsJSON?eventType=';
	$nmEvents = array(5=>'i', 6=>'i', 7=>'i', 8=>'c', 9=>'c', 13=>'i', 16=>'i', 17=>'i', 19=>'c', 20=>'i');
	
	// california incidents
	$f = file_get_contents($caURL);
	$xml = simplexml_load_string($f);
	$caIncidents = array();
	foreach ($xml->Document->Placemark as $i) {
		$arr = explode(',', (string)$i->Point->coordinates);
		if (!$arr[0] || !$arr[1]) continue;
		$str = '{"type":"';
		$type = ((string)$i->styleUrl);
		
		if (stripos($type, '#incident') !== false || stripos($type, '#emergency') !== false) $str .= 'i';
		else $str .= 'c';
		
		preg_match("/<br> ([A-Za-z\d\- \,_]+) <br>/", ((string)$i->description), $short);
		$str .= '", "short":"'.preg_replace("/[\x1C-\x1F]/", '', $short[1]).'", "long":"'.preg_replace("/[\x1C-\x1F]/", '', str_replace(array("\r", "\n", '"'), array('', '', '&quot;'), ((string)$i->description))).'", "lat":'.$arr[1].', "lng":'.$arr[0].'}';
		$caIncidents[] = $str;
	}
	
	// california construction
	$f = file_get_contents($caURL2);
	$xml = simplexml_load_string($f);
	$caConstruction = array();
	foreach ($xml->Document->Placemark as $i) {
		$arr = explode(',', (string)$i->Point->coordinates);
		if (!$arr[0] || !$arr[1]) continue;
		$str = '{"type":"';
		$type = ((string)$i->styleUrl);
		
		if (stripos($type, '#lcs') !== false || stripos($type, '#lcs-full') !== false || stripos($type, '#lcs-hsr') !== false) $str .= 'c';
		else continue;
		
		$str .= '", "short":"'.((string)$i->name).'", "long":"'.str_replace(array("\r", "\n"), '', str_replace('"', '&quot;', ((string)$i->description))).'", "lat":'.$arr[1].', "lng":'.$arr[0].'}';
		$caConstruction[] = $str;
	}
	
	// nevada
	$f = str_replace(array('<string xmlns="http://tempuri.org/">', '</string>', '&lt;', '&gt;'), array('', '', '<', '>'), file_get_contents($nvURL));
	//echo $f;
	//exit;
	$xml = simplexml_load_string($f);
	$nvIncidents = array();
	$nvConstruction = array();
	foreach ($xml->incident as $i) {
		$str = '{"type":"';
		$type = ((string)$i->attributes()->IconURL);
		$incident = '';
		if (stripos($type, 'incident') !== false) $incident = 'i';
		elseif (stripos($type, 'construction') !== false) $incident = 'c';
		$str .= $incident;
		
		$str .= '", "short":"'.((string)$i->attributes()->LastUpdate).'", "long":"'.((string)$i->attributes()->Description).'", "lat":'.((string)$i->lat).', "lng":'.((string)$i->lon).'}';
		if ($incident == 'i') $nvIncidents[] = $str;
		elseif ($incident == 'c') $nvConstruction[] = $str;
	}
	
	// arizona
	$f = file_get_contents($azURL);
	preg_match_all("/var attr = [\r\n]+(\{[^\}]+\});/", $f, $azMatches);
	preg_match_all("/var iHtml = \"([^\"]+)\";/", $f, $azDetails);
	
	if (count($azMatches) != count($azDetails)) {
		print_r($azMatches);
		echo "<br/><br/><br/><br/>\n";
		print_r($azDetails);
		die("AZ counts do not match");
	}
	
	$azIncidents = array();
	$azConstruction = array();
	foreach ($azMatches[1] as $i=>$incident) {
		$ii = json_decode($incident);
		$type = '';
		if (stripos($ii->icon, 'dia-') !== false || stripos($ii->icon, 'hex-') !== false) $type = 'c';
		elseif (stripos($ii->icon, 'tri-') !== false) $type = 'i';
		
		if (!$type) continue;
		
		$str = '{"type":"'.$type.'", "short":"'.$ii->mapTip.'", "long":"'.$azDetails[1][$i].'", "lat":'.$ii->latit.', "lng":'.$ii->longit.'}';
		if ($type == 'i') $azIncidents[] = $str;
		elseif ($type == 'c') $azConstruction[] = $str;
	}
	
	// new mexico
	$nmIncidents = array();
	$nmConstruction = array();
	foreach ($nmEvents as $event => $type) {
		$f = file_get_contents($nmURL.$event);
		$ii = json_decode($f);
		foreach ($ii->events as $i=>$eventDetail) {
			$degreesPrRadians = 57.295779513082320876798154814105;
			$wgs84EarthRadius = 6378137;
			$longRadians = $eventDetail->longitude / $wgs84EarthRadius;
			$longDegrees = $longRadians * $degreesPrRadians;
			$rotations = floor(($longDegrees + 180) / 360);
			$longitude = $longDegrees - ($rotations * 360);
			$latitude = (pi() / 2) - (2 * atan(exp(-1.0 * $eventDetail->latitude / $wgs84EarthRadius)));
			$latitude = $latitude * $degreesPrRadians;
			$title = preg_replace('/[\x00-\x1F\x7F]/', '', $eventDetail->title);
			$str = '{"type":"'.$type.'", "short":"'.$title.' ('.$eventDetail->updateDate.')", "long":"'.$title.' ('.$eventDetail->updateDate.')<br/>'.preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(array("\r", "\n", '"'), array('', '<br/>', '&quot;'), $eventDetail->description)).'", "lat":'.$latitude.', "lng":'.$longitude.'}';
			//echo $str;
			//exit;
			if ($type == 'i') $nmIncidents[] = $str;
			elseif ($type == 'c') $nmConstruction[] = $str;
		}
	}
	
	// utah
	$utIncidents = array();
	$utConstruction = array();
	//foreach ($utEvents as $event => $type) {
		$f = file_get_contents($utURL);//.$event);
		$ii = simplexml_load_string($f);
		foreach ($ii->Document->Placemark as $i=>$eventDetail) {
			$type = (((string)$eventDetail->ExtendedData->SchemaData->SimpleData[0]) == 'Construction') ? 'c' : 'i';
			$latlng = explode(',', (string)$eventDetail->Point->coordinates);
			$str = '{"type":"'.$type.'", "short":"'.((string)$eventDetail->name).', '.((string)$eventDetail->ExtendedData->SchemaData->SimpleData[1]).')", "long":"'.str_replace(array("\r", "\n"), array('', ' '), (string)$eventDetail->ExtendedData->SchemaData->SimpleData[18]).'", "lat":'.$latlng[1].', "lng":'.$latlng[0].'}';
			if ($type == 'i') $utIncidents[] = $str;
			elseif ($type == 'c') $utConstruction[] = $str;
		}
	//}

?>
<!DOCTYPE html>
<html >
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Western Roads</title>
<style type="text/css">

	body {
		margin:0;
		font-size:0.8em;
	}
	
	#menu {
		float:left;
		width:120px;
		background-color:#F90;
		padding:5px;
	}
	#menu ul {
		margin:0;
		padding:0;
	}
	#menu li {
		list-style-type:none;
		margin:0;
		padding:3px 0;
		font-weight:bold;
	}
	#menu ul ul {
		margin-left:5px;
		display:none;
	}
	#menu li li {
		font-weight:normal;
	}
	#menu li span {
		color:#C00;
		cursor:pointer;
	}
	
	#directions {
		clear:both;
		border:solid 5px #CCC;
		height:200px;
	}
		#directionsPanel {
			padding:5px;
			float:right;
			width:500px;
			height:190px;
			overflow:auto;
			border-left:solid 1px #333;
		}
		
		#directionsForm {
			width:450px;
			padding:20px 10px;
			text-align:center;
		}
		#directionsIcon {
			float:left;
			width:24px;
			height:24px;
			background:url("//maps.gstatic.com/tactile/omnibox/directions-1x-20150407.png") no-repeat;
			border:solid 1px #000;
		}
		#addStop {
			color:blue;
			cursor:pointer;
			font-size:1.1em;
		}
		#directionsSubmit {
			text-align:right;
		}

</style>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false&key=AIzaSyC-fkr3STMSnoe_wlzur71trPmdWn-Vm0k"></script>

<script type="text/javascript">

	var map;
	var activeIW;
	var ds, dr;
	var trafficLayer;
	
	$(function () {
		var height = $(window).height();
		var zoomLevel = (height < 500) ? 4 : 5;
		
		// max out map
		$('#map').css('width',($(document).width() - 157) + 'px').css('height', (height - 210) + 'px');
		map = new google.maps.Map(document.getElementById("map"), {center:new google.maps.LatLng(40.2,-98.53),zoom:zoomLevel,mapTypeId:google.maps.MapTypeId.ROADMAP});
		ds = new google.maps.DirectionsService();
		dr = new google.maps.DirectionsRenderer({map:map, panel:document.getElementById('directionsPanel')});
		$('#menu').css('height', (height - 220) + 'px');
		
		loadPoints(allPoints.c.ca, 'ca');
		loadPoints(allPoints.c.nv, 'nv');
		loadPoints(allPoints.c.az, 'az');
		loadPoints(allPoints.c.nm, 'nm');
		loadPoints(allPoints.c.ut, 'ut');
		
		$('#menu li span').on('click', function () {
			if ($(this).parent().children('ul').is(':visible'))
				$(this).parent().children('ul').slideUp();
			else
				$(this).parent().children('ul').slideDown();
		});
		$('#menu > ul > li > input[type="checkbox"]').on('click', function () {
			$(this).parent().children('ul').children('li').children('input[type="checkbox"]').attr('checked', $(this).is(':checked'));
			if ($(this).is(':checked')) {
				if ($(this).val() == 'i') {
					// already loaded
					if (markers.i.ca.length) {
						showPoints('i', false);
						return true;
					}
					
					loadPoints(allPoints.i.ca, 'ca');
					loadPoints(allPoints.i.nv, 'nv');
					loadPoints(allPoints.i.az, 'az');
					loadPoints(allPoints.i.nm, 'nm');
					loadPoints(allPoints.i.ut, 'ut');
				}
				else
					showPoints('c', false);
			}
			else {
				hidePoints($(this).val(), false);
			}
		});
		$('#menu li li input[type="checkbox"]').on('click', function () {
			var type = $(this).parent().parent().parent().children('input').val();
			var state = $(this).val();
			if ($(this).is(':checked')) {
				if (!markers[type][state].length)
					loadPoints(allPoints[type][state], state);
				showPoints(type, state);
			}
			else
				hidePoints(type, state);
		});
		
		$('#addStop').on('click', function () {
			var stops = $('input[name="txtStop"]').size()
			if (stops == 8) return false;
			$('#stops').append('<p><b>' + (stops + 1) + '</b> &nbsp;<input type="text" maxlength="255" name="txtStop" /></p>');
		});
		
		trafficLayer = new google.maps.TrafficLayer();
	});
	
	var markers = {"c":{"ca":[], "nv":[], "az":[], "ut":[], "nm":[], "co":[]}, "i":{"ca":[], "nv":[], "az":[], "ut":[], "nm":[], "co":[]}};
	var infoWindows = {};
	function loadPoints (points, state) {
		if (!points.length) return false;
		
		for (var i = 0; i < points.length; i++) {
			var img = (points[i].type == 'c') ? new google.maps.MarkerImage('images/construction.gif') : new google.maps.MarkerImage('images/incident-32x32.png');
			var marker = new google.maps.Marker({id:(points[i].type + '-' + state + '-' + i), map:map, title:points[i].short, icon:img, position:new google.maps.LatLng(points[i].lat, points[i].lng)});
			infoWindows[marker.id] = new google.maps.InfoWindow({content:points[i].long.replace(/&quot;/g, '"')});
			google.maps.event.addListener(marker, 'click', function () {
				if (activeIW) activeIW.close();
				infoWindows[this.id].open(map, this);
				activeIW = infoWindows[this.id];
			});
			markers[points[i].type][state].push(marker);
		}
	}
	
	function showPoints (type, state) {
		for (var i in markers[type]) {
			if (state && i != state) continue;
			for (var m = 0; m < markers[type][i].length; m++) {
				markers[type][i][m].setMap(map);
			}
			if (state) break;
		}
	}
	
	function hidePoints (type, state) {
		for (var i in markers[type]) {
			if (state && i != state) continue;
			for (var m = 0; m < markers[type][i].length; m++) {
				markers[type][i][m].setMap(null);
			}
			if (state) break;
		}
	}
	
	function submitDirections () {
		var wps = [];
		$('input[name="txtStop"]').each(function (i, ele) {
			if (!$(this).val()) return false;
			wps.push({location:$(this).val(), stopover:true});
		});
		
		var request = {
			origin: $('#txtFrom').val(),
			destination: $('#txtTo').val(),
			waypoints: wps,
			travelMode: google.maps.TravelMode.DRIVING
		};
		ds.route(request, function(response, status) {
			if (status == google.maps.DirectionsStatus.OK) {
				dr.setDirections(response);
			}
		});
	}
	
	function toggleTraffic (isChecked) {
		if (isChecked) trafficLayer.setMap(map);
		else trafficLayer.setMap(null);
	}
	
	var allPoints = {'i':{}, 'c':{}};
	allPoints.i.ca = [
<?PHP

	echo implode(",\r\n", $caIncidents)."\r\n";

?>
	];
	
	allPoints.c.ca = [
<?PHP

	echo implode(",\r\n", $caConstruction)."\r\n";

?>
	];
	
	allPoints.i.nv = [
<?PHP

	echo implode(",\r\n", $nvIncidents)."\r\n";

?>
	];
	
	allPoints.c.nv = [
<?PHP

	echo implode(",\r\n", $nvConstruction)."\r\n";

?>
	];
	
	allPoints.i.az = [
<?PHP

	echo implode(",\r\n", $azIncidents)."\r\n";

?>
	];
	
	allPoints.c.az = [
<?PHP

	echo implode(",\r\n", $azConstruction)."\r\n";

?>
	];
	
	allPoints.i.ut = [
<?PHP

	echo implode(",\r\n", $utIncidents)."\r\n";

?>
	];
	
	allPoints.c.ut = [
<?PHP

	echo implode(",\r\n", $utConstruction)."\r\n";

?>
	];
	
	allPoints.i.nm = [
<?PHP

	echo implode(",\r\n", $nmIncidents)."\r\n";

?>
	];
	
	allPoints.c.nm = [
<?PHP

	echo implode(",\r\n", $nmConstruction)."\r\n";

?>
	];
	
	allPoints.i.co = [
	
	];
	
	allPoints.c.co = [
	
	];

</script>
</head>

<body>
<div id="menu">

	<ul>
		<li><input type="checkbox" value="c" checked /> Construction <span>+</span>
		<ul>
<?PHP

	foreach ($availStates as $code => $name) {
		echo "			<li><input type=\"checkbox\" value=\"$code\" checked /> $name</li>\n";
	}

?>
		</ul></li>
		<li><input type="checkbox" value="i" /> Incidents <span>+</span>
		<ul>
<?PHP

	ksort($availStates);
	foreach ($availStates as $code => $name) {
		echo "			<li><input type=\"checkbox\" value=\"$code\" /> $name</li>\n";
	}

?>
		</ul></li>
        <li><input type="checkbox" id="trafficLayerToggle" value="1" onclick="toggleTraffic(this.checked);" /> Traffic</li>
	</ul>

</div>
<div id="map"></div>
<div id="directions">
	<div id="directionsPanel"></div>
	<div id="directionsForm">
		<div id="directionsIcon"></div>
		<strong>
			From: <input type="text" id="txtFrom" maxlength="255" />
			To: <input type="text" id="txtTo" maxlength="255" /><br/>&nbsp;<br/>
			<span id="addStop">+ Add Stop</span>
		</strong>
		<div id="stops"></div>
		<div id="directionsSubmit">
			<button onclick="submitDirections();" title="Click to calculate route">Submit</button>
		</div>
	</div>
</div>
</body>
</html>