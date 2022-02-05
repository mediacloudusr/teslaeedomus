<?php
# This file is part of Tesla Car Plugin for Eedomus <https://github.com/mediacloudusr/teslaeedomus>.
# It connects to the Tesla API and reports back data to Eedomus.
# Copyright (C) 2022 mediacloud (https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with This program. If not, see <http://www.gnu.org/licenses/>.
#
# v1.8.0

/////////////////////////////////////////////////////////////////////////
// Constants
/////////////////////////////////////////////////////////////////////////

$api_url = 'https://owner-api.teslamotors.com/';
$api_auth_url = 'https://auth.tesla.com/';
$code_verifier = 'MTI4NTQyMzc2NjFhNTUzMGQ4YTQ2NjkuNjQzMzM1MjEzNTc5OTMxOTI2MWE1NTMwZDhhNGEwNC4yOTA5NTA0MTIwMjM0ODEwMzE2MWE1NTMwZDhhNGI';

$access_token_duration = 60 * 8; // 8 hours

$CACHE_DURATION = 15; // minutes. Do not set to lower than 15 min if you want your car to go asleep
$CACHE_DURATION_ACTIVE = 3; // minutes when we are actively monitoring 
$WAIT_TO_SWITCH_TO_ASLEEP = 10; // minutes when we are actively monitoring 

//$token = getArg('token', true);
//$token = str_replace(' ', '', $token);
$access_token = loadVariable('access_token');
$refresh_token = loadVariable('refresh_token');
$access_token_start_time = loadVariable('access_token_start_time');

$action = getArg('action', false, 'get_data');
$nocache = getArg('nocache', false, 'false'); // used for debug
$vin = getArg('vin', true);
$moduleId = getArg('eedomus_controller_module_id', true);
$code = getArg('code', false);
$code_saved = loadVariable('code_' . $vin, false);
$usecache = true;

/////////////////////////////////////////////////////////////////////////
// Functions
/////////////////////////////////////////////////////////////////////////

function sdk_get_car_id($api_url, $vin, $headers)
{
	$myurlget = $api_url . 'api/1/vehicles';
	$id = '';

	// this API call should not awake the car
	$response = httpQuery($myurlget, 'GET', NULL, NULL, $headers);

	$paramsvehicles = sdk_json_decode($response);

	if ($paramsvehicles['error'] != '') {
		die("Error when getting list of vehicles: " . $paramsvehicles['error']);
	}

	$count = $paramsvehicles['count'];

	if ($count == 0) {
		die("Error when getting list of vehicles : no vehicle.");
	} else if ($vin == 'auto') {  // no vehicle id provided, let's take first
		$id = $paramsvehicles['response'][0]['id_s'];
	} else { // vin is specified
		for ($i = 0; $i < $count; $i++) {
			if ($paramsvehicles['response'][$i]['vin'] == strtoupper($vin)) {
				$id = $paramsvehicles['response'][$i]['id_s'];
				break;
			}
		}
		if ($id == '') {
			die("Error when getting vehicle Id for vin: " . $vin);
		}
	}
	return $id;
}


function sdk_get_car_state($api_url, $id, $headers)
{
	$myurlget = $api_url . 'api/1/vehicles';

	if ($id == 'auto') {  // no vehicle id provided
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

function sdk_get_charge_state($api_url, $id, $headers)
{
	// get GPS data
	$myurlgetgps = $api_url . 'api/1/vehicles/' . $id . '/data_request/charge_state';
	$responsegps = httpQuery($myurlgetgps, 'GET', NULL, NULL, $headers);

	$paramsgps = sdk_json_decode(utf8_encode($responsegps));
	if ($paramsgps['error'] != '') {
		die("Error when getting charge data: " . $paramsgps['error']);
	}
	return $paramsgps['response'];
}

function sdk_wake_up_and_wait($api_url, $id, $vin, $headers, $die = true)
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
	saveVariable('last_xml_success_' . $vin, '');
	saveVariable('last_gps_success_' . $vin, '');

	if ($die) {
		die("wake_up done.");
	}
}

function sdk_action_on_car($api_url, $id, $vin, $headers, $action, $paramjson, $die = true)
{
	// actions on the car
	$state = sdk_get_car_state($api_url, $id, $headers);
	if ($state['state'] != 'online') {
		sdk_wake_up_and_wait($api_url, $id, $vin, $headers, false);
	}

	// commands to the car
	$myurlpost = $api_url . 'api/1/vehicles/' . $id . '/command/' . $action;
	$response = httpQuery($myurlpost, 'POST', $paramjson, NULL, $headers);
	$paramsAction = sdk_json_decode($response);

	if ($paramsAction['response']['result'] != 'true') {
		die("Error when doing action $action " . $paramsAction['error']);
	}

	if ($die)
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

function sdk_array_search($needle, $haystack)
{
	foreach ($haystack as $first_level_key => $value) {
		if ($needle === $value) {
			return $first_level_key;
		}
	}
	return false;
}

function sdk_random_bytes($bytes)
{
	$buf = '';
	$execCount = 0;

	/**
	 * Let's not let it loop forever. If we run N times and fail to
	 * get N bytes of random data, then CAPICOM has failed us.
	 */
	do {
		$buf .= (string) uniqid(rand(), TRUE);
		if (strlen($buf) >= $bytes) {
			/**
			 * Return our random entropy buffer here:
			 */
			return (string) substr($buf, 0, $bytes);
		}
		++$execCount;
	} while ($execCount < $bytes);

	/**
	 * If we reach here, PHP has failed us.
	 */
}

function sdk_get_token($url, $paramjson, $tokentype)
{
	// get token
	$headers_refresh = array("Content-Type: application/json");
	$myurlpost = $url . 'oauth2/v3/token';
	$response = httpQuery($myurlpost, 'POST', $paramjson, NULL, $headers_refresh);
	$paramsToken = sdk_json_decode($response);

	if (!empty($paramsToken['error'])) {
		die("Error when getting the $tokentype access token : " . $paramsToken['error']);
	}
	return $paramsToken;
}

/////////////////////////////////////////////////////////////////////////
// Main code
/////////////////////////////////////////////////////////////////////////

// First time or new code, so let's get the refresh token from the code

$token_is_renewed = false;

if ($action == 'get_state_data') {
	if (!empty($code) &&  ($code_saved != $code || (empty($refresh_token)))) {

		$parentId = sdk_get_id_of_parent_control($moduleId);
		// let's make the code verifier the same than the one generated in the register script 
		$code_verifier = $parentId . substr($code_verifier, 0, 86 - strlen($parentId));

		$text_json = '{ "grant_type": "authorization_code",
		"client_id": "ownerapi",
		"code": "' . $code . '",
		"code_verifier": "' . $code_verifier . '",
		"redirect_uri" : "https://auth.tesla.com/void/callback" }';

		$paramsToken = sdk_get_token($api_auth_url, $text_json, 'first');

		$refresh_token = $paramsToken['refresh_token'];
		saveVariable('refresh_token', $refresh_token);
		$access_token = $paramsToken['access_token'];
		$access_token_start_time = time();
		saveVariable('access_token_start_time', $access_token_start_time);
		saveVariable('access_token', $access_token);

		saveVariable('code_' . $vin, $code); // we used it to detect new code !

		// cleaning of variables
		saveVariable('monitor_mode_' . $vin, '');
		saveVariable('time_when_car_was_active_' . $vin, '');
	}

	// new access token when it is expired
	if ((empty($access_token) || empty($access_token_start_time) ||  ((time() - $access_token_start_time) / 60 > ($access_token_duration - 10)))) { // token age is more than 8 hours minus 10 min
		$headers_refresh = array("Content-Type: application/json");
		$text_json = '{ "grant_type": "refresh_token",
		"client_id": "ownerapi",
		"scope": "openid email offline_access",
		"refresh_token" : "' . $refresh_token . '" }';

		$paramsToken = sdk_get_token($api_auth_url, $text_json, 'renewable');

		saveVariable('access_token_start_time', time());
		$access_token = $paramsToken['access_token'];
		saveVariable('access_token', $access_token);
		$token_is_renewed = true;
	}
}

if (empty($access_token)) {
	die("No valid access token");
}

$headers = array("Authorization: Bearer " . $access_token, "Content-Type: application/json");

$id = '';
// Id of vehicle if auto mode. Let's get the saved one or uses the one provided by the user, or redetect it
// Warning : the Id can change over time... may be when there is a software update....
$id_saved = loadVariable('cached_id_' . $vin);
if (!empty($id_saved)) {
	$id = $id_saved;
}

if ($token_is_renewed || empty($id)) {  // token just renewed ir no id saved.
	// let's refresh the Id to make sure as it can change over time
	$id = sdk_get_car_id($api_url, $vin, $headers);
	saveVariable('cached_id_' . $vin, $id);
}

$vehiclestate = '';
$vehiclestatestate = '';

$cached_vehiclestatestate = loadVariable('cached_vehiclestatestate_' . $vin);
$monitor_mode = loadVariable('monitor_mode_' . $vin); // 'asleep' (monitor every 15 min) or 'active' (monitor every 3 min)
$time_when_car_was_active = loadVariable('time_when_car_was_active_' . $vin); // when we are in active mode, let's store when shift is not null or charge active to switch back to asleep mode after no activity for 10 minutes ($WAIT_TO_SWITCH_TO_ASLEEP)

// Cache duration definition
if ($monitor_mode == 'active' || $monitor_mode == '') {
	$CACHE = $CACHE_DURATION_ACTIVE;
} else {
	$CACHE = $CACHE_DURATION;
}

switch ($action) {
	case 'wake_up':
		sdk_wake_up_and_wait($api_url, $id, $vin, $headers);
		break;

	case 'flash_lights':
	case 'honk_horn':
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, null, true);
		break;

	case 'auto_conditioning_start':
	case 'auto_conditioning_stop':
	case 'door_lock':
	case 'door_unlock':
	case 'charge_start':
	case 'charge_stop':
	case 'charge_port_door_open':
	case 'charge_port_door_close':
	case 'remote_start_drive':
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, null, false);
		break;

	case 'remote_seat_heater_request':
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] . ', "' . $arg[2] . '": ' . $arg[3] . '}';
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);
		// code continue to report data as xml
		break;

	case 'remote_steering_wheel_heater_request':
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);
		// code continue to report data as xml
		break;

	case 'actuate_trunk';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": "' . $arg[1] .  '" }';
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);
		// code continue to report data as xml
		break;

		//charge_current_request_max
	case 'set_charge_limit';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);

		$chargestate = sdk_get_charge_state($api_url, $id, $headers);
		if ((float)$arg[1] <= (float) $chargestate['charge_limit_soc_max']) {
			$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
			sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);
		}
		// code continue to report data as xml
		break;

	case 'set_charging_amps';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);

		$chargestate = sdk_get_charge_state($api_url, $id, $headers);
		if ((float)$arg[1] <= (float) $chargestate['charge_current_request_max']) {
			$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
			sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);
		}
		// code continue to report data as xml
		break;

	case 'set_sentry_mode';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
		sdk_action_on_car($api_url, $id, $vin, $headers, $action, $text_json, false);

		// code continue to report data as xml
		break;

	case 'get_data':
		// most of the meters use this
		// let's return as quickly as possible the cached data
		sdk_header('text/xml');
		$cached_xml = loadVariable('cached_xml_' . $vin);
		echo $cached_xml;
		die();
		break;

	case 'get_state_data':
		$vehiclestate = sdk_get_car_state($api_url, $id, $headers);
		$vehiclestatestate = $vehiclestate['state'];

		// Did we come from asleep to awake ? If yes, we need to switch to active monitor mode
		if (!empty($cached_vehiclestatestate) && $vehiclestatestate == 'online' && $cached_vehiclestatestate == 'asleep') {
			// let's switch to active mode	
			$monitor_mode = 'active';
			saveVariable('time_when_car_was_active_' . $vin, time());
		}
		// else, are we in active mode and we should switch to asleep mode ?
		else if (!empty($monitor_mode) && !empty($time_when_car_was_active) && $monitor_mode == 'active' && ((time() - $time_when_car_was_active) / 60 > $WAIT_TO_SWITCH_TO_ASLEEP)) {
			$monitor_mode = 'asleep';
		}
		// variables not initialized
		else if (empty($monitor_mode) || empty($time_when_car_was_active)) {
			$monitor_mode = 'asleep';
			saveVariable('time_when_car_was_active_' . $vin, time());
		}
		saveVariable('monitor_mode_' . $vin, $monitor_mode);
		saveVariable('cached_vehiclestatestate_' . $vin, $vehiclestatestate);
		sdk_header('text/xml');
		$xml = '<root>
	<vehicle_state>' . $vehiclestatestate . '</vehicle_state>
	<monitor_mode>' . $monitor_mode . '</monitor_mode>
	</root>';

		// Do we need to refresh the cache ?
		$last_xml_success = loadVariable('last_xml_success_' . $vin);

		if ($nocache != 'true') {
			if (!empty($last_xml_success) && ((time() - $last_xml_success) / 60 < $CACHE)) { // we send back the cached response except if the state changed
				// no need to refresh the cache
				echo $xml;
				die();
			}
		}
		break;
}

// follow up of get_state_data action, or commands

if (empty($vehiclestate)) {
	$vehiclestate = sdk_get_car_state($api_url, $id, $headers);
	$vehiclestatestate = $vehiclestate['state'];
}

sdk_header('text/xml');
// get vehicule data

$cached_xml = '<root>
<cached>0</cached>
<monitor_mode>' . $monitor_mode . '</monitor_mode>
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

	$sentrymode = $paramResponse['vehicle_state']['sentry_mode'] ? 100 : 0;
	$isclimateon = $paramResponse['climate_state']['is_climate_on'] ? 100 : 0;

	$shiftstate = empty($paramResponse['drive_state']['shift_state']) ? 'P' : $paramResponse['drive_state']['shift_state'];
	$minutestofullcharge = (float)$paramResponse['charge_state']['minutes_to_full_charge'];

	$hours = floor($minutestofullcharge / 60);
	$minutes = $minutestofullcharge - $hours * 60;
	if ($minutestofullcharge >= 0)
		$timetofullcharge =  ($hours > 0 ? $hours . ' h '   : '') . $minutes . ' min';

	$latitude = $paramResponse['drive_state']['latitude'];
	$longitude = $paramResponse['drive_state']['longitude'];

	// id of parent control, used to send GPS data
	$parentid = sdk_get_id_of_parent_control($moduleId);

	// updating position channel
	setValue($parentid, $latitude . ',' . $longitude);

	$cached_xml .= '<battery_level>' . $paramResponse['charge_state']['battery_level'] . '</battery_level>
<charge_limit_soc>' . $paramResponse['charge_state']['charge_limit_soc'] . '</charge_limit_soc>
<charge_energy_added>' . $paramResponse['charge_state']['charge_energy_added'] . '</charge_energy_added>
<charge_port_door_open>' . ($paramResponse['charge_state']['charge_port_door_open'] ? 100 : 0) . '</charge_port_door_open>
<charging_state>' . $paramResponse['charge_state']['charging_state'] . '</charging_state>
<minutes_to_full_charge>' . $minutestofullcharge . '</minutes_to_full_charge>
<time_to_full_charge>' . $timetofullcharge . '</time_to_full_charge>
<charge_rate>' . $paramResponse['charge_state']['charge_rate'] . '</charge_rate>
<charge_amps>' . $paramResponse['charge_state']['charge_amps'] . '</charge_amps>
<charger_voltage>' . $paramResponse['charge_state']['charger_voltage'] . '</charger_voltage>
<charger_power>' . $paramResponse['charge_state']['charger_power'] . '</charger_power>
<battery_range>' . $batteryrange . '</battery_range>
<est_battery_range>' . $estbatteryrange . '</est_battery_range>
<outside_temp>' . $paramResponse['climate_state']['outside_temp'] . '</outside_temp>
<inside_temp>' . $paramResponse['climate_state']['inside_temp'] . '</inside_temp>
<seat_heater_left>' . $paramResponse['climate_state']['seat_heater_left'] . '</seat_heater_left>
<seat_heater_right>' . $paramResponse['climate_state']['seat_heater_right'] . '</seat_heater_right>
<seat_heater_rear_left>' . $paramResponse['climate_state']['seat_heater_rear_left'] . '</seat_heater_rear_left>
<seat_heater_rear_center>' . $paramResponse['climate_state']['seat_heater_rear_center'] . '</seat_heater_rear_center>
<seat_heater_rear_right>' . $paramResponse['climate_state']['seat_heater_rear_right'] . '</seat_heater_rear_right>
<is_climate_on>' . $isclimateon . '</is_climate_on>
<steering_wheel_heater>' . ($paramResponse['climate_state']['steering_wheel_heater'] ? 100 : 0) . '</steering_wheel_heater>
<odometerkm>' . $odometerkm . '</odometerkm>
<locked>' . ($paramResponse['vehicle_state']['locked'] ? 100 : 0) . '</locked>
<remote_start>' . ($paramResponse['vehicle_state']['remote_start'] ? 100 : 0) . '</remote_start>
<vehicle_name>' . $paramResponse['vehicle_state']['vehicle_name'] . '</vehicle_name>
<shift_state>' . $shiftstate . '</shift_state>
<sentry_mode>' . $sentrymode . '</sentry_mode>
<latitude>' . $latitude . '</latitude>
<longitude>' . $longitude . '</longitude>
<rtclosed>' . ($paramResponse['vehicle_state']['rt'] == 0 ? 100 : 0) . '</rtclosed>
<ftclosed>' . ($paramResponse['vehicle_state']['ft'] == 0 ? 100 : 0) . '</ftclosed>
<access_token_duration_before_exp>' . (($access_token_start_time + $access_token_duration * 60) - time()) / 60 . '</access_token_duration_before_exp>
<speedkmh>' . $speedkmh . '</speedkmh>';
}

$cached_xml .= '</root>';

echo $cached_xml;
$cached_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $cached_xml);

saveVariable('last_xml_success_' . $vin, time());
saveVariable('cached_xml_' . $vin, $cached_xml);
saveVariable('cached_id_' . $vin, $id);
saveVariable('cached_vehicle_data_' . $vin, $paramResponse);
// $paramResponse

// if shift state is D N R or, or if car is charging, or if air conditionning is on, then store the time to continue do active monitoring
if ($paramResponse['drive_state']['shift_state'] != null || $paramResponse['charge_state']['charger_power'] > 0 || $isclimateon == 100 || $sentrymode == 100) {
	saveVariable('time_when_car_was_active_' . $vin, time());
	saveVariable('monitor_mode_' . $vin, 'active');
}
