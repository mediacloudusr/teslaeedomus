# Tesla Car Application for Eedomus

Follow your Tesla car with this plugin for [Eedomus](https://www.eedomus.com/).

This plugin was developped by [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

See this [forum thread](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

Current version is v1.4.

## Features

![tesla car overview](https://user-images.githubusercontent.com/94607717/143082462-a6ddc241-7754-4ad4-86c0-0ac2333d2d56.png)

The plugin reports the following data to Eedomus :

- geolocalisation of the car
- car state (asleep, online)
- doors state
- heat and air conditionning state
- left and right seat heater state
- battery level
- limit soc
- energy added
- charge port door status
- charging state
- minutes to full charge
- time to full charge (h min)
- charge rate
- charger voltage
- charger power
- charge limit defined
- charge energy added (kWh, cost)
- charging state
- battery range
- estimated battery range
- outside temperature
- inside temperature
- odometer
- lock state
- vehicle name
- shift state
- speed
- sentinel (sentry) state

![tesla car actions](https://user-images.githubusercontent.com/94607717/143082466-fc94abad-1e12-46c0-9315-e209ff066bd4.png)

It exposes the following commands which can be used in rules :

- wake_up
- start/stop heat and air conditionning
- start/stop left and right seat heaters and set level (prerequisite : heat and air conditionning should be On)
- lock/unlock doors
- flash lights
- honk horn
- start/stop charging
- open/close charge port door
- set charge rate (A)
- set charge limit (%)

Note : each command calls the 'wake_up' command before, if needed.

## Installation

Install the plugin from the Eedomus store.
It is recommended :
- to create a Telsa room and assign it to the plugin at creation time,
- have your Tesla car awake when you install the plug-in.

### Tesla access token

To get a Tesla access token, you can can use a Tesla token app [on Android](https://play.google.com/store/apps/details?id=net.leveugle.teslatokens&hl=fr) or [on iOS](https://apps.apple.com/us/app/auth-app-for-tesla/id1552058613). Token must be renewed every 6 weeks.

### Vehicle id

By default, the plugin uses the first vehicle of your account. You can force a specific vehicle if needed by providing its id.

## Polling interval and battery drain

There are optimizations in the script to avoid battery drain. Here are some details :

- Polling interval is 2 to 5 minutes for the meters but there is a data cache of 15 minutes in the script to allow the car go to sleep. So the data reported can be 15 min late, including for the geolocation data.
- When the car is asleep, car general data and GPS data are retrieved every 15 minutes but data will be empty as the car is asleep. There is an exception for the car state which uses a different API : state is always updated every 3 minutes.
- When car is active (air conditioning is on, charging happening, car is not parked, sentinel is on), then monitoring is done every 3 minutes. If the car seems inactive for 10 minutes, the monitoring switchback to every 15 minutes so the car can go asleep if needed.

This behavior may be improved in a future version of the plugin.
