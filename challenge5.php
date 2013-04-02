<?php

namespace OpenCloud;

// Author: Chris Parsons

//include the lib directory in the working path
ini_set('include_path','./lib:'.ini_get(include_path));

$INSTANCENAME = 'TEST'; //Upload files from this directory
$DBNAME = 'KripsyDB';
$USERNAME = 'krispy'; //Upload files to this Cloud Files container
$USERPASS = 'Rackspace1';

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

$clouddb = $rsconnect -> DbService('cloudDatabases', 'DFW');

$flavorlist = $clouddb -> FlavorList();

$i = 1;
while($flavor = $flavorlist -> Next())
{
    print("[$i]");
    print($flavor -> Name()."\n");
    $i++;
}
print("Please choose your flavor and hit ENTER: \n");
$flavor = fgets(STDIN);

$instance = $clouddb -> Instance();
$instance -> name = $INSTANCENAME;
$instance -> flavor = $clouddb -> Flavor($flavor);
$instance -> volume -> size = 1;
$instance -> Create();
$instance -> WaitFor('ACTIVE', 300);
//sleep(120);
$database = $instance -> Database();
$database -> Create(array('name' => $DBNAME));

$username = $instance -> User();
$username -> AddDatabase($DBNAME);
$username -> Create(array('user' => $USERNAME,
                          'password' => $USERPASS));

?>