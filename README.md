# Tesla Car Application for Eedomus

Follow your Tesla car with this plugin for Eedomus.

This plugin was developped by [mediacloud](https://forum.eedomus.com/ucp.php?i=pm&mode=compose&u=5280).

See this [forum thread](https://forum.eedomus.com/viewtopic.php?f=16&t=10515) for more information or for feedback.

## Configuration

### Tesla access token

To create a Tesla access token, you can can use the [Tesla tokens app for Android](https://play.google.com/store/apps/details?id=net.leveugle.teslatokens&hl=fr). Token must be renewed every 6 weeks.

### Vehicle id

By default, the plug in will use the first vehicle of your account. You can force a specific vehicle if needed.

## Features

The plugin (v 1.0) reports the following data to Eedomus :

- geolocalisation of the car
- battery level
- soc level
- energy added
- charge port door status
- charging state
- minutes to full charge
- charge rate
- outside temperature
- inside temperature
- odometer
- lock state
- vehicle name
- shift state
- speed

Polling interval is 5 minutes.