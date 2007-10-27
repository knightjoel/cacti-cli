<?php
/*
  $jwk$

  add_threshold_contact.php

  In Thold 0.3.7 (the version needed for Cacti 0.8.7), threshold alerts
  are sent to a list of addresses which are configured on a per-threshold
  basis; the old behavior of specifying a global list of recipients is
  gone. This script will populate the list of contacts for each configured
  threshold.

  Usage:
  1. Login to Cacti, User Management and add an email address for the admin
     user (or whichever user(s) you want to use as contacts for threshold
     alerts).
  2. Place this script in your cacti/cli/ directory.
  3. Run the script without any options to view the usage help.


  Joel Knight
  <knight.joel@gmail.com>
  2007.10.27 
*/


/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once($config["base_path"] . "/lib/auth.php");

$options = getopt("d:h:u:");

function usage() {
	global $_SERVER;
	$me = $_SERVER["argv"][0];

	print <<<END
Usage: $me [-d <descr>] [-h <hostname>] -u <username>

	<descr> Only add <username> as a contact if the threshold
                belongs to a device with description <descr>.
                Wildcards can be used by using '%'.
        <hostname> Only add <username> as a contact if the threshold
                   belongs to a device with hostname <hostname>.
                   Wildcards can be used by using '%'.
        <username> The Cacti user to add as a contact. Unless -d
                   and/or -h are specified, the user will be added
                   to all thresholds.

END;
	exit(1);
}

if (count($_SERVER["argv"]) < 2)
	usage();

if (gettype($options["u"]) != "string") {
	printf("Please specify exactly one username using the -u switch.\n\n");
	usage();
}

$sql = "SELECT ua.id, ua.username, contacts.data," .
	"contacts.id AS contact_id " .
	"FROM plugin_thold_contacts AS contacts " .
	"INNER JOIN user_auth AS ua ON ua.id = contacts.user_id  " .
	"WHERE ua.username = '$options[u]'";
$user = db_fetch_row($sql);
if (count($user) == 0) {
	printf("Error: User %s does not have an email address.\n", $options["u"]);
	exit(1);
}
printf("Adding %s (%s) to thresholds:\n", $options["u"], $user["data"]);

$where = array();
if (isset($options["d"])) {
	array_push($where, "host.description LIKE '$options[d]'");
}
if (isset($options["h"])) {
	array_push($where, "host.hostname LIKE '$options[h]'");
}
if (count($where)) {
	$where_sql = sprintf("WHERE %s", join(" AND ", $where));
} else {
	$where_sql = "";
}

$sql = "SELECT thold.id AS thold_id, dtd.name_cache, dtr.data_source_name " .
	"FROM thold_data AS thold ".
	"INNER JOIN data_template_data AS dtd ON thold.rra_id = dtd.local_data_id " .
	"INNER JOIN data_template_rrd AS dtr ON thold.data_id = dtr.id " .
	"INNER JOIN data_local AS dl ON dtd.local_data_id = dl.id " .
	"INNER JOIN host ON host.id = dl.host_id " .
	$where_sql .
	"ORDER BY dtd.name_cache ASC"; 
$thresholds = db_fetch_assoc($sql);
foreach ($thresholds as $th) {
	printf("\t%s [%s]\n", $th["name_cache"], $th["data_source_name"]);
	$sql = "DELETE FROM plugin_thold_threshold_contact " .
		"WHERE thold_id = $th[thold_id] " .
		"AND contact_id = $user[contact_id]";
	db_execute($sql);
	$sql = "INSERT INTO plugin_thold_threshold_contact " .
		"VALUES ($th[thold_id], $user[contact_id])";
	db_execute($sql);
}

?>
