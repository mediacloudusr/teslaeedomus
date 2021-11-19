<?php
// PHP Script for Tesla plugin for Eedomus
// Version 1.0 - November 2021 // Initial version

$api_url = 'https://owner-api.teslamotors.com/';
$auth_url = 'https://auth.tesla.com/';

// Return cache ?
$CACHE_DURATION = 1; // minutes
$last_xml_success = loadVariable('last_xml_success');
if ((time() - $last_xml_success) / 60 < $CACHE_DURATION) {
	sdk_header('text/xml');
	$cached_xml = loadVariable('cached_xml');
	echo $cached_xml;
	die();
}

// Id of vehicle. Let's get the saved one or uses the one provided by the user, or redetect it
$id = getArg('id', true);
$id_saved = loadVariable('cached_id');
if ($id_saved != '' && $id == '(auto)') {
	$id = $id_saved;
}

$token = getArg('token', true);
$headers = array("Authorization: Bearer " . $token, "Content-Type: application/json");

if ($id == '(auto)') {  // no vehicle id provided
	// list of vehicules to get vehicule id
	$myurlget = $api_url . 'api/1/vehicles';
	$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
	$paramsvehicles = sdk_json_decode($response);

	if ($paramsvehicles['error'] != '') {
		die("Error when getting list of vehicles: [" . $paramsvehicles['error']);
	}

	$count = $paramsvehicles['count'];

	if ($count > 0) {
		// we take the the first vehicle per default
		$id = $paramsvehicles['response'][0]['id_s'];
	} else {
		die("Error when getting list of vehicles : no vehicle.");
	}
}

// get vehicule data
$myurlget = $api_url . 'api/1/vehicles/' . $id . '/vehicle_data';
$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
$params = sdk_json_decode(utf8_encode($response));
if ($params['error'] != '') {
	die("Error when getting data: [" . $params['error']);
}

// mile to km conversion for odometer and speed
$odometerkm = round($params['response']['vehicle_state']['odometer'] * 1.60934);
$speedkmh = round($params['response']['drive_state']['speed'] * 1.60934);



// Output XML
$xml_content = jsonToXML($response);
$cached_xml = str_replace('<root>', '<root><cached>0</cached><odometerkm>' . $odometerkm . '</odometerkm><speedkmh>' . $speedkmh . '</speedkmh>', $xml_content);
echo $cached_xml;
$cached_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $cached_xml);
if ($xml_content != '') { // non empty
	saveVariable('cached_xml', $cached_xml);
	saveVariable('last_xml_success', time());
	saveVariable('cached_id', $id);
}

// we sent GPS update every $CACHE_DURATION time only
$last_lat_long_time   = (loadVariable('last_lat_long_time')) ? loadVariable('last_lat_long_time') : 0;

if (time() - $last_lat_long_time > $CACHE_DURATION /*minutes*/ * 60) {

	// id of parent control, used to send GPS data
	$moduleId = getArg('eedomus_controller_module_id', true);
	$periph_list = getPeriphList();
	$parentid  = $periph_list[$moduleId]['parent_device_id'];
	if ($parentid == '') $parentid = $moduleId;

	// get GPS data
	$myurlgetgps = $api_url . 'api/1/vehicles/' . $id . '/data_request/drive_state';
	$responsegps = httpQuery($myurlgetgps, 'GET', NULL, NULL, $headers);
	$paramsgps = sdk_json_decode(utf8_encode($responsegps));
	if ($paramsgps['error'] != '') {
		die("Error when getting data: [" . $params['error']);
	}
	$lat_long = $paramsgps['response']['latitude'] . ',' . $paramsgps['response']['longitude'];
	// updating position channel
	setValue($parentid, $lat_long);
	saveVariable('last_lat_long_time', time());
}
?>