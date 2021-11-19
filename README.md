# Tesla Car Application for Eedomus

Follow your Tesla car with this plugin for Eedomus.

This plugin was developped by [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

See this [forum thread](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

## Configuration

### Tesla access token

To get a Tesla access token, you can can use a Tesla token app [on Android](https://play.google.com/store/apps/details?id=net.leveugle.teslatokens&hl=fr) or [on iOS](https://apps.apple.com/us/app/auth-app-for-tesla/id1552058613). Token must be renewed every 6 weeks.

### Vehicle id

By default, the plug in will use the first vehicle of your account. You can force a specific vehicle if needed.

## Features

The plugin reports the following data to Eedomus :

- geolocalisation of the car
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

**Note**
Polling interval is 5 minutes. The script does not awake the car to avoid battery drain. As a consequence, the data cannot be retrieved when the car is asleep.