# Check_VCSA_Mon
Nagios Library in PHP to monitor the health, access and resources of VMware VCSA

Usage: "check_vcsa_mon.php" -H "<hostname>"  -f  "/path/to/authfile" -a "<api counter>" -c "<critical>" -o "<option>"
	NOTE: -H, -f, -c must be specified

	Options:
	-h
	     Print this help and usage message
	-H
	     Hostname to query
	-f
	     the full path to the authentication file to used
	-c
	     The critical value to be evaluted against
	-a
		 The upper level api to check
         1. health
         2. networking
         3. servicelist
         4. service
         5. monitoringlist
         6. access
         7. datastorelist
         8. datastorefree
         9. monitoring
    -o
         Some check have required/availble options that need to passed to the plugin
         1. health options (required)
            system
            load
            mem
            cpu
            storage
            database-storage
            
         2. networking options (required)
            interfaces
            
         3. service options
            servicename
            servicestate
         
         4. access options
            consolecli
            dcui
            shell
            ssh
            
        5. monitoring options
           cpu
           mem
           eth <requires -m value (txa, rxa)> txpr = transfer packet rate, txa = transfer activity rate, rxpr = recieve packet rate, rxa = reveive activity 
            

	This plugin will check the condition of Access/Service/Health/Monitoring/Storage Indicators for a VCenter Appliance via the REST API.
	Examples:
	     Health
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f  "/my/path/configs/192.168.1.1.cfg" -a "health" -c "yellow" -o "system"
         
         Complete list of services
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "servicelist" 
         
         Service status
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "service" -c "up" -o "xinitd" 
         
         Access status
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "access" -c "enabled" -o "ssh" 
         
         Datastore List
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "datastorelist" -c "" -o "" 
         
         Datastore Free Space
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "monitoring" -c "10" -u "B,MB,GB,TB" -o "datastore" -m "free" 
         
         CPU Load Monitoring
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "monitoring" -c "15" -u "%" -o "cpu" 
         
         Memory Usage Monitoring
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "monitoring" -c "95" -u "B,MB,GB,TB" -o "mem" 
         
         File System Usage Monitoring
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "monitoring" -c "10" -u "MB" -o "fsystem" -m "root,log,boot" 
         $/usr/bin/php -q ".PROGRAM." -H "192.168.1.1" -f "/my/path/configs/192.168.1.1.cfg" -a "monitoring" -c "10" -u "KB" -o "fsystem" -m "imagebuilder"
         "
