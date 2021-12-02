# Tesla Car Application for Eedomus

Follow your Tesla car with this plugin for [Eedomus](https://www.eedomus.com/).

This plugin was developped by [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

See this [forum thread](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

Current version is v1.6.0.

## Features

![tesla car overview](https://user-images.githubusercontent.com/94607717/144480490-5f20b465-0030-4763-853d-096b30bf684f.png)

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

![tesla car actions](https://user-images.githubusercontent.com/94607717/143620966-adb1b4a2-d270-4eeb-ae6b-5c9a7aa78c9f.png)

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
- start/stop sentinel

Note : each command calls the 'wake_up' command before, if needed.

## Installation and configuration

Install the plugin from the Eedomus store.

It is recommended to have your Tesla car awake when you install the plug-in. To awake tour car, launch the Tesla App on your phone, or open/close a door.

### Code and authentication

The plug-in will get automatically the access token using the Tesla backend. It will renew it automatically every 8 hours.

The plugin needs a code to retrieve the tokens. To obtain this code, do the following :

- Click on the link in the plugin installation page to authenticate with your Tesla account

![auth url](https://user-images.githubusercontent.com/94607717/144481095-e7c54f83-dd18-4d6d-8318-850c501ef9b3.png)

- Your browser will go to the Tesla web site and you will login with your Tesla account.
- When it's done, a "Page Not Found" will be displayed by Tesla. This is expected. Inspect the URL and extract the **code** parameter from it (everything after `code=` and before the next `&`)

![auth url](https://user-images.githubusercontent.com/94607717/144481395-b52b58f2-90b6-42c3-9f9a-4202525e1cca.png)

- Code is valid 2 minutes. Paste it to the corresponding field in the plugin installation page.

![code paste](https://user-images.githubusercontent.com/94607717/144481146-d171ace8-56f7-43d1-8b79-e8a90a543a62.png)

### Vehicle id

By default, the plugin uses the first vehicle of your account. You can force a specific vehicle if needed by providing its id.

### Creation

Click on create.
Then you can go the Tesla room. You should see the data a few seconds after (if the car is awake).

## Note on polling interval and battery drain

There are optimizations in the plugin to avoid battery drain. Here are some details :

- Polling interval is from 2 to 5 minutes for the meters but there is a data cache of 15 minutes in the script to allow the car go to sleep. So the data reported can be 15 min late, including for the geolocation data.
- When the car is asleep, car general data and GPS data are retrieved every 15 minutes but data will be empty (or the same) as the car is asleep. There is an exception for the **car state** which uses a different API : state is always updated every 3 minutes.
- When car is active (air conditioning is on, charging happening, car is not parked, or sentinel is on), then monitoring is done every 3 minutes. If the car seems inactive for 10 minutes, the monitoring switch back to every 15 minutes so the car can go asleep.

## Note on electricity price

You can modify the price per kWh in the configuration of the 'Charge energy added (cost)" meter. Update the value in the XPATH expression.

![cost](https://user-images.githubusercontent.com/94607717/144512926-09530b1b-6056-4e5a-8109-d33c3a625384.png)
