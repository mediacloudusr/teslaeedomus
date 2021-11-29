<?php
// PHP Script for Tesla plugin for Eedomus
// Version 1.4 - November 2021

/////////////////////////////////////////////////////////////////////////
// Constants
/////////////////////////////////////////////////////////////////////////
$api_url = 'https://owner-api.teslamotors.com/';
$CACHE_DURATION = 15; // minutes. Do not set to lower than 15 min if you want your car to go asleep
$CACHE_DURATION_ACTIVE = 3; // minutes when we are actively monitoring 
$WAIT_TO_SWITCH_TO_ASLEEP = 10; // minutes when we are actively monitoring 
$monitor_mode = loadVariable('monitor_mode'); // 'asleep' (monitor every 15 min) or 'active' (monitor every 3 min)
$time_when_car_was_active = loadVariable('time_when_car_was_active'); // when we are in active mode, let's store when shift is not null or charge active to switch back to asleep mode after no activity for 10 minutes ($WAIT_TO_SWITCH_TO_ASLEEP)

$token = getArg('token', true);
$token = str_replace(' ', '', $token);
$headers = array("Authorization: Bearer " . $token, "Content-Type: application/json");
$action = getArg('action', false, 'get_data');
$nocache = getArg('nocache', false, 'false'); // used for debug
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


function sdk_action_on_car($api_url, $id, $headers, $action, $paramjson, $die = true)
{
	// actions on the car
	$state = sdk_get_car_state($api_url, $id, $headers);
	if ($state['state'] != 'online') {
		sdk_wake_up_and_wait($api_url, $id, $headers);
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


/////////////////////////////////////////////////////////////////////////
// Main code
/////////////////////////////////////////////////////////////////////////

// Id of vehicle. Let's get the saved one or uses the one provided by the user, or redetect it
$id_saved = loadVariable('cached_id');
if ($id_saved != '' && $id == '(auto)') {
	$id = $id_saved;
}

$vehiclestate = sdk_get_car_state($api_url, $id, $headers);
$vehiclestatestate = $vehiclestate['state'];
$cached_vehiclestatestate = loadVariable('cached_vehiclestatestate');

// Cache duration definition
if ($monitor_mode == 'active') {
	$CACHE = $CACHE_DURATION_ACTIVE;
} else {
	$CACHE = $CACHE_DURATION;
}

switch ($action) {
	case 'wake_up':
		sdk_wake_up_and_wait($api_url, $id, $headers);
		break;

	case 'flash_lights':
	case 'auto_conditioning_start':
	case 'auto_conditioning_stop':
	case 'honk_horn':
	case 'door_lock':
	case 'door_unlock':
	case 'charge_start':
	case 'charge_stop':
	case 'charge_port_door_open':
	case 'charge_port_door_close':
		sdk_action_on_car($api_url, $id, $headers, $action, null);
		break;

	case 'remote_seat_heater_request':
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] . ', "' . $arg[2] . '": ' . $arg[3] . '}';
		sdk_action_on_car($api_url, $id, $headers, $action, $text_json);
		break;


		//charge_current_request_max

	case 'set_charge_limit';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);

		$chargestate = sdk_get_charge_state($api_url, $id, $headers);
		if ((float)$arg[1] <= (float) $chargestate['charge_limit_soc_max']) {
			$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
			sdk_action_on_car($api_url, $id, $headers, $action, $text_json, false);
		}
		// code continue to report data as xml
		$nocache = 'true';
		break;

	case 'set_charging_amps';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);

		$chargestate = sdk_get_charge_state($api_url, $id, $headers);
		if ((float)$arg[1] <= (float) $chargestate['charge_current_request_max']) {
			$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
			sdk_action_on_car($api_url, $id, $headers, $action, $text_json, false);
		}
		// code continue to report data as xml
		$nocache = 'true';
		break;

	case 'set_sentry_mode';
		$actionparam = getArg('actionparam', true);
		$arg = explode(",", $actionparam);
		$text_json    = '{ "' . $arg[0] . '": ' . $arg[1] .  '}';
		sdk_action_on_car($api_url, $id, $headers, $action, $text_json, false);

		// code continue to report data as xml
		$nocache = 'true';
		break;

	case 'get_state_data':

		// Did we come from asleep to awake ? If yes, we need to switch to active monitor mode
		if (!empty($cached_vehiclestatestate) && $vehiclestatestate == 'online' && $cached_vehiclestatestate == 'asleep') {
			// let's switch to active mode	
			$monitor_mode = 'active';
			saveVariable('time_when_car_was_active', time());
		}
		// else, are we in active mode and we should switch to asleep mode ?
		else if (!empty($monitor_mode) && !empty($time_when_car_was_active) && $monitor_mode == 'active' && ((time() - $time_when_car_was_active) / 60 > $WAIT_TO_SWITCH_TO_ASLEEP)) {
			$monitor_mode = 'asleep';
		}
		// variables not initialized
		else if (empty($monitor_mode) || empty($time_when_car_was_active)) {
			$monitor_mode = 'asleep';
			saveVariable('time_when_car_was_active', time());
		}
		saveVariable('monitor_mode', $monitor_mode);
		saveVariable('cached_vehiclestatestate', $vehiclestatestate);
		sdk_header('text/xml');
		$xml = '<root>
<vehicle_state>' . $vehiclestatestate . '</vehicle_state>
<monitor_mode>' . $monitor_mode . '</monitor_mode>
</root>';
		echo $xml;
		die();
		break;

	case 'get_gps_data':
		if ($vehiclestatestate == 'online') {

			// Return cache ?
			$last_gps_success = loadVariable('last_gps_success');
			$cached_vehiclestatestate_for_gps = loadVariable('cached_vehiclestatestate_for_gps');

			// let's interrogate GPS only when needed and let the car to to sleep
			if ($nocache != 'true') {
				if (!empty($cached_vehiclestatestate_for_gps) && !empty($last_gps_success) && ($cached_vehiclestatestate_for_gps == $vehiclestatestate) && ((time() - $last_gps_success) / 60 < $CACHE)) {
					sdk_header('text/xml');
					$cached_gps_xml = loadVariable('cached_gps_xml');
					echo $cached_gps_xml;
					die();
				}
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
		break;

	default:
		break;
}

// Return cache ?
$last_xml_success = loadVariable('last_xml_success');

if ($nocache != 'true') {
	if (!empty($last_xml_success) && ((time() - $last_xml_success) / 60 < $CACHE)) { // we send back the cached response except if the state changed
		sdk_header('text/xml');
		$cached_xml = loadVariable('cached_xml');
		echo $cached_xml;
		die();
	}
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
	$sentrymode = $paramResponse['vehicle_state']['sentry_mode'] ? "true" : "false";
	$shiftstate = empty($paramResponse['drive_state']['shift_state']) ? 'P' : $paramResponse['drive_state']['shift_state'];
	$minutestofullcharge = (float)$paramResponse['charge_state']['minutes_to_full_charge'];

	$hours = floor($minutestofullcharge / 60);
	$minutes = $minutestofullcharge - $hours * 60;
	if ($minutestofullcharge > 0)
		$timetofullcharge =  ($hours > 0 ? $hours . ' h '   : '') . $minutes . ' min';

	$cached_xml .= '<battery_level>' . $paramResponse['charge_state']['battery_level'] . '</battery_level>
<charge_limit_soc>' . $paramResponse['charge_state']['charge_limit_soc'] . '</charge_limit_soc>
<charge_energy_added>' . $paramResponse['charge_state']['charge_energy_added'] . '</charge_energy_added>
<charge_port_door_open>' . $chargeportdooropen . '</charge_port_door_open>
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
<is_climate_on>' . $isclimateon . '</is_climate_on>
<odometerkm>' . $odometerkm . '</odometerkm>
<locked>' . $locked . '</locked>
<vehicle_name>' . $paramResponse['vehicle_state']['vehicle_name'] . '</vehicle_name>
<shift_state>' . $shiftstate . '</shift_state>
<sentry_mode>' . $sentrymode . '</sentry_mode>
<speedkmh>' . $speedkmh . '</speedkmh>';
}

$cached_xml .= '</root>';

echo $cached_xml;
$cached_xml = str_replace('<cached>0</cached>', '<cached>1</cached>', $cached_xml);

saveVariable('last_xml_success', time());
saveVariable('cached_xml', $cached_xml);
saveVariable('cached_id', $id);

// if shift state is D N R or, or if car is charging, or if air conditionning is on, then store the time to continue do active monitoring
if ($paramResponse['drive_state']['shift_state'] != null || $paramResponse['charge_state']['charger_power'] > 0 || $isclimateon == "true" || $sentrymode == "true") {
	saveVariable('time_when_car_was_active', time());
	saveVariable('monitor_mode', 'active');
}
