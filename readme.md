Simple script to turn on a group of hue lights at sunset+x

### Turning a group on at twilight
1. Configure config.json (see the example)
..1. Set the group you want to control
..2. Set the username hash from the hub
..3. Set the IP address of the hub
..4. Set the offset between sunset and when you want the lights on
..5. Set the loop delay
2. Execute `php run twilight`

### Turning a group off at night
1. Configure night_config.json (see the example)
..1. Set the group you want to control
..2. Set the username hash from the hub
..3. Set the IP address of the hub
..4. Set the offset between sunset and when you want the lights on
..5. Set the loop delay
2. Execute `php run night` 

### Configure the cron

Example cron below

```
0 16 * * *      /usr/bin/php /path/to/project/Twilight/run.php twilight
0 16 * * *      /usr/bin/php /path/to/project/Twilight/run.php night
```

In essence the twilight one runs at 16:00 and waits for the time to pass before turning the group on and exiting. 
The night one runs at 4 and waits for the cut of time to lapse before turning the lights off and exiting. 

## Logs
Output is sent to twilight.log which is in the root of the project