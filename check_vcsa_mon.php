#!/usr/bin/php
<?php
// check_vcsa_mon.php
// version 0.1.0
// Created by: SNapier


define("PROGRAM", 'check_vcsa_mon.php');
define("VERSION", 'Beta-0.112');
define("STATUS_OK", 0);
define("STATUS_WARNING", 1);
define("STATUS_CRITICAL", 2);
define("STATUS_UNKNOWN", 3);
define("DEBUG", false);

function parse_args() {
    $specs = array(array('short' => 'h',
                         'long' => 'help',
                         'required' => false),
                   array('short' => 'H',
                         'long' => 'hostname', 
                         'required' => true),
                   array('short' => 'f', 
                         'long' => 'authfile', 
                         'required' => false),
                   array('short' => 'a', 
                         'long' => 'apicounter', 
                         'required' => false),
                   array('short' => 'c', 
                         'long' => 'critical', 
                         'required' => false),
                   array('short' => 'o', 
                         'long' => 'option', 
                         'required' => false),
                   array('short' => 'u', 
                         'long' => 'unit', 
                         'required' => false),
                   array('short' => 'm', 
                         'long' => 'mquery', 
                         'required' => false)
    );
    
    $options = parse_specs($specs);
    return $options;
}

function parse_specs($specs) {

    $shortopts = '';
    $longopts = array();
    $opts = array();

    // Create the array that will be passed to getopt
    // Accepts an array of arrays, where each contained array has three 
    // entries, the short option, the long option and required
    foreach($specs as $spec) {    
        if(!empty($spec['short'])) {
            $shortopts .= "{$spec['short']}:";
        }
        if(!empty($spec['long'])) {
            $longopts[] = "{$spec['long']}:";
        }
    }

    // Parse with the builtin getopt function
    $parsed = getopt($shortopts, $longopts);

    // Make sure the input variables are sane. Also check to make sure that 
    // all flags marked required are present.
    foreach($specs as $spec) {
        $l = $spec['long'];
        $s = $spec['short'];

        if(array_key_exists($l, $parsed) && array_key_exists($s, $parsed)) {
            plugin_error("Command line parsing error: Inconsistent use of flag: ".$spec['long']);
        }
        if(array_key_exists($l, $parsed)) {
            $opts[$l] = $parsed[$l];
        }
        elseif(array_key_exists($s, $parsed)) {
            $opts[$l] = $parsed[$s];
        }
        elseif($spec['required'] == true) {
            plugin_error("Command line parsing error: Required variable ".$spec['long']." not present.");
        }
    }
    return $opts;

}

function debug_logging($message) {
    if(DEBUG) {
        echo $message;
    }
}

function plugin_error($error_message) {
    //print("***ERROR***:\n\n{$error_message}\n\n");
    fullusage();
    nagios_exit('', STATUS_UNKNOWN);
}

function nagios_exit($stdout='', $exitcode=0) {
    print($stdout);
    exit($exitcode);
}

//Where the actual check takes place
function main() {
    $options = parse_args();
    
    if(array_key_exists('version', $options)) {
        print('Plugin version: '.VERSION);
        fullusage();
        nagios_exit('', STATUS_OK);
    }

    //Enable before production
    //check_environment();
    // check the value of the API counter and see what function to run
    if($options['apicounter'] == "health"){
        if($options['option'] != ""){
            check_vcsa_health($options);
        }else{
            nagios_exit("UNKNOWN: Required ".strtoupper($options['apicounter'])." command input missing! The \"-o\" vairable is blank. ", STATUS_UNKNOWN); 
        }
    }elseif($options['apicounter'] == "networking"){
        if($options['option'] != ""){
            check_vcsa_network($options);
        }else{
            nagios_exit("UNKNOWN: Required ".strtoupper($options['apicounter'])." command input missing! The \"-o\" vairable is blank. ", STATUS_UNKNOWN); 
        }
    }elseif($options['apicounter'] == "service"){
        if($options['option'] != ""){
            check_vcsa_service($options);
        }else{
            nagios_exit("UNKNOWN: Required ".strtoupper($options['apicounter'])." command input missing! The \"-o\" vairable is blank. ", STATUS_UNKNOWN); 
        }
    }elseif($options['apicounter'] == "access"){
        //Here we should step through all the required objects before moving to the vcsa access check
        if($options['option'] != ""){
            check_vcsa_access($options);
        }else{
            nagios_exit("UNKNOWN: Required ".strtoupper($options['apicounter'])." command input missing! The \"-o\" vairable is blank. ", STATUS_UNKNOWN); 
        }
    }elseif($options['apicounter'] == "datastorefree"){
        //Here we should step through all the required objects before moving to the vcsa access check
        if($options['option'] != ""){
            check_vcsa_datastorefree($options);
        }else{
            nagios_exit("UNKNOWN: Required ".strtoupper($options['apicounter'])." command input missing! The \"-o\" vairable is blank. ", STATUS_UNKNOWN); 
        }
    }elseif($options['apicounter'] == "datastorelist"){
            check_vcsa_datastorelist($options);
    }elseif($options['apicounter'] == "monitoring"){
            check_vcsa_monitoring2($options);
    }elseif($options['apicounter'] == "servicelist"){
            check_vcsa_servicelist($options);
    }elseif($options['apicounter'] == "monitoringlist"){
            check_vcsa_monitoringlist($options);
    }else{
        nagios_exit("UNKNOWN: NOTHING TO DO!", STATUS_UNKNOWN); 
    }
}

//Once determined all the things needed then add them to the environment
//pre-flight check list
function check_environment() {
    exec('which php 2>&1', $execout, $return_var);
    $php_path = $execout[0];

    if ($return_var != 0) {
        plugin_error("PHP is not installed in your system.");
    }
}

//
//PLUGIN FUNCTIONS
//

function check_vcsa_health($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    //The observed vlaue returned by the check
    $value = apiHealthCheck($api_session, $options);
    //Pull the critical threshold from the command input
    $critical = (!empty($options['critical'])) ? $options['critical'] : null;
    //Compare the cmd output to the threshold value
    if ($critical !== null && $value == $critical) {
        nagios_exit("CRITICAL -  Status for ".strtoupper($options['apicounter']."-".$options['option'])." is (".$value.") | ".$options['apicounter']."-".$options['option']."=".$value." \n\n", STATUS_CRITICAL);
    }
    else {
        nagios_exit("OK - Status for ".strtoupper($options['apicounter']."-".$options['option'])." is (".$value.") | ".$options['apicounter']."-".$options['option']."=".$value." \n\n", STATUS_OK);
    }
}

function check_vcsa_network($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    //echo "apiAuth = ".$api_session;
    if($options['option'] == "interfaces"){
        $badNicCount = "0";
        $nicCount = "0";
        $msg = "";
        //The observed vlaue returned by the check
        $result = apiNetworkCheck($api_session, $options);
        if(array($result)){
            //print print_r($result, TRUE);
            foreach($result as $res ){
                $nicCount = count($res);
                foreach($res as $nic){
                $msg = $msg . $nic->name."=".$nic->status;
                    //print print_r($nic, TRUE);
                    if($nic->status != "up"){
                    $badNicCount ++;
                    } 
               }
               
            }
            
            
            if($nicCount != "0" && $badNicCount > "0"){
                nagios_exit("CRITICAL - Network Check Returns (".$badNicCount."/".$nicCount.") is Down!| NicDown=".$badNicCount." \n\n", STATUS_CRITICAL);
            }else{
                nagios_exit("OK - Network Check Returns ALL (".$nicCount.") Interface/s are UP. ".$msg. "| NicDown=".$badNicCount." \n\n", STATUS_OK);
            }
        } 
    }else{
        $value = "TEST";
    }
    
    //Pull the critical threshold from the command input
    $critical = (!empty($options['critical'])) ? $options['critical'] : null;
    //Compare the cmd output to the threshold value
    if ($critical !== null && $value == $critical) {
        nagios_exit("CRITICAL - Network Check Returns ".$value." , This is the same as the critical (".$critical.") value! | value=".$value." \n\n", STATUS_CRITICAL);
    }
    else {
        nagios_exit("OK - Network Check Returns ".$value." This is not the same as the critical value to be expected (".$critical.") | value=".$value." \n\n", STATUS_OK);
    }
}

function check_vcsa_access($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    //echo "apiAuth = ".$api_session;
    $msg = "";
    $state = "";
    $result = apiAccessCheck($api_session, $options);
        if($result){
            if($result == "true"){
                $state = "ENABLED";
                $msg = $options['option']."=ENABLED";
            }else{
                $state = "DISABLED";
                $msg = $options['option']."=DISABLED";
            }
        }    
        if(strtolower($state) == $options['critical']){
            nagios_exit("CRITICAL - Access Service ".strtoupper($options['option'])." is ".strtoupper($state)."!| ".$options['option']."=".$state." \n\n", STATUS_CRITICAL);
        }else{
            nagios_exit("OK - Access Service ".strtoupper($options['option'])." is ".strtoupper($state)."!| ".$options['option']."=".$state." \n\n", STATUS_OK);
        }
}

function check_vcsa_service($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    //echo "apiAuth = ".$api_session;
    $serviceCount = "0";
    $badServiceCount = "0";
    $msg = "";
    $result = apiServiceCheck($api_session, $options);
        if($result){
            $msg = $options['option']."=".$result;
        }
            
        if($result == $options['critical']){
            nagios_exit("CRITICAL - Service ".$options['option']." is ".strtoupper($result)."! | ".$options['option']."=".$result." \n\n", STATUS_CRITICAL);
        }else{
            nagios_exit("OK - Service ".$options['option']." is ".strtoupper($result)."! | ".$options['option']."=".$result." \n\n", STATUS_OK);
        }
}

function check_vcsa_servicelist($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    $serviceCount = "0";
    $badServiceCount = "0";
    $msg = "";
    $result = apiServiceListCheck($api_session, $options);
        if(array($result)){
            foreach($result as $services){
               foreach($services as $service){
                    $serviceCount ++;
                    $msg = $msg . $service->name.", ";
               }
            }
            if($serviceCount == "0"){
                nagios_exit("CRITICAL - Service Check Returned a count of (".$serviceCount.") services | Services=".$serviceCount." \n\n", STATUS_CRITICAL);
            }else{
                nagios_exit("OK - Service Check Returned a count of (".$serviceCount.") Service/s. ".$msg. " | Services=".$serviceCount." \n\n", STATUS_OK);
            }
        }
}

function check_vcsa_datastorelist($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    $dsCount = "0";
    $msg = "";
    $result = apiDatastoreListCheck($api_session, $options);
    //var_dump($result);    
        if(array($result)){
            foreach($result as $datastores){
                $totalds = count($datastores);
               foreach($datastores as $datastore){
                    $dsCount ++;
                    if($dsCount < $totalds && $dsCount > "1"){
                        $msg = $msg . $datastore->name.", ";
                    }else{
                        $msg = $msg . $datastore->name;
                    }
               }
            }
            if($dsCount == "0"){
                nagios_exit("CRITICAL - Datastore Check Returned a count of (".$dsCount.") Datastores| dscount=".$dsCount." \n\n", STATUS_CRITICAL);
            }else{
                nagios_exit("OK - Datastore Check Returned a count of (".$dsCount.") Datastores. (".$msg. ")| dscount=".$dsCount." \n\n", STATUS_OK);
            }
        }
}

function check_vcsa_datastorefree($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    $dsCount = "0";
    $msg = "";
    $perfdata = "";
    $result = apiDatastoreCheck($api_session, $options);  
        if(array($result)){
            foreach($result as $datastores){
               $totalds = count($datastores);
               foreach($datastores as $datastore){
                    $dsCount ++;
                    //Format the storage values to appropriate size
                    $dscap = formatBytes($datastore->capacity);
                    $dsfree = formatBytes($datastore->free_space);
                    $dsused = $dscap - $dsfree;
                    $dsused = formatBytes($dsused);
                    //Math for percentage used
                    $dcap = $datastore->capacity;
                    $dfree = $datastore->free_space;
                    $dused = $dcap - $dfree;
                    $pused = strval(round($dused/$dcap*100, 2));
                    //Datastore Message
                    $msg = $msg . strtoupper($datastore->name) . " has ".$dsfree." of ".$dscap." free";
                    $perfdata = $perfdata . $datastore->name ."=".$dsfree." ";
                    //This needs to be extended to count for the number fo total DS with less than critical percentage free
                    //if the count is greater than 0 then the check will be critical else it will be OK
               }
            }
            if($pused >= $options['critical']){
                nagios_exit("CRITICAL - ".$msg." | ".$perfdata." \n\n", STATUS_CRITICAL);
            }else{
                nagios_exit("OK - ".$msg." | ".$perfdata." \n\n", STATUS_OK);
            }
        }
}

function check_vcsa_monitoringlist($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    $count = "0";
    $msg = "";
    $result = apiMonitoringListCheck($api_session, $options);
        if(array($result)){
            foreach($result as $res){
                    $total = count($res);
                    foreach($res as $mon){
                        $count ++;
                        if($count < $total){
                            $msg = $msg . $mon->id . " , ";
                        }else{
                            $msg = $msg . $mon->id ;
                        }
                        
                    }
                }
            if($count == "0"){
                nagios_exit("CRITICAL - Service Check Returned a count of (".$count.") Instance IDs | instanceID=".$count." \n\n", STATUS_CRITICAL);
            }else{
                nagios_exit("OK - Service Check Returned a count of (".$count.") Instance IDs. ( ".$msg." ) | instanceID=".$count." \n\n", STATUS_OK);
            }
        }
}

//
//Upgraded command
//
function check_vcsa_monitoring2($options){
    if($options['option'] == "mem"){
        //$values = getMem($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true)."\n\n";
        foreach($result as $metric){
            foreach($metric as $stat){
                if(is_array($stat->data)){
                    $pointName = $stat->name;
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPoint = formatBytes2($avg, $options['unit']);
                    //echo print_r($formatPoint, true);
                    if($formatPoint['value'] >= $options['critical']){                       
                       echo "CRITICAL: ".$formatPoint['value'].$options['unit']." is greater than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];                       
                    }else{    
                        echo "OK: ".$formatPoint['value'].$options['unit']." is less than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];                        
                    }
                }
            }
        }
    }elseif($options['option'] == "cpu"){
        //$values = getMem($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true)."\n\n";
        foreach($result as $metric){
            foreach($metric as $stat){
                if(is_array($stat->data)){
                    $pointName = $stat->name;
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    //$formatPoint = formatBytes2($avg, $options['unit']);
                    $formatPoint['value'] = $avg;
                    
                    //echo print_r($formatPoint, true);
                    if($formatPoint['value'] >= $options['critical']){
                       echo "CRITICAL: ".$formatPoint['value'].$options['unit']." is greater than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];
                    }else{
                        echo "OK: ".$formatPoint['value'].$options['unit']." is less than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];
                    }
                }
            }
        }
    }elseif($options['option'] == "eth0" && $options['mquery'] == "txa" ){
        //$values = getMem($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true)."\n\n";
        foreach($result as $metric){
            foreach($metric as $stat){
                if(is_array($stat->data)){
                    $pointName = $stat->name;
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    //$formatPoint = formatBytes2($avg, $options['unit']);
                    $formatPoint['value'] = $avg;
                    $formatPoint['unit'] = "kbps";
                    //echo print_r($formatPoint, true);
                    if($formatPoint['value'] >= $options['critical']){                     
                       echo "CRITICAL: ".$formatPoint['value'].$options['unit']." is greater than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];                       
                    }else{                        
                        echo "OK: ".$formatPoint['value'].$options['unit']." is less than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];
                    }
                }
            }
        }
    }elseif($options['option'] == "eth0" && $options['mquery'] == "rxa" ){
        //$values = getMem($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true)."\n\n";
        foreach($result as $metric){
            foreach($metric as $stat){
                if(is_array($stat->data)){
                    $pointName = $stat->name;
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    //$formatPoint = formatBytes2($avg, $options['unit']);
                    $formatPoint['value'] = $avg;
                    $formatPoint['unit'] = "kbps";
                    //echo print_r($formatPoint, true);
                    if($formatPoint['value'] >= $options['critical']){              
                       echo "CRITICAL: ".$formatPoint['value'].$options['unit']." is greater than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];
                    }else{                        
                        echo "OK: ".$formatPoint['value'].$options['unit']." is less than ".$options['critical'].$options['unit']. " | ".$options['option']."=".$formatPoint['value'].$options['unit'];                        
                    }
                }
            }
        }
    }elseif($options['option'] == "fsystem" && $options['mquery'] == "root" ){
        //$values = getDatastore($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true);
        foreach($result as $metric){
            foreach($metric as $stat){
                if($stat->name == "storage.totalsize.filesystem.root"){
                    $totalSizeKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointTotal = formatBytes2($totalSizeKB, $options['unit']);
                }
                if($stat->name == "storage.used.filesystem.root"){
                    $totalUsedKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointUsed = formatBytes2($totalUsedKB, $options['unit']);
                    $ttlfree = $totalSizeKB - $totalUsedKB;
                    $pused = strval(round($totalUsedKB/$totalSizeKB*100, 2));
                    $formatPoint = formatBytes($ttlfree);
                }
            }
            //echo print_r($formatPointTotal, true);
            //echo print_r($formatPointUsed, true);
        }
        if($pused <= $options['critical']){
            echo "CRITICAL: \"/\" has less than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit'];
        }else{
           echo "OK: \"/\" has more than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit']; 
        }
    }elseif($options['option'] == "fsystem" && $options['mquery'] == "boot" ){
        //$values = getDatastore($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true);
        foreach($result as $metric){
            foreach($metric as $stat){
                if($stat->name == "storage.totalsize.filesystem.boot"){
                    $totalSizeKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointTotal = formatBytes2($totalSizeKB, $options['unit']);
                }
                if($stat->name == "storage.used.filesystem.boot"){
                    $totalUsedKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointUsed = formatBytes2($totalUsedKB, $options['unit']);
                    $ttlfree = $totalSizeKB - $totalUsedKB;
                    $pused = strval(round($totalUsedKB/$totalSizeKB*100, 2));
                    $formatPoint = formatBytes($ttlfree);
                }
            }
            //echo print_r($formatPointTotal, true);
            //echo print_r($formatPointUsed, true);
        }
        if($pused <= $options['critical']){
            echo "CRITICAL: \"/boot\" has less than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit'];
        }else{
           echo "OK: \"/boot\" has more than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit']; 
        }
    }elseif($options['option'] == "fsystem" && $options['mquery'] == "log" ){
        //$values = getDatastore($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true);
        foreach($result as $metric){
            foreach($metric as $stat){
                if($stat->name == "storage.totalsize.filesystem.log"){
                    $totalSizeKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointTotal = formatBytes2($totalSizeKB, $options['unit']);
                }
                if($stat->name == "storage.used.filesystem.log"){
                    $totalUsedKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointUsed = formatBytes2($totalUsedKB, $options['unit']);
                    $ttlfree = $totalSizeKB - $totalUsedKB;
                    $pused = strval(round($totalUsedKB/$totalSizeKB*100, 2));
                    $formatPoint = formatBytes($ttlfree);
                }
            }
            //echo print_r($formatPointTotal, true);
            //echo print_r($formatPointUsed, true);
        }
        if($pused <= $options['critical']){
            echo "CRITICAL: \"/storage/log\" has less than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit'];
        }else{
           echo "OK: \"/storage/log\" has more than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit']; 
        }
    }elseif($options['option'] == "fsystem" && $options['mquery'] == "imagebuilder" ){
        //$values = getDatastore($options);
        $api_session = apiAuth($options);
        $result = apiMonitoringCheck($api_session, $options);
        //echo print_r($result, true);
        foreach($result as $metric){
            foreach($metric as $stat){
                if($stat->name == "storage.totalsize.filesystem.imagebuilder"){
                    $totalSizeKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointTotal = formatBytes2($totalSizeKB, $options['unit']);
                }
                if($stat->name == "storage.used.filesystem.imagebuilder"){
                    $totalUsedKB = round(array_sum($stat->data)/count($stat->data), 2);
                    $formatPointUsed = formatBytes2($totalUsedKB, $options['unit']);
                    $ttlfree = $totalSizeKB - $totalUsedKB;
                    $pused = strval(round($totalUsedKB/$totalSizeKB*100, 2));
                    $formatPoint = formatBytes($ttlfree);
                }
            }
            //echo print_r($formatPointTotal, true);
            //echo print_r($formatPointUsed, true);
        }
        if($pused >= "1" && $pused <= $options['critical']){
            echo "CRITICAL: \"/storage/imagebuilder\" has less than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit'];
        }else{
           echo "OK: \"/storage/imagebuilder\" has more than ".$options['critical']."% free space. Percent Used = ".$pused."% | ".$options['option']."-".$options['mquery']."-TotalSize=".$formatPointTotal['value'].$formatPointTotal['unit']." ".$options['option']."-".$options['mquery']."-TotalUsed=".$formatPointUsed['value'].$formatPointUsed['unit']; 
        }
    }
}

function check_vcsa_monitoring($options) { 
    //Basic Authentication to the Appliance REST API
    $api_session = apiAuth($options);
    $result = apiMonitoringCheck($api_session, $options);
    foreach($result as $metric){
        //echo print_r($metric, TRUE);
        foreach($metric as $stat){
             //echo print_r($stat, TRUE);
             if(is_array($stat->data)){
                $pointName = $stat->name;
                if(strtoupper($pointName) == "NET.RX.ACTIVITY.ETH0"){
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    //$avg = $avg."kbps";
                }elseif(strtoupper($pointName) == "NET.TX.ACTIVITY.ETH0"){
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);
                    //$avg = $avg."kbps";
                }else{
                    $pointCount = count($stat->data);
                    $pointReso = $stat->interval;
                    $avg = round(array_sum($stat->data)/count($stat->data), 2);  
                }
                
                if(strtoupper($pointName) == "MEM.UTIL"){
                    $display = formatBytes($avg);
                    $avg = round($avg / "1024", 2);
                    if($avg >= "1" && $avg <= '1024'){
                        $avg = round($avg / "1024");
                    }
                }elseif(strtoupper($pointName) == "CPU.UTIL"){
                    $display = $avg."%";
                }
             }
        }
    }
    if($avg >= $options['critical']){
        nagios_exit("CRITICAL - ".strtoupper($pointName)." AVERAGE for ".strtoupper($pointReso)." (".$avg.") is greater than ".$options['critical']." | ".$pointName."=".$avg." \n\n", STATUS_CRITICAL);
    }else{
        nagios_exit("OK - ".strtoupper($pointName)." AVERAGE for ".strtoupper($pointReso)." (".$avg.") is less than ".$options['critical']." | ".$pointName."=".$avg." \n\n", STATUS_OK);
    }               
}

//
//AUTHENTICATION
//

function keyRing($options){
    //get lines for username and password from the authfile
    $keys = new SplFileObject($options['authfile'], 'r' );
	$keys->setFlags(SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
	while(!$keys->eof()){
        foreach($keys as $key){
            //Get the first char of the line
            $fchar = mb_substr($key, 0, 1);
            //Ignore all lines starting with the pund (#) sign
            if($fchar != "#"){
                list($authpart,$authvalue) = explode("=", $key);
                if($authpart == "VCENTER"){
                    $mykeys['vcenter'] = $authvalue;
                }elseif($authpart == "USERNAME"){
                    $mykeys['username'] = $authvalue;
                }elseif($authpart == "PASSWORD"){
                    $mykeys['password'] = "$authvalue";
                }else{
                    nagios_exit('UNKNOWN VALUE FROM AUTH FILE!', STATUS_UNKNOWN);
                }
            }
        }
    }
    //return the array for use in authentication
    return $mykeys;
}

function apiAuth($options){
    $mykeys = keyRing($options);
    $usr = $mykeys['username'];
    $pwd = $mykeys['password'];
	$url = 'https://'.$options['hostname'].'/rest/com/vmware/cis/session';
    //Connect to VCSA and authenticate
    //Success will reutrn the session id
    $res = exec('curl -ks -X POST -H \'Accept: application/json\' --basic -u '.$usr.':\''.$pwd.'\' '.$url.'');
    $auth = json_decode($res);
    //Grab the sessionid from the authentication step
    //validate the session and then return the session id or fail and exit
    foreach($auth as $sessionID){
        $code = exec('curl -o /dev/null -w "%{http_code}" -ks -X POST --header \'Content-Type: application/json\' --header "Accept: application/json" --header "vmware-api-session-id: '.$sessionID.'" https://'.$options['hostname'].'/rest/com/vmware/cis/session?~action=get');
        if($code != "200"){
            nagios_exit("CRITICAL - Unable to login to VCSA API, Please Check Credentials and Firewall Rules. \n\n", STATUS_CRITICAL);
        }else{
            return $sessionID;
        }
    }
}

//
// CURL COMMANDS
// WILL BE CONVERYED TO PHP FUNCTION ONCE ALL ARE IDENTIFIED
//
function apiDatastoreListCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/vcenter/datastore 2>/dev/null');
    $res = json_decode($check_result);
    return $res;
}

function apiDatastoreCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/vcenter/datastore 2>/dev/null');
    $res = json_decode($check_result);
    return $res;
}

function apiServiceListCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/appliance/techpreview/services 2>/dev/null');
    $res = json_decode($check_result);
    return $res;
}

function apiMonitoringListCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/appliance/monitoring 2>/dev/null');
    $res = json_decode($check_result);
    return $res;
}

function apiMonitoringCheck($api_session, $options){
    //TODO
    //Date fucntions for start and end date
    //Start time would be now - 10minutes
    //End time would be now
    //For such fine resolution the interval should be set to MINUTE1
    //
    //Parameters for Date
    //Now and -10 minutes in ISO8601
    $now = new DateTime;
    $start = new DateTime('-10min');
    $end = $now->setTimezone(new DateTimeZone('UTC'));
    $begin = $start->setTimezone(new DateTimeZone('UTC'));
    $end_time = $end->format('Y-m-d\TH:i:s\.\0\0\0\Z');
    $start_time = $begin->format('Y-m-d\TH:i:s\.\0\0\0\Z');
    if($options['option'] == 'cpu'){
            $mq = array(
                    "interval" => "MINUTES5",
                    "function" => "AVG",
                    "names" => "cpu.util",
                    "start_time" => $start_time,
                    "end_time" => $end_time
                    );
                    
            $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
            $mquery_data = 'item.interval='.$mq['interval'].
                           '&item.names.1='.$mq['names'].
                           '&item.function='.$mq['function'].
                           '&item.start_time='.$mq['start_time'].
                           '&item.end_time='.$mq['end_time'];
            $query = $mquery_url.$mquery_data;
        }elseif($options['option'] == 'mem'){
            $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names" => "mem.util",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
           $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
           $mquery_data = 'item.interval='.$mq['interval'].
                          '&item.names.1='.$mq['names'].
                          '&item.function='.$mq['function'].
                          '&item.start_time='.$mq['start_time'].
                          '&item.end_time='.$mq['end_time'];
           $query = $mquery_url.$mquery_data;   
        }elseif($options['option'] == 'eth0'){
            if($options['mquery'] != "" && $options['mquery'] == "txa"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names" => "net.tx.activity.eth0",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
            }elseif($options['mquery'] != "" && $options['mquery'] == "rxa"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names" => "net.rx.activity.eth0",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
            }else{
                nagios_exit('NO ETH MQUERY GIVEN TO COLLECT', STATUS_CRITICAL);
            }
        }elseif($options['option'] == "fsystem" && $options['mquery'] == "root"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names1" => "storage.totalsize.filesystem.root",
                   "names2" => "storage.used.filesystem.root",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names1'].
                                '&item.names.2='.$mq['names2'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
        }elseif($options['option'] == "fsystem" && $options['mquery'] == "boot"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names1" => "storage.totalsize.filesystem.boot",
                   "names2" => "storage.used.filesystem.boot",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names1'].
                                '&item.names.2='.$mq['names2'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
        }elseif($options['option'] == "fsystem" && $options['mquery'] == "imagebuilder"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names1" => "storage.totalsize.filesystem.imagebuilder",
                   "names2" => "storage.used.filesystem.imagebuilder",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names1'].
                                '&item.names.2='.$mq['names2'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
        }elseif($options['option'] == "fsystem" && $options['mquery'] == "log"){
                $mq = array(
                   "interval" => "MINUTES5",
                   "function" => "AVG",
                   "names1" => "storage.totalsize.filesystem.log",
                   "names2" => "storage.used.filesystem.log",
                   "start_time" => $start_time,
                   "end_time" => $end_time
                   );
                   
                $mquery_url = 'https://'.$options['hostname'].'/rest/appliance/monitoring/query?';
                $mquery_data = 'item.interval='.$mq['interval'].
                                '&item.names.1='.$mq['names1'].
                                '&item.names.2='.$mq['names2'].
                                '&item.function='.$mq['function'].
                                '&item.start_time='.$mq['start_time'].
                                '&item.end_time='.$mq['end_time'];
                $query = $mquery_url.$mquery_data;   
        }else{
           nagios_exit('NO VALUE GIVEN TO COLLECT', STATUS_UNKNOWN); 
        }
    
    $full_exec = 'curl -ks -X GET --header "Accept: application/json" --header "content-type : application/json" --header "vmware-api-session-id: '.$api_session.'" '.$query.' 2>/dev/null';
    $shell_command = escapeshellcmd($full_exec);
    $check_result = shell_exec($shell_command);
    $res = json_decode($check_result);
    //print print_r($res, TRUE);
    return $res;
}

function apiDatastoreMquery($mq){
$full_exec = 'curl -ks -X GET --header "Accept: application/json" --header "content-type : application/json" --header "vmware-api-session-id: '.$api_session.'" '.$query.' 2>/dev/null';
    $shell_command = escapeshellcmd($full_exec);
    $check_result = shell_exec($shell_command);
    $res = json_decode($check_result);
    //print print_r($res, TRUE);
    return $res;
}

function apiServiceCheck($api_session, $options){
    $check_result = exec('curl -ks -X POST --header "Content-Type: application/json" --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" -d \'{"name":"'.$options['option'].'","timeout":"0" }\' https://'.$options['hostname'].'/rest/appliance/techpreview/services/status/get 2>/dev/null');
    $res = json_decode($check_result);
    foreach($res as $v){
        $status = $v;
    }
    return $status;
}

function apiAccessCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Content-Type: application/json" --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/appliance/access/'.$options['option'].' 2>/dev/null');
    $res = json_decode($check_result);
    foreach($res as $v){
        $status = $v;
    }
    return $status;
}

function apiHealthCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/appliance/'.$options['apicounter'].'/'.$options['option'].' 2>/dev/null');
    $res = json_decode($check_result);
    foreach($res as $v){
        $status = $v;
    }
    return $status;
}

function apiNetworkCheck($api_session, $options){
    $check_result = exec('curl -ks -X GET --header "Accept: application/json" --header "vmware-api-session-id: '.$api_session.'" https://'.$options['hostname'].'/rest/appliance/networking/'.$options['option'].' 2>/dev/null');
    $res = json_decode($check_result);
    return $res;
}

//
//UTILITY FUNCTIONS
//
function formatBytes2($bytes, $unit){
    $arUnits = array(
                    0 => array(
                                "UNIT" => "TB",
                                "VALUE" => pow(1024, 4)
                               ),
                    1 => array(
                                "UNIT" => "GB",
                                "VALUE" => pow(1024, 3)
                               ),
                    2 => array(
                                "UNIT" => "MB",
                                "VALUE" => pow(1024, 2)
                               ),
                    3 => array(
                                "UNIT" => "KB",
                                "VALUE" => 1024
                               ),
                    4 => array(
                                "UNIT" => "B",
                                "VALUE" => 1
                               ),
                   );
    foreach($arUnits as $arUnit){
        if($unit == $arUnit['UNIT']){
            $res = $bytes / $arUnit['VALUE'];
            $result['value'] = strval(round($res, 2));
            $result['unit'] = $arUnit['UNIT'];
            break;
        }
    }
    return $result;
}


function formatBytes($bytes){
    $arUnits = array(
                    0 => array(
                                "UNIT" => "TB",
                                "VALUE" => pow(1024, 4)
                               ),
                    1 => array(
                                "UNIT" => "GB",
                                "VALUE" => pow(1024, 3)
                               ),
                    2 => array(
                                "UNIT" => "MB",
                                "VALUE" => pow(1024, 2)
                               ),
                    3 => array(
                                "UNIT" => "KB",
                                "VALUE" => 1024
                               ),
                    4 => array(
                                "UNIT" => "B",
                                "VALUE" => 1
                               ),
                   );
    foreach($arUnits as $arUnit){
        if($bytes >= $arUnit['VALUE']){
            $result = $bytes / $arUnit['VALUE'];
            $result = str_replace(".",".",strval(round($result, 2)))."".$arUnit['UNIT'];
            break;
        }
    }
    return $result;
}

//
// USAGE - HELP
//
function fullusage() {
print(
	"check-vcsa-mon.php - v".VERSION."

	Usage: ".PROGRAM." -H \"<hostname>\"  -f \"/path/to/authfile\" -a \"<api counter>\" -c \"<critical>\" -o \"<option>\"
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
            

	This plugin will check the condition of Access/Service/Health/Monitoring/Storage Indicatiors for a VCenter Appliance via the REST API.
	Examples:
	     Health
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"health\" -c \"yellow\" -o \"system\" \n\n
         
         Complete list of services
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"servicelist\" \n\n
         
         Service status
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"service\" -c \"up\" -o \"xinitd\" \n\n
         
         Access status
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"access\" -c \"enabled\" -o \"ssh\" \n\n
         
         Datastore List
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"datastorelist\" -c \"\" -o \"\" \n\n
         
         Datastore Free Space
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"monitoring\" -c \"10\" -u \"B,MB,GB,TB\" -o \"datastore\" -m \"free\" \n\n
         
         CPU Load Monitoring
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"monitoring\" -c \"15\" -u \"%\" -o \"cpu\" \n\n
         
         Memory Usage Monitoring
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"monitoring\" -c \"95\" -u \"B,MB,GB,TB\" -o \"mem\" \n\n
         
         File System Usage Monitoring
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"monitoring\" -c \"10\" -u \"MB\" -o \"fsystem\" -m \"root,log,boot\" \n\n
         $/usr/bin/php -q ".PROGRAM." -H \"192.168.1.1\" -f \"/my/path/configs/192.168.1.1.cfg\" -a \"monitoring\" -c \"10\" -u \"KB\" -o \"fsystem\" -m \"imagebuilder\" \n\n
         "
    );
}

main();
?>
