# rrd_export
Cacti Plugin : Scheduling Export RRD file

This plugin will export rrd file to text format to use in another system. It let's you create a schedule then add datasources to each schedule.

## Features
* Mirror poller collection to logfile
* Custom output log path
* Output poller data in KV pairs (key-value pairs)
* Enable logfile rotation
* Enable Mirage Debug logs (writes to cacti.log)

## Prerequisites
Dev and Test on : 
* Cacti version 0.8.8+ 
* PIA version 3.1

## Installation
### setup plugins
* Copy file into ```/<CACTI_ROOT>/plugins/rrdexport/```  
make sure that folder name after plugins is ```rrdexport``` and store file in it
* Install rrdexport through ```Cacti Plugin Management```
* Go to ```Cacti Setting``` page then ```RRD Export Schedules```. Review log path and save
* Make sure both log path in setting and ```/<CACTI_ROOT>/plugins/rrdexport/``` are writable.
* Enable rrdexport pluging through ```Cacti Plugin Management```

### setup schedules job
* Go to ```Management > RRD Export Schedules```
* Add or edit new schedule
* Add associate data source to schedule.

