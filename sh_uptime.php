<?php
/*
* $jwk$
*
* Lists devices and their uptime. Uptime gained by polling the device since
* Cacti doesn't actually store the uptime in the database.
*
*
* Joel Knight
* joel@isisnetworks.ca
* [2008.04.09]
*/


/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

function usage() {
	print "Usage: " . $_SERVER['SCRIPT_NAME'] . " [-m <seconds>] [-n hostname]\n";
	exit;
}


$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");
include_once($config["base_path"] . "/lib/auth.php");
include_once($config["base_path"] . "/lib/snmp.php");

$opt = getopt("hm:n:");

if (array_key_exists("h", $opt))
	usage();


$sql = "SELECT host.id,
	host.hostname,
	host.status,
	host.disabled,
	host.snmp_community,
	host.snmp_version,
	host.snmp_username,
	host.snmp_password,
	host.snmp_auth_protocol,
	host.snmp_priv_passphrase,
	host.snmp_priv_protocol,
	host.snmp_context,
	host.snmp_port,
	host.snmp_timeout
	FROM host";

if (array_key_exists("n", $opt)) {
	$hostname = $opt["n"];
	$sql .= " WHERE host.hostname LIKE '$hostname'";
}

$sql .=	" ORDER BY host.hostname";

$hosts = db_fetch_assoc($sql);

foreach ($hosts as $host) {
	if ($host["status"] != HOST_UP || !empty($host["disabled"])) {
		continue;
	}
	$uptime = cacti_snmp_get($host["hostname"], $host["snmp_community"],
		".1.3.6.1.2.1.1.3.0", $host["snmp_version"],
		$host["snmp_username"], $host["snmp_password"],
		$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"],
		$host["snmp_priv_protocol"], $host["snmp_context"], $host["snmp_port"],
		$host["snmp_timeout"], read_config_option("snmp_retries"),
		SNMP_CMDPHP);

	if (empty($uptime)) {
		print "ERROR: couldn't poll " . $host["hostname"] . "\n";
		continue;
	}

	$uptime /= 100;
	if (array_key_exists("m", $opt) && $uptime >= $opt["m"])
		continue;

	$days = intval($uptime / (60*60*24));
	$r = $uptime % (60*60*24);
	$hours = intval($r / (60*60));
	$r = $r % (60*60);
	$mins = intval($r / 60);
	printf("%20.20s\t\t%d days %d hours %d minutes\n", $host["hostname"],
		$days, $hours, $mins);

}


?>
