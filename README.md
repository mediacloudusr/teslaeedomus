# Tesla Car Application for Eedomus

Follow your Tesla car with this plugin for Eedomus.

This plugin was developped by [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

See this [forum thread](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

Current version is v1.2.

## Configuration

### Tesla access token

To get a Tesla access token, you can can use a Tesla token app [on Android](https://play.google.com/store/apps/details?id=net.leveugle.teslatokens&hl=fr) or [on iOS](https://apps.apple.com/us/app/auth-app-for-tesla/id1552058613). Token must be renewed every 6 weeks.

### Vehicle id

By default, the plugin uses the first vehicle of your account. You can force a specific vehicle if needed.

## Features

The plugin reports the following data to Eedomus :

- geolocalisation of the car
- car state
- battery level
- limit soc
- energy added
- charge port door status
- charging state
- minutes to full charge
- charge rate
- charger voltage
- Charger power
- battery range
- estimated battery range
- outside temperature
- inside temperature
- odometer
- lock state
- vehicle name
- shift state
- speed

## Polling interval and battery drain

There are some optmizations in the plugin to avoid battery drain. Here are the details :

- Polling interval is 5 minutes for the meters but there is a data cache of 15 minutes in the script to allow the car go to sleep. So the data reported can be 15 min late, including for the geolocation data.
- When the car is asleep, data is not retrieved to avoid awaking the car and creating a battery drain. There is an exception for the car state which uses a different API : state is always updated every 15 minutes.
- It may take up to 15 minutes to know that the car is back as online and retrieve the data again.

This behavior may be improved in a future version of the plugin.