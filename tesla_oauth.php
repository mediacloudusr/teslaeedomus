<?php
// PHP Script for Tesla plugin for Eedomus
// Version 1.2 - November 2021

$api_url = 'https://owner-api.teslamotors.com/';

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

	// this API call should not awake the car
	$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
	$paramsvehicles = sdk_json_decode($response);

	if ($paramsvehicles['error'] != '') {
		die("Error when getting list of vehicles: " . $paramsvehicles['error']);
	}

	$count = $paramsvehicles['count'];
	if ($count > 0) {
		// we take the the first vehicle per default
		$id = $paramsvehicles['response'][0]['id_s'];
		$vehiclestate = $paramsvehicles['response'][0];
	} else {
		die("Error when getting list of vehicles : no vehicle.");
	}
} else // vehicle id provided
{
	// list of vehicules to get vehicule id
	$myurlget = $api_url . 'api/1/vehicles/' . $id;

	// this API call should not awake the car
	$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
	$paramsvehicles = sdk_json_decode($response);

	if ($paramsvehicles['error'] != '') {
		die("Error when getting vehicle state: " . $paramsvehicles['error']);
	}
	$vehiclestate = $paramsvehicles['response'];
}

$vehiclestatestate = $vehiclestate['state'];

// Return cache ?
$CACHE_DURATION = 15; // minutes
$last_xml_success = loadVariable('last_xml_success');
$cached_vehiclestatestate = loadVariable('cached_vehiclestatestate');

if (($cached_vehiclestatestate == $vehiclestatestate) && ((time() - $last_xml_success) / 60 < $CACHE_DURATION)) { // we send back the cached response except if the state changed
	sdk_header('text/xml');
	$cached_xml = loadVariable('cached_xml');
	echo $cached_xml;
	die();
}

sdk_header('text/xml');
$cached_xml = '<root>
		<cached>0</cached>
		<vehicle_state>' . $vehiclestatestate . '</vehicle_state>';

if ($vehiclestatestate == 'online') {
	// vehicle is online, let's get the data. This call can prevent the car to go to sleep !
	// Cache is set for 15 minutes so the car has time to go to sleep.
	// get vehicule data
	$myurlget = $api_url . 'api/1/vehicles/' . $id . '/vehicle_data';
	$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
	$params = sdk_json_decode(utf8_encode($response));
	if ($params['error'] != '') {
		die("Error when getting data: " . $params['error']);
	}

	// Output XML
	$paramResponse = $params['response'];

	// Mile to km conversion for odometer and speed
	$odometerkm = round($paramResponse['vehicle_state']['odometer'] * 1.60934);
	$speedkmh = round($paramResponse['drive_state']['speed'] * 1.60934);
	$batteryrange = round($paramResponse['charge_state']['battery_range'] * 1.60934);
	$estbatteryrange = round($paramResponse['charge_state']['est_battery_range'] * 1.60934);

	$chargeportdooropen = $paramResponse['charge_state']['charge_port_door_open'] ? "true" : "false";
	$locked = $paramResponse['vehicle_state']['locked'] ? "true" : "false";

	$cached_xml .= '<battery_level>' . $paramResponse['charge_state']['battery_level'] . '</battery_level>
		<charge_limit_soc>' . $paramResponse['charge_state']['charge_limit_soc'] . '</charge_limit_soc>
		<charge_energy_added>' . $paramResponse['charge_state']['charge_energy_added'] . '</charge_energy_added>
		<charge_port_door_open>' . $chargeportdooropen . '</charge_port_door_open>
		<charging_state>' . $paramResponse['charge_state']['charging_state'] . '</charging_state>
		<minutes_to_full_charge>' . $paramResponse['charge_state']['minutes_to_full_charge'] . '</minutes_to_full_charge>
		<charge_rate>' . $paramResponse['charge_state']['charge_rate'] . '</charge_rate>
		<charger_voltage>' . $paramResponse['charge_state']['charger_voltage'] . '</charger_voltage>
		<charger_power>' . $paramResponse['charge_state']['charger_power'] . '</charger_power>
		<battery_range>' . $batteryrange . '</battery_range>
		<est_battery_range>' . $estbatteryrange . '</est_battery_range>
		<outside_temp>' . $paramResponse['climate_state']['outside_temp'] . '</outside_temp>
		<inside_temp>' . $paramResponse['climate_state']['inside_temp'] . '</inside_temp>
		<odometerkm>' . $odometerkm . '</odometerkm>
		<locked>' . $locked . '</locked>
		<vehicle_name>' . $paramResponse['vehicle_state']['vehicle_name'] . '</vehicle_name>
		<shift_state>' . $paramResponse['drive_state']['shift_state'] . '</shift_state>
		<speedkmh>' . $speedkmh . '</speedkmh>';


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
			//die("Error when getting gps data: " . $paramsgps['error']);
		} else { // let's send the gps coordinated to the position periph id
			$lat_long = $paramsgps['response']['latitude'] . ',' . $paramsgps['response']['longitude'];
			// updating position channel
			setValue($parentid, $lat_long);
			saveVariable('last_lat_long_time', time());
		}
	}
}
$cached_xml .= '</root>';

echo $cached_xml;
$cached_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $cached_xml);

saveVariable('cached_xml', $cached_xml);
saveVariable('last_xml_success', time());
saveVariable('cached_id', $id);
saveVariable('cached_vehiclestatestate', $vehiclestatestate);
