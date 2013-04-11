<?php

namespace OpenCloud;

// Author: Chris Parsons
// This script takes two arguments, an FQDN and an IP address, and creats a new
// A record.  If the domain does not exist, it will create it and then set the
// A record.  If it does exist, it will add the A record to the existing domain.

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

if(sizeof($argv) != 3)
{
    print("Incorrect number of arguments!\n");
    print("Usage: challenge4.php <FQDN> <IP ADDRESS>\n");
    exit();
}

$FQDN = $argv[1];  // the FQDN of the new A record
$IP = $argv[2]; // the IP address of the new A record
$EMAIL = 'chris.parsons@rackspace.com'; // use this email for the domain contact

// include the rackspace library
require('rackspace.php');

// read the ini file and store it in $ini as an array
// ini file is at .rackspace_cloud_credentials and contains:
// [authentication]
// user name: $your_username
// apikey: $your_apikey
$ini = parse_ini_file('.rackspace_cloud_credentials', TRUE);

// get an auth token by passing the username and api key to the auth server
$auth = array('username' => $ini['authentication']['username'],
              'apiKey' => $ini['authentication']['apikey']);
$rsconnect = new Rackspace(RACKSPACE_US, $auth);

// create a handle to cloud DNS
$dns = $rsconnect -> DNS();

// create a list of all domains on the account
$domainlist = $dns -> DomainList();

// strip the prefix from the FQDN to see if "root" domain exists
// if $FQDN is "test.krispy.com", $domainname will be "krispy.com"
$domainname = substr($FQDN, strpos($FQDN, '.') + 1); 
$newdomainname = true;
// see if "root" domain exists
while($domain = $domainlist -> Next())
{
    // if "root" domain found, create a new A record within that domain
    if($domain -> Name() == $domainname)
    {
        print("Adding A record $FQDN ($IP) to existing domain $domainname...\n");
        // create a new record object
        $record = $domain -> Record(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $IP));
        $record -> Create();
        $newdomainname = false;
    }
}

// if we didn't find an existing domain while cycling through the list of domains, create it
if($newdomainname == true)
{
    print("Creating new domain $domainname and adding A record for $IP...\n");
    // create a new domain object
    $domain = $dns -> Domain(array('name' => $domainname,
                                   'emailAddress' => $EMAIL,
                                   'ttl' => 28800));
    // create a new record object
    $record = $domain -> Record(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $IP));
    $domain -> AddRecord($record); 
    $domain -> Create(); 
}

?>