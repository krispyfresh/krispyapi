<?php

namespace OpenCloud;

// Author: Chris Parsons
// This script takes two arguments, an FQDN and an IP address, and creats a new
// A record.  If the domain does not exist, it will create it and then set the
// A record.  If it does exist, it will add the A record to the existing domain.

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$FQDN = $argv[1];  //Upload files from this directory
$IP = $argv[2]; //Upload files to this Cloud Files container
$EMAIL = 'chris.parsons@rackspace.com'; //use this email for the domain contact

//include the rackspace library
require('rackspace.php');

//read the ini file and store it in $ini as an array
//ini file is at .rackspace_cloud_credentials and contains:
//[authentication]
//username: $your_username
//apikey: $your_apikey
$ini = parse_ini_file('.rackspace_cloud_credentials', TRUE);

//get an auth token by passing the username and api key to the auth server
$auth = array('username' => $ini['authentication']['username'],
              'apiKey' => $ini['authentication']['apikey']);
$rsconnect = new Rackspace(RACKSPACE_US, $auth);

//create a handle to cloud DNS
$dns = $rsconnect -> DNS();

//create a list of all domains on the account
$domainlist = $dns -> DomainList();

//strip the prefix from the FQDN to see if "root" domain exists
//if $FQDN is "test.krispy.com", $domainname will be "krispy.com"
$domainname = substr($FQDN,strpos($FQDN,'.') + 1); 
$newdomainname = true;
//see if "root" domain exists
while($domain = $domainlist -> Next())
{
    //if "root" domain found, create a new A record within that domain
    if($domain -> Name() == $domainname)
    {
        print("Adding A record $FQDN [$IP] to existing domain $domainname...\n");
        $record = $domain -> Record();
        $response = $record -> Create(array('type' => 'A',
                                  'name' => $FQDN,
                                  'ttl' => 28800,
                                  'data' => $IP));
        $response -> Waitfor("COMPLETED", 10);
        
        if($response -> Status() == 'ERROR')
        {
            print("Error creating record\n");
            print($response->error->code."\n".$response->error->message."\n".$response->error->details."\n");
        }
        $newdomainname = false;
    }
}

if($newdomainname == true)
{
    print("Creating new domain $FQDN and adding A record for $IP...\n");
    //add a new domain $FQDN
    $domain = $dns -> Domain(array('name' => $FQDN,
                                   'emailAddress' => $EMAIL,
                                   'ttl' => 28800));
    //add a new A record to the new domain
    $record = $domain -> Record(array('type' => 'A',
                                      'name' => $FQDN,
                                      'ttl' => 28800,
                                      'data' => $IP));
    $domain -> AddRecord($record);
    $response = $domain -> Create();
    $response -> WaitFor("COMPLETED",10);
}


?>