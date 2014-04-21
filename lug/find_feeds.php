<?php

/*
	SimplePie non gestisce i redirect, dunque questa funzione viene usata
	come filtro per identificare il vero URL di ogni pagina. Prima fa un
	controllo sui redirect HTTP, se non ne trova prova ad identificare dei
	tag META REFRESH nel codice HTML

	Fonti:
	http://stackoverflow.com/questions/427203/how-can-i-determine-if-a-url-redirects-in-php
	http://www.phpclasses.org/package/6317-PHP-Check-and-retrieve-the-redirection-URL-of-a-page.html
*/
function check_url ($url) {
	$ch = curl_init ($url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
	curl_exec ($ch);
	$code = curl_getinfo ($ch, CURLINFO_HTTP_CODE);

	if (($code == 301) || ($code == 302)) {
		$ch2 = curl_init ($url);
		curl_setopt ($ch2, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt ($ch2, CURLOPT_RETURNTRANSFER, true);
		curl_exec ($ch2);
		$url = curl_getinfo ($ch2, CURLINFO_EFFECTIVE_URL);
		curl_close ($ch2);
	}
	else {
		$keys = array ();
		$open = @file_get_contents ($url);

		if (preg_match ('/<META HTTP-EQUIV="Refresh" CONTENT="(.*);URL=(.[^"]*)">/i', $open, $keys)) {
			if (strncmp ('http://', $keys [2], 7) == 0 || strncmp ('https://', $keys [2], 8) == 0)
				$url = $keys [2];
			else
				$url = $url . $keys [2];
		}
	} 

	return $url;
}

// Richiede SimplePie!
// http://simplepie.org/
include_once ('/usr/share/php/simplepie/simplepie.inc');

$elenco_regioni = array (
	"abruzzo"    => "Abruzzo",
	"basilicata" => "Basilicata",
	"calabria"   => "Calabria",
	"campania"   => "Campania",
	"emilia"     => "Emilia Romagna",
	"friuli"     => "Friuli Venezia Giulia",
	"lazio"      => "Lazio",
	"liguria"    => "Liguria",
	"lombardia"  => "Lombardia",
	"marche"     => "Marche",
	"molise"     => "Molise",
	"piemonte"   => "Piemonte",
	"puglia"     => "Puglia",
	"sardegna"   => "Sardegna",
	"sicilia"    => "Sicilia",
	"toscana"    => "Toscana",
	"trentino"   => "Trentino Alto Adige",
	"umbria"     => "Umbria",
	"valle"      => "Valle d'Aosta",
	"veneto"     => "Veneto",
	"Italia"     => "Italia"
);

$exceptions = array ();

$exceptions_file = file ('http://raw.github.com/Gelma/LugMap/lugmap.it/forge/opml-generator/eccezioni.txt', FILE_IGNORE_NEW_LINES);
if ($exceptions_file != false) {
	foreach ($exceptions_file as $ex) {
		if ($ex [0] == '#')
			continue;
		$exceptions [] = $ex;
	}
}

$feeds = array ();

foreach ($elenco_regioni as $region => $name) {
	$lugs = file ('https://raw.github.com/Gelma/LugMap/master/db/' . $region . '.txt', FILE_IGNORE_NEW_LINES);

	foreach ($lugs as $lug) {
		list ($prov, $name, $zone, $site) = explode ('|', $lug);
		$site = check_url ($site);

		$parser = new SimplePie ();
		$parser->set_feed_url ($site);
		$parser->set_autodiscovery_level (SIMPLEPIE_LOCATOR_AUTODISCOVERY);
		$parser->init ();
		$parser->handle_content_type ();
		if ($parser->error ())
			continue;

		$discovered = $parser->get_all_discovered_feeds ();

		foreach ($discovered as $f) {
			$skip = false;
			$f->url = str_replace ('&', '&amp;', str_replace ('&amp;', '&', $f->url));

			foreach ($exceptions as $exception) {
				if ($f->url == $exception) {
					$skip = true;
					break;
				}
			}

			if ($skip == true)
				continue;

			$obj = new stdClass ();
			$obj->name = $name;
			$obj->feed = $f->url;
			$feeds [] = $obj;
			break;
		}
	}
}

$output =<<<PLANET
# Planet configuration file
#
# This illustrates some of Planet's fancier features with example.

# Every planet needs a [Planet] section
[Planet]
# name: Your planet's name
# link: Link to the main page
# owner_name: Your name
# owner_email: Your e-mail address
name = Planet LUG
link = http://lug.linux.it/
owner_name = Carlo Perassi
owner_email = carlo@linux.it

# cache_directory: Where cached feeds are stored
# new_feed_items: Number of items to take from new feeds
# log_level: One of DEBUG, INFO, WARNING, ERROR or CRITICAL
# feed_timeout: number of seconds to wait for any given feed
cache_directory = lug/cache
new_feed_items = 3
log_level = INFO
feed_timeout = 20

# template_files: Space-separated list of output template files
template_files = lug/index.html.tmpl lug/atom.xml.tmpl lug/rss20.xml.tmpl lug/rss10.xml.tmpl lug/opml.xml.tmpl lug/foafroll.xml.tmpl

# The following provide defaults for each template:
# output_dir: Directory to place output files
# items_per_page: How many items to put on each page
# days_per_page: How many complete days of posts to put on each page
#                This is the absolute, hard limit (over the item limit)
# date_format: strftime format for the default 'date' template variable
# new_date_format: strftime format for the 'new_date' template variable
# encoding: output encoding for the file, Python 2.3+ users can use the
#           special "xml" value to output ASCII with XML character references
# locale: locale to use for (e.g.) strings in dates, default is taken from your
#         system. You can specify more locales separated by ':', planet will
#         use the first available one
output_dir = /var/www/planet/lug/
items_per_page = 60
days_per_page = 0
date_format = %d/%m/%Y %I:%M %p
new_date_format = %d/%m/%Y
encoding = utf-8
# locale = C


# To define a different value for a particular template you may create
# a section with the same name as the template file's filename (as given
# in template_files).

# Provide no more than 7 days articles on the front page
[lug/index.html.tmpl]
days_per_page = 7

# If non-zero, all feeds which have not been updated in the indicated
# number of days will be marked as inactive
activity_threshold = 0


# Options placed in the [DEFAULT] section provide defaults for the feed
# sections.  Placing a default here means you only need to override the
# special cases later.
[DEFAULT]
# Hackergotchi default size.
# If we want to put a face alongside a feed, and it's this size, we
# can omit these variables.
facewidth = 65
faceheight = 85

PLANET;

foreach ($feeds as $f)
	$output .= "[" . $f->feed . "]\nname = " . $f->name . "\n\n";

file_put_contents ('config.ini', $output);
