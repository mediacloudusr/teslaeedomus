<?php
// PHP Script for Tesla plugin for Eedomus
// Version 1.3 - November 2021

/////////////////////////////////////////////////////////////////////////
// Constants
/////////////////////////////////////////////////////////////////////////
$api_url = 'https://owner-api.teslamotors.com/';
$CACHE_DURATION = 15; // minutes. Do not set to lower than 15 min if you want your car to go asleep

$token = getArg('token', true);
$token = str_replace(' ', '', $token);
$headers = array("Authorization: Bearer " . $token, "Content-Type: application/json");
$action = getArg('action', false, 'get_data');
$id = getArg('id', true);
$moduleId = getArg('eedomus_controller_module_id', true);
$usecache = true;

/////////////////////////////////////////////////////////////////////////
// Functions
/////////////////////////////////////////////////////////////////////////

function sdk_get_car_state($api_url, $id, $headers)
{
	$myurlget = $api_url . 'api/1/vehicles';

	if ($id == '(auto)') {  // no vehicle id provided
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
		} else {
			die("Error when getting list of vehicles : no vehicle.");
		}
		return $paramsvehicles['response'][0];
	} else {
		$myurlget .= '/' . $id;

		// this API call should not awake the car
		$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);
		$paramsvehicles = sdk_json_decode($response);

		if ($paramsvehicles['error'] != '') {
			die("Error when getting vehicle state: " . $paramsvehicles['error']);
		}
		return $paramsvehicles['response'];
	}
}

function sdk_get_gps_state($api_url, $id, $headers)
{
	// get GPS data
	$myurlgetgps = $api_url . 'api/1/vehicles/' . $id . '/data_request/drive_state';
	$responsegps = httpQuery($myurlgetgps, 'GET', NULL, NULL, $headers);
	$paramsgps = sdk_json_decode(utf8_encode($responsegps));
	if ($paramsgps['error'] != '') {
		die("Error when getting gps data: " . $paramsgps['error']);
	}
	return $paramsgps['response'];
}

function sdk_wake_up_and_wait($api_url, $id, $headers)
{
	// wake_up the car
	$myurlpost = $api_url . 'api/1/vehicles/' . $id . '/wake_up';
	$response = httpQuery($myurlpost, 'POST', NULL, NULL, $headers);
	$paramsWakeup = sdk_json_decode($response);

	if ($paramsWakeup['error'] != '') {
		die("Error when doing wakeup" . $paramsWakeup['error']);
	}

	// let's wait for the state to be 'online'
	for ($i = 1; $i <= 10; $i++) {
		$state = sdk_get_car_state($api_url, $id, $headers);
		if ($state['state'] == 'online') break;
		usleep(2000000);
	}
	
	// let's not use the cache for data or GPS the next time
	saveVariable('last_xml_success', '');
	saveVariable('last_gps_success', '');

	die("wake_up done.");
}


function sdk_action_on_car($api_url, $id, $headers, $action)
{
	// actions on the car
	$state = sdk_get_car_state($api_url, $id, $headers);
	if ($state['state'] != 'online') {
		sdk_wake_up_and_wait($api_url, $id, $headers);
	}

	// commands to the car
	$myurlpost = $api_url . 'api/1/vehicles/' . $id . '/command/' . $action;
	$response = httpQuery($myurlpost, 'POST', NULL, NULL, $headers);
	$paramsAction = sdk_json_decode($response);

	if ($paramsAction['response']['result'] != 'true') {
		die("Error when doing action $action " . $paramsAction['error']);
	}

	die("Action $action done.");
}

function sdk_get_id_of_parent_control($moduleId)
{
	// id of parent control, used to send GPS data
	$periph_list = getPeriphList();
	$parentid  = $periph_list[$moduleId]['parent_device_id'];
	if ($parentid == '') $parentid = $moduleId;
	return $parentid;
}


/////////////////////////////////////////////////////////////////////////
// Main code
/////////////////////////////////////////////////////////////////////////

// Id of vehicle. Let's get the saved one or uses the one provided by the user, or redetect it
$id_saved = loadVariable('cached_id');
if ($id_saved != '' && $id == '(auto)') {
	$id = $id_saved;
}

$vehiclestate = sdk_get_car_state($api_url, $id, $headers);

if ($action == 'wake_up') {
	sdk_wake_up_and_wait($api_url, $id, $headers);
} else if (
	$action == 'flash_lights' ||
	$action ==  'auto_conditioning_start' ||
	$action == 'auto_conditioning_stop' ||
	$action ==  'honk_horn' ||
	$action == 'door_lock' ||
	$action == 'door_unlock' ||
	$action == 'charge_start' ||
	$action == 'charge_stop' ||
	$action == 'charge_port_door_open' ||
	$action == 'charge_port_door_close'
) {
	sdk_action_on_car($api_url, $id, $headers, $action);
}

$vehiclestatestate = $vehiclestate['state'];

if ($action == 'get_state_data') {
	sdk_header('text/xml');
	$xml = '<root>
			<vehicle_state>' . $vehiclestatestate . '</vehicle_state>
			</root>';
	echo $xml;
	die();
}


if ($action == 'get_gps_data') {

	if ($vehiclestatestate == 'online') {

		// Return cache ?
		$last_gps_success = loadVariable('last_gps_success');
		$cached_vehiclestatestate_for_gps = loadVariable('cached_vehiclestatestate_for_gps');

		// let's interrogate GPS only when needed and let the car to to sleep
		if (!empty($cached_vehiclestatestate_for_gps) && !empty($last_gps_success) && ($cached_vehiclestatestate_for_gps == $vehiclestatestate) && ((time() - $last_gps_success) / 60 < $CACHE_DURATION)) {
			sdk_header('text/xml');
			$cached_gps_xml = loadVariable('cached_gps_xml');
			echo $cached_gps_xml;
			die();
		}

		// id of parent control, used to send GPS data
		$parentid = sdk_get_id_of_parent_control($moduleId);

		// get GPS data
		$paramsgps = sdk_get_gps_state($api_url, $id, $headers);
		$latitude = $paramsgps['latitude'];
		$longitude = $paramsgps['longitude'];

		// updating position channel
		setValue($parentid, $latitude . ',' . $longitude);
		saveVariable('last_gps_success', time());
		sdk_header('text/xml');
		$gps_xml = '<root>
			<cached>0</cached>
			<coordinates>' . $latitude . ',' . $longitude . '</coordinates>
			<latitude>' . $latitude . '</latitude>
			<longitude>' . $longitude . '</longitude>
			<moduleId>' . $moduleId . '</moduleId>
			<positionmoduleid>' . $parentid . '</positionmoduleid>
			</root>';
		echo $gps_xml;
		$gps_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $gps_xml);
		saveVariable('cached_gps_xml', $gps_xml);
		saveVariable('cached_vehiclestatestate_for_gps', $vehiclestatestate);
	} else {
		echo 'Vehicle is not online';
	}
	die();
}

// Return cache ?
$cached_vehiclestatestate = loadVariable('cached_vehiclestatestate');
$last_xml_success = loadVariable('last_xml_success');

if (!empty($cached_vehiclestatestate) && !empty($last_xml_success) && ($cached_vehiclestatestate == $vehiclestatestate) && ((time() - $last_xml_success) / 60 < $CACHE_DURATION)) { // we send back the cached response except if the state changed
	sdk_header('text/xml');
	$cached_xml = loadVariable('cached_xml');
	echo $cached_xml;
	die();
}

sdk_header('text/xml');
// get vehicule data

$cached_xml = '<root>
		<cached>0</cached>
		<vehicle_state>' . $vehiclestatestate . '</vehicle_state>';

if ($vehiclestatestate == 'online') {

	// get_data	
	// vehicle is online, let's get the data. This call can prevent the car to go to sleep !
	// Cache is set for 15 minutes so the car has time to go to sleep.

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
	$isclimateon = $paramResponse['climate_state']['is_climate_on'] ? "true" : "false";
	$sentrymode = $paramResponse['vehicle_state']['sentry_mode'] ? "true" : "false";;

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
		<is_climate_on>' . $isclimateon . '</is_climate_on>
		<odometerkm>' . $odometerkm . '</odometerkm>
		<locked>' . $locked . '</locked>
		<vehicle_name>' . $paramResponse['vehicle_state']['vehicle_name'] . '</vehicle_name>
		<shift_state>' . $paramResponse['drive_state']['shift_state'] . '</shift_state>
		<sentry_mode>' . $sentrymode . '</sentry_mode>
		<speedkmh>' . $speedkmh . '</speedkmh>';
}

$cached_xml .= '</root>';

echo $cached_xml;
$cached_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $cached_xml);

saveVariable('last_xml_success', time());
saveVariable('cached_xml', $cached_xml);
saveVariable('cached_id', $id);
saveVariable('cached_vehiclestatestate', $vehiclestatestate);
