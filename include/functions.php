<?php
/*
		      dotpointers _      _          _
	  ___ _ __ ___  _   _| | ___| |__   ___| |_ __   ___ _ __
	 / _ \ '_ ` _ \| | | | |/ _ \ '_ \ / _ \ | '_ \ / _ \ '__|
	|  __/ | | | | | |_| | |  __/ | | |  __/ | |_) |  __/ |
	 \___|_| |_| |_|\__,_|_|\___|_| |_|\___|_| .__/ \___|_|
		                                     |_|

	# note: eMule seems to use uppercased ed2k-hashes, so this script uses uppercase.

	# changelog
	2011-07-10 			- Taking indexing code from Kreosot
	2011-07-12 18:30	-
	2011-07-12 19:53	- Building simple admin control
	2012-05-31 22:59:00 - investigating move-downloaded-files for possible extension to move mp3:s
	2012-06-22 00:29:21 - adding move rules
	2012-11-23 22:25:40 - updating interface with toggler
	2013-07-01			- trying to improve
	2013-07-17 22:30:43
	2013-09-25 21:17:52 - PHP5.5, correcting for mysqli, base1
	2013-09-27 01:31	- correcting for base1, mysql_result_as_array
	2013-10-22 			- adding pingtest from transfer
	2014-02-02 			- adding ed2khash-checking and regex-file-renamings in in move_file, also enabling utf8 filesystem usage
	2014-05-01 			- bugfix, when filenames in downloaddir cannot be escaped for some reason
	2014-05-08 			- bugfix, when hashes for filenames in downloaddir cannot be escaped for some reason, what is this all about?
	2014-07-12 17:03:00 - v2
	2014-07-22 			- multi-client with clientpumps, search with name+size check
	2014-07-23 19:33:36 - v3, probably many bugs but we must get this bastard out in the free
	2014-10-30 00:49:45 - adding config.php
	2015-03-10 22:10:00 - replacing filesize and ed2khash with _custom editions for 32-bit 2GB PHP file size limit
	2015-07-15 12:12:09 - adding shutdown function to close database connection properly
	2015-07-24 23:24:00 - bugfix for include bug where php~ pump files were included
	2015-07-25 00:05:50
	2015-07-27 03:24:16 - adding mailer string
	2015-07-27 17:17:44 - adding fakecheck_file
	2015-07-27 18:38:08 - adding deep debug level for SQL and execs
	2015-08-03 23:17:30 - bugfix for fakechecking in move function which used the source path instead of the target path
	2015-08-07 13:03:00 - bugfix where file array was confused with search array and email was not used
	2015-08-07 16:12:00 - bugfix for syntax error
	2015-08-14 14:21:00 - bugfix for notify email array position
	2015-08-16 10:25:23 - adding logmessage functions from Kreosot
	2015-08-18 00:10:13 - adding log messages for discovery, download and relocations
	2015-08-20 20:13:00
	2015-08-21 21:50:00 - adding file and line to errors
	2016-02-23 13:40:53 - replacing email code with new code summarizing with timeout possibility to reduce email amount
	2016-03-03 16:45:52 - adding identify collection to identify collections when moving files
	2016-03-05 22:17:53 - cleanup
	2016-09-22 22:59:19 - base 2 to base 3
	2016-09-22 23:05:38
	2016-11-08 23:33:41 - bugfix, base 2 to base 3 leftovers
	2017-02-25 21:50:05 - editing mailer to use php mail function
	2017-04-07 19:01:31 - bugfix, invalid id in move rules and leftovers from mailer
	2017-04-07 22:33:23 - bugfix, leftovers from mailer

	# SQL setup
	CREATE DATABASE emulehelper;
	USE emulehelper;

	CREATE TABLE clientpumps(id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, username TINYTEXT NOT NULL, password TINYTEXT NOT NULL, host TINYTEXT NOT NULL, port INT NOT NULL, `type` TINYTEXT NOT NULL, status int not null default 1, searched datetime not null default '0000-00-00 00:00:00', searches BIGINT NOT NULL DEFAULT 0, queuedfiles BIGINT NOT NULL DEFAULT 0, path_incoming TEXT NOT NULL DEFAULT '', updated DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', created DATETIME NOT NULL);
	CREATE TABLE collections(id BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT, host TEXT NOT NULL, hostpath TEXT NOT NULL, username TEXT NOT NULL, password TEXT NOT NULL, rootpath TEXT NOT NULL,  enabled int not null default 1, updated DATETIME NOT NULL, created DATETIME NOT NULL);
	CREATE TABLE files (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, id_collections bigint not null default 0, id_searches INT NOT NULL DEFAULT 0, name TINYTEXT NOT NULL, path TINYTEXT NOT NULL, ed2khash VARCHAR(32) NOT NULL, size BIGINT NOT NULL DEFAULT 0, verified INT NOT NULL DEFAULT 1, existing INT NOT NULL DEFAULT 0, fakecheck INT NOT NULL, moved INT NOT NULL DEFAULT 0, modified DATETIME NOT NULL, created DATETIME NOT NULL, updated DATETIME NOT NULL DEFAULT 0);
	CREATE TABLE logmessages (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, id_logmessages_parent BIGINT NOT NULL DEFAULT  0, id_files INT NOT NULL DEFAULT 0, type INT NOT NULL DEFAULT 0, message TEXT NOT NULL, updated DATETIME NOT NULL, created DATETIME NOT NULL);
	CREATE TABLE moverules(id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, regex TEXT NOT NULL, movetopath TEXT NOT NULL, movetochgrp TINYTEXT NOT NULL, movetochmod VARCHAR(4) NOT NULL, matches INT NOT NULL DEFAULT 0, filessincelastmail INT NOT NULL DEFAULT 0, status INT NOT NULL DEFAULT 1);
	CREATE TABLE parameters(id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, parameter TINYTEXT NOT NULL, value TEXT NOT NULL);
	CREATE TABLE searches (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT, id_clientpumps INT NOT NULL DEFAULT 0, search TINYTEXT NOT NULL, type TINYTEXT NOT NULL, sizemin BIGINT NOT NULL DEFAULT 0, sizemax BIGINT NOT NULL DEFAULT 0, extension TINYTEXT NOT NULL, method TINYTEXT NOT NULL, executiontimeout BIGINT NOT NULL DEFAULT 3600, executiontimeoutbase BIGINT NOT NULL DEFAULT 3600, executiontimeoutrandbase BIGINT NOT NULL DEFAULT 3600, status int not null default 0, executions BIGINT NOT NULL DEFAULT 0, resultscans INT NOT NULL DEFAULT 0, queuedfiles INT NOT NULL DEFAULT 0, filessincelastmail INT NOT NULL DEFAULT 0, movetopath TEXT NOT NULL, movetochgrp TINYTEXT NOT NULL, movetochmod VARCHAR(4) NOT NULL, executed DATETIME NOT NULL, updated DATETIME NOT NULL, created DATETIME NOT NULL);

	CREATE INDEX ed2khash ON files(ed2khash(32));

	
*/

	# database setup
	# define('DATABASE_HOST', 'localhost');
	# define('DATABASE_USERNAME', 'root');
	# define('DATABASE_PASSWORD', '');
	define('DATABASE_NAME', 'emulehelper');

	# file to store eMule web session id in, must be writable
	define('FILE_SESSION', '/var/cache/emulehelper.emule.session');

	# tree root where to mount samba shares - ends with a slash
	define('MOUNT_ROOTPATH', '/mnt/emulehelper/');

	# eMule setup - eMule MUST have special XML-template loaded as web interface
	# define('EMULEHOST', 'hostname');
	# define('EMULEWEBURL', 'http://'.EMULEHOST.':4711/');

   # path to a suitable mailing application, set to false to disable
   # not used in favour of php mail function
   # define('MAILER', '/var/scripts/sysmail ###EMAIL### ###SUBJECT### ###BODY###');

	# bad words detected in filenames
	$blacklist = array('preteen', 'child', 'underage', '.exe','.zip', '.com', '.rar', 'kids', 'stargate');

	# filetypes to index in lowercase
	$indexable_file_endings = array(
		'avi',
		'divx',
		'gif',
		'jpeg',
		'jpg',
		'mov',
		'mp4',
		'mpeg',
		'mpg',
		'png',
		'ram',
		'rm',
		'rv',
		'tif',
		'tiff',
		'wmv',
		'xvid'
	);

	# setup the locale
	setlocale(LC_ALL, 'sv_SE.utf8');
	setlocale(LC_NUMERIC, 'en_US.utf8'); # overwrite decimal separator

	# --- end of setup ---------------------------------------------------

	# file movement statuses
	define('FILES_MOVED_NOT_MOVED', 0);
	define('FILES_MOVED_OK', 1);
	define('FILES_MOVED_SOURCE_UNEXISTENT', -1);
	define('FILES_MOVED_TARGET_EXISTS', -2);
	define('FILES_MOVED_FAILED', -3);

	# search statuses
	define('SEARCHES_STATUS_ACTIVE', 1);
	define('SEARCHES_STATUS_INACTIVE', 0);
	define('SEARCHES_STATUS_DELETED', -1);

	# client pump statuses
	# search statuses
	define('CLIENTPUMPS_STATUS_ACTIVE', 1);
	define('CLIENTPUMPS_STATUS_INACTIVE', 0);
	define('CLIENTPUMPS_STATUS_DELETED', -1);

	define('FILES_ID_COLLECTIONS_NO_COLLECTION', 0);
	define('FILES_ID_COLLECTIONS_DOWNLOAD', -1);
	define('FILES_ID_COLLECTIONS_DUMPED', -2);


	# logmessage types, derived from Kreosot
	define('LOGMESSAGE_TYPE_TEXT', 0);
	define('LOGMESSAGE_TYPE_SOURCE_DISCOVERED', 1);
	define('LOGMESSAGE_TYPE_RELOCATION',2);
	define('LOGMESSAGE_TYPE_FILE_SIZE', 3);
	define('LOGMESSAGE_TYPE_MODIFICATION_TIME', 4);
	define('LOGMESSAGE_TYPE_ED2K_REHASH', 5);
	define('LOGMESSAGE_TYPE_INDEXING_BEGIN', 6);
	define('LOGMESSAGE_TYPE_INDEXING_END', 7);
	define('LOGMESSAGE_TYPE_MERGE_RELOCATION', 8);	# when a merge when file has been moved occours
	define('LOGMESSAGE_TYPE_MERGE_COPY', 9);	# when a merge with a copy file occours
	define('LOGMESSAGE_TYPE_BITRATE_SAMPLERATE_CHANGE', 10); # when bitrate / samplerate change occours
	define('LOGMESSAGE_TYPE_DOWNLOAD_REQUESTED', 11);
	define('LOGMESSAGE_TYPE_INDEXED_COLLECTION', 12);
	define('LOGMESSAGE_TYPE_EMAIL_SENT',13); # when an email is dispatched
	define('LOGMESSAGE_TYPE_ERROR_MYSQL', -1);

	$logmessage_type_descriptions = array(
		LOGMESSAGE_TYPE_BITRATE_SAMPLERATE_CHANGE => 'Bitrate- eller samplerate-förändring',
		LOGMESSAGE_TYPE_DOWNLOAD_REQUESTED => 'Download is requested.',
		LOGMESSAGE_TYPE_ED2K_REHASH => 'ED2K hash change',
		LOGMESSAGE_TYPE_EMAIL_SENT => 'Email sent.',
		LOGMESSAGE_TYPE_ERROR_MYSQL => 'MySQL database error',
		LOGMESSAGE_TYPE_FILE_SIZE => 'File size change',
		LOGMESSAGE_TYPE_MERGE_COPY => 'Merge due to copy',
		LOGMESSAGE_TYPE_MERGE_RELOCATION => 'Merge due to source file reloation',
		LOGMESSAGE_TYPE_MODIFICATION_TIME => 'Modification date change',
		LOGMESSAGE_TYPE_RELOCATION => 'Source file change',
		LOGMESSAGE_TYPE_SOURCE_DISCOVERED => 'Source file discovered',
		LOGMESSAGE_TYPE_TEXT => 'Text'
	);

	$logmessage_type_descriptions_short = array(
		LOGMESSAGE_TYPE_BITRATE_SAMPLERATE_CHANGE => 'Bitrate change',
		LOGMESSAGE_TYPE_DOWNLOAD_REQUESTED => 'Downloading.',
		LOGMESSAGE_TYPE_ED2K_REHASH => 'ED2K',
		LOGMESSAGE_TYPE_EMAIL_SENT => 'E-mail sent',
		LOGMESSAGE_TYPE_ERROR_MYSQL => 'MySQL-error',
		LOGMESSAGE_TYPE_FILE_SIZE => 'Size',
		LOGMESSAGE_TYPE_INDEXED_COLLECTION => 'Indexed',
		LOGMESSAGE_TYPE_INDEXING_BEGIN => 'Index begin',
		LOGMESSAGE_TYPE_INDEXING_END => 'Index end',
		LOGMESSAGE_TYPE_MERGE_COPY => 'Copy-merge',
		LOGMESSAGE_TYPE_MERGE_RELOCATION => 'Relocation-merge',
		LOGMESSAGE_TYPE_MODIFICATION_TIME => 'Date',
		LOGMESSAGE_TYPE_RELOCATION => 'Moved',
		LOGMESSAGE_TYPE_SOURCE_DISCOVERED => 'Source found',
		LOGMESSAGE_TYPE_TEXT => 'Text'
	);


	# time definitions
	define('TIME_ONE_SECOND', 1);
	define('TIME_ONE_MINUTE',		TIME_ONE_SECOND * 60);
	define('TIME_THIRTY_MINUTES',	TIME_ONE_MINUTE * 30);
	define('TIME_ONE_HOUR',			TIME_ONE_MINUTE * 60);
	define('TIME_ONE_DAY',			TIME_ONE_HOUR * 24);

	# verbosity constants
	define('VERBOSE_OFF', 0);
	define('VERBOSE_ERROR', 1);
	define('VERBOSE_INFO', 2);
	define('VERBOSE_DEBUG', 3);
	define('VERBOSE_DEBUG_DEEP', 4);

	$conn = false;
	$loglevel = VERBOSE_INFO;
	$pingresults = array(); # to store ping results
	$verbose = VERBOSE_ERROR; # level of verbosity, 0=off, 1=errors, 2=info, 3=debug, 4=debug deep

	# get common functionality
	require_once('config.php');
	require_once('base3.php');

	# eMule-defined search types - shortened
	$types = array(
		'Arc'	=> 'Archive',
		'Audio'	=> 'Audio',
		'Iso'	=> 'Disk image',
		'Doc'	=> 'Document',
		'Image'	=> 'Image',
		'Pro'	=> 'Program',
		'Video'	=> 'Video'
	);

	# eMule-defined server methods
	$methods = array(
		'global'	=> 'Global',
		'server'	=> 'Server',
		'kademlia'	=> 'Kademlia'
	);

	# get the database connection
	$link = db_connect();
	if (!$link) {
		cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
		die();
	}

	# a function to run when the script shutdown
	function shutdown_function($link) {
		if ($link) {
			db_close($link);
		}
	}

	# register a shutdown function
	register_shutdown_function('shutdown_function', $link);

	# set the database charset
	# mysql_set_charset('utf8', $link);

	# --- start of pumps preparation ---------------------------------------------------------------------------------------

	# scan for available pump classes
	$clientpumpclasses = array();
	$pumpdir = substr(__FILE__, 0, strrpos(__FILE__, '/') + 1).'pumps/';
	foreach (scandir($pumpdir) as $file) {

		$fullpath = $pumpdir.$file;

		# not a file or not a pump class
		if (!is_file($fullpath) || substr($file, -15) !== '.pump.class.php') continue;

		# get file contents
		$c = file_get_contents($fullpath);
		if (!$c) continue;

		# try to find first matching pump class inside file
		if (!preg_match('/class\s+([a-zA-Z0-9]+Pump)\s*\{/', $c, $m)) continue;
		if (!isset($m[1])) continue;

		# include the file, making the class available
		require_once($fullpath);

		# take filename as the identification and note the class behind it
		$clientpumpclasses[ substr($file, 0, strrpos($file, '.pump.class.php')) ] = $m[1];
	}

	$clientpumps = array();

	# get available pumps
	$sql = 'SELECT * FROM clientpumps';
	$result = db_query($link, $sql);
	foreach ($result as $clientpump) {
		if (!isset($clientpumpclasses[$clientpump['type']])) {
			die('Missing class for '.$clientpump['type']);
		}

		# make an object for it
		$clientpumps[$clientpump['type'].'@'.$clientpump['host']] = array(
			'data' => $clientpump,
			'pump' => new $clientpumpclasses[$clientpump['type']](array(
				'username'	=> $clientpump['username'],
				'password'	=> $clientpump['password'],
				'host'		=> $clientpump['host'],
				'port'		=> $clientpump['port']
			))
		);

	}

	# --- functions ------------------------------------------------------------------

	# as PHP:s built-in array_merge does not do a real merge with overwriting of int-keys, we do it ourself
	function array_merge_keep_keys(/* dynamic */) {
		$result = array();
		foreach (func_get_args() as $arg) {
			if (!is_array($arg)) continue;
			foreach ($arg as $k => $v) {
				$result[$k] = $v;
			}
		}
		return $result;
	}

	# to quickly cast data
	function caster($data, $ints=array(), $floats=array()) {

		# walk data col by col
		foreach ($data as $k => $v) {
			# is this an integer?
			if (in_array($k, $ints)) {
				# make it a true int
				$data[$k] = (int)$data[$k];
			# is this a float?
			} else if (in_array($k, $floats)) {
				# make it a true float
				$data[$k] = (float)$data[$k];
			}
		}

		return $data;
	}

	# console log - debug printing
	function cl($s, $level=1, $log_to_db=true) {
		global $verbose;
		global $loglevel;
		global $link;

		# do not log passwords from mountcifs
		$s = preg_replace('/password=\".*\" \"\/\//', 'password="*****" "//', $s);

		# find out level of verbosity
		switch ($level) {
		case VERBOSE_ERROR:
			$l = 'E';
			break;
		case VERBOSE_INFO:
			$l = 'I';
			break;
		case VERBOSE_DEBUG:
		case VERBOSE_DEBUG_DEEP:
			$l = 'D';
			break;
		}

		# is verbosity on and level is enough?
		if ($verbose && $verbose >= $level) echo '['.date('Y-m-d H:i:s').' '.$l.'] '.$s."\n";

		if ($log_to_db && $loglevel && $loglevel >= $level) {
			$sql = 'INSERT INTO logmessages (type, data, updated, created) VALUES('.LOGMESSAGE_TYPE_TEXT.', "'.dbres($link, $s).'","'.date('Y-m-d H:i:s').'","'.date('Y-m-d H:i:s').'")';
			# no logging here - endless loop
			$result = db_query($link, $sql);
		}

		return true;
	}

	# to log a message - taken from Kreosot
	function logmessage($link, $type=LOGMESSAGE_TYPE_TEXT, $data='', $created=false, $id_files=0) {

		# created is not set
		if ($created === false) {
			$created = date('Y-m-d H:i:s', time());
		# created is a timestamp
		} else if (is_numeric($created)) {
			$created = date('Y-m-d H:i:s', (int)$created);
		}

		# data is an array
		if (is_array($data)) {
			$data = json_encode($data);
		}

		# prepare insert array
		$logmessage = dbpia($link, array(
			'created' => $created,
			'data' => $data,
			'id_files' => $id_files,
			'type' => $type
		));

		# run this query
		$sql = 'INSERT INTO logmessages ('.implode(',', array_keys($logmessage)).') VALUES('.implode(',',$logmessage).')';
		$logmessage_insert = db_query($link, $sql);
		if ($logmessage_insert === false) {
			cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
		}

		# return the id to the log message
		return db_insert_id($link);
	}


	# --- cURL:ing ----------------------------------------------------------------------

	function curl_try_get_connection() {

		global $pingresults;

		# try to do a pingtest
		if (!count($pingresults)) {
			# die silently
			if(!pingtest(EMULEHOST)) {
				cl('eMule host '.EMULEHOST.' is offline'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}
		}
		return curl_get_connection();
	}

	# list dir without . and ..
	function dirlist($dir) {

		$dirlist = scandir($dir);
		if (!$dirlist) return false;

		$tmp = array();
		# remove . and ..
		foreach ($dirlist as $k => $v) {
			if ($v === '.' || $v === '..') continue;
			$tmp[] = $v;
		}

		return $tmp;
	}

	# to check a file for fakes, returns true if ok, scan the first x bytes for nulls
	function fakecheck_file($file) {

		$f = fopen($file, 'r');
		if (!$f) {
			die('Failed opening file: '.$file."\n");
		}

		$ok = false;
		$chars_read = 0;
		# walk file while not at the end and we have not read more than x chars
		while (!feof($f) && $chars_read < 500) {
			$chars_read++;
			$c = fgetc($f);
			if ($c === false) {
				# close file
				fclose($f);
				# end here
				die('Failed reading from file: '.$c);
			}

			# if zero detected - go next
			if ($c === chr(0)) continue;

			# close file
			fclose($f);

			# not a fake
			return true;
		}

		# file scanned, seems to be fake
		return false;
	}

	# to get a filelist of files to move
	function get_filelist() {

		global $clientpumps;

		$totalfiles = array();

		foreach ($clientpumps as $pumpname => $pump) {

			# is this pump not active?
			if (!(int)$pump['data']['status']) {
				cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') is inactive, skipping', VERBOSE_DEBUG);
				continue;
			}

			# is the incoming dir not available?
			if (!strlen($pump['data']['path_incoming']) || !file_exists($pump['data']['path_incoming']) || !is_dir($pump['data']['path_incoming'])) {
				cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') does not have a valid incoming path, skipping', VERBOSE_DEBUG);
				continue;
			}

			# make sure path ends with a /
			$dirpath = substr($pump['data']['path_incoming'], -1) === '/' ? $pump['data']['path_incoming'] : $pump['data']['path_incoming'].'/';

			cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') has path '.$dirpath, VERBOSE_DEBUG);

			# get the files in the download directory
			$files = scandir($dirpath);
			if (!is_array($files)) {
				return false;
			}

			$tmp = [];
			# walk the files in the download directory and clean out those not good
			foreach($files as $key => $item) {

				# . or ..
				if (in_array($item, array('.', '..'))) {
					# unset($files[$key]);
					continue;
				}

				# get full path to file
				$fullsourcepath = $dirpath.$item;

				cl('File: '.$fullsourcepath, VERBOSE_DEBUG);

				# file does not exist, kick out
				if (!file_exists($fullsourcepath)) {
					cl('Ignored, does not exist', VERBOSE_DEBUG);
					# unset($files[$key]);
					continue;
				}

				# not a file?
				# note, is_file fails on a file bigger than 2GB due to 32-bit limits?
				if (is_link($fullsourcepath) || is_dir($fullsourcepath)) {
					cl('Ignored, not a file', VERBOSE_DEBUG);
					# unset($files[$key]);
					continue;
				}

				$tmp[] = array(
					'fullpath' => $fullsourcepath,
					'id_clientpumps' => $pump['data']['id'],
					'size' => filesize_custom($fullsourcepath)
				);
			}
			$totalfiles = array_merge($totalfiles, $tmp);

		}

		return $totalfiles;
	}

	# check if a dir is mountpoint ready
	function is_mountpoint($path) {
		$cmd = 'mountpoint -q '.$path;
		cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
		exec($cmd, $output, $retval);
		return  ($retval == 0);
	}

	function make_dir($path) {
		cl('Making dir '.$path, VERBOSE_DEBUG);
		if (is_dir($path) || mkdir($path)) {
			$path = substr($path, -1) !== '/' ? $path.'/' : $path;
			return $path;
		} else {
			return false;
		}
	}

	# to change collection of files that has been downloaded, but skipped and does not exist
	function mark_dumped_files($c, $link_unused, $ses) {

		# this only works with eMule which can provide a list of downloads with:
		# - files completed
		# - ed2k-hashes in transfer list
		return true;
	/*
		global $link;

		# request transfer list from eMule
		cl('Requesting transfer list', VERBOSE_DEBUG);
		$r = curl_do($c, array(CURLOPT_URL => EMULEWEBURL.'?'.http_build_query(array(
			'ses' => $ses,
			'w' => 'transfer'
		))));
		$r = parse_response($r); # convert to XML

		# if it failed, end here
		if ($r['status'] !== 'ok' || !isset($r['filelist'], $r['filelist']['file'])) {
			cl('Request failed'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			die();
		}

		# walk files in the download list and collect the hashes
		$hashes = array();
		cl('Walking files to collect hashes', VERBOSE_DEBUG);
		foreach ($r['filelist']['file'] as $k => $v) {
			$hashes[] = '"'.dbres($link, $v['ed2khash']).'"';
		}

		# get files that has not been moved, belongs to the download collection, does not exist and is not in download directory
		$sql = 'UPDATE files
				SET id_collections="'.dbres($link, FILES_ID_COLLECTIONS_DUMPED).'"
			WHERE
				moved=0 AND
				id_collections="'.dbres($link, FILES_ID_COLLECTIONS_DOWNLOAD).'" AND
				existing=0 AND
				ed2khash NOT IN ('.implode($hashes, ",").')

			';
		cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
		$r = db_query($link, $sql);
		if ($r === false) {
			cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			die();
		}

		return true;
		*/
	}

	# samba-mount something
	function mountcifs($parsed_url, $mountpath, $options=array()) {

		# make sure options is an array
		$options = is_array($options) ? $options : array($options);

		# job sourcepath
		if (!($newpath = make_dir($mountpath))) {
			cl('Path '.$mountpath.' could not be created or is not a directory'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}
		$mountpath = $newpath;

		if (is_mountpoint($mountpath) && !umount($mountpath)) {
			cl('Path '.$mountpath.' has something mounted, and could not unmount it'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}

		# command: mount -t cifs -o username=$SYNC_TARGET_USERNAME,password=$SYNC_TARGET_PASSWORD,uid=$SYNC_TARGET_UID,gid=$SYNC_TARGET_GID,iocharset=$SYNC_TARGET_IOCHARSET,codepage=$SYNC_TARGET_CODEPAGE,dir_mode=$SYNC_TARGET_DIR_MODE,file_mode=$SYNC_TARGET_FILE_MODE //$SYNC_TARGET_HOST/$SYNC_TARGET_SHARE $SYNC_TARGET_TEMP_MOUNT
		$options['username'] = $parsed_url['user'];
		$options['password'] = $parsed_url['pass'];

		# walk options and prepare them
		foreach ($options as $k => $v) {
			$options[$k] = $k.'="'.$v.'"';
		}

		# split path by slashes
		$tmp = explode("/", $parsed_url['path']);
		array_shift($tmp); # remove first item in path - the rootpath of the share
		$share = array_shift($tmp);
		# put the dir path back again
		$sharepath = implode("/", $tmp);

		# run mount
		$cmd = 'mount -t cifs -o '.implode(',', $options).' "//'.$parsed_url['host'].'/'.$share.'" "'.$mountpath.'"';
		cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);

		exec($cmd, $output, $retval);

		# if it failed, get out
		if ($retval !== 0) {
			cl('Mount failed: '.implode("\n", $output).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}

		# is there a share path?
		if (strlen($sharepath)) {

			$fullpath = $mountpath.(substr($sharepath, -1) !== '/' ? $sharepath.'/' : $sharepath);
			# does the path not exist?
			if (!is_dir($fullpath)) {
				cl('Path '.$sharepath.' on mounted share does not exist'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				# then unmount this share
				umount($mountpath);
				# and get out
				return false;
			}

			# secure endslash
			$fullpath = substr($fullpath, -1) !== '/' ? $fullpath.'/' : $fullpath;

			# return the full path instead
			return $fullpath;
		}

		# secure endslash
		$mountpath = substr($mountpath, -1) !== '/' ? $mountpath.'/' : $mountpath;

		# return the path to the data
		return $mountpath;
	}

	# to reverse-engineer the collection_id based on the fullpath of a file including filename
	# /known/collection/path/to/file.txt -> array(id_collections => x, basepath => path/to/)
	# /unknown/path/to/file.txt => array(id_collections => 0, basepath = > /unknown/path/to/)
	function identify_collection($fulltargetpath) {
		global $link;

		# get all collections
		$sql = 'SELECT * FROM collections';
		$collections = db_query($link, $sql);
		if ($collections === false) {
			cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			die();
		}

		# walk the collections
		foreach ($collections as $collection) {


			# parse the URL from where to get the files
			if (!($collection['url'] = parse_url($collection['url']))) {
				cl('Malformed URL format of destination '.$collection['url'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				continue 2;
			}

			$rootpath = false;

			# find out what type of source this is
			switch (strtolower($collection['url']['scheme'])) {
				# samba connection
				case 'smb':

					$rootpath = MOUNT_ROOTPATH.$collection['name'];

					break;
				# local path
				case 'file':
					$collection['fullpath'] = substr($collection['url']['path'], -1) !== '/' ? $collection['url']['path'].'/' : $collection['url']['path'];
					if (!is_dir($collection['fullpath'])) {
						cl('Local path '.$collection['fullpath'].' not found'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						continue 3;
					}

					$rootpath = $collection['fullpath'];
					break;
				default:
					cl('Unknown scheme: '.strtolower($collection['url']['scheme']).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					continue 3;
			}

			# no rootpath or fullpath is shorter than rootpath?
			if($rootpath === false || !strlen($rootpath) || strlen($fulltargetpath) < strlen($rootpath)) {
				# go next collection
				continue;
			}

			# /collection/rootpath != /path/to/file/ ?
			if (substr($fulltargetpath, 0, strlen($rootpath)) !== $rootpath) {
				# go next collection
				continue;
			}

			return array(
				'id_collections' => (int)$collection['id'],
				'basepath' => substr(
						$fulltargetpath,
						strlen($rootpath) - 0,
						# if only rootpath then it should be empty as collection provides last slash
						# if rootpath and subfolder, then it should be subfolder/
						strrpos($fulltargetpath, '/') + 1 - strlen($rootpath)
				)
			);
		} # eof-foreach-collections

		# no matching collection, return the fullpath to the item with no collection selected
		return array(
			'id_collections' => (int)FILES_ID_COLLECTIONS_NO_COLLECTION,
			# no collection, provide fullpath from root up to last slash
			'basepath' => substr($fulltargetpath, 0, strrpos($fulltargetpath, '/') + 1)
		);
	}

	# to move a file
	function move_file($fullsourcepath, $fulltargetpath, $item, $link_unused) {
		global $link;

		cl($fullsourcepath.' -> '.$fulltargetpath, VERBOSE_DEBUG);

		# source does not exist, bail out
		if (!file_exists($fullsourcepath)) {
			cl('Source does not exist: '.$fullsourcepath.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}

		# target already exists, bail out
		if (file_exists($fulltargetpath)) {
			cl('Target already exists: '.$fulltargetpath.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}

		# run the move command, escaping the shell arguments
		$cmd = 'mv '.escapeshellarg($fullsourcepath).' '.escapeshellarg($fulltargetpath);
		cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
		$result = exec($cmd);

		$source_exists = file_exists($fullsourcepath);
		$target_exists = file_exists($fulltargetpath);

		# if the source does not exist but the target does, then the move succeeded, otherwise error
		if (!$source_exists && $target_exists) {
			cl('Moved "'.$fullsourcepath.'" to "'.$fulltargetpath.'"', VERBOSE_INFO);
			# this is a known file in db
			if (isset($item['id'])) {

				cl('Fake-checking file: "'.$fulltargetpath, VERBOSE_DEBUG);
				$fakecheck = fakecheck_file($fulltargetpath) ? 1 : -1;
				cl('Fake check '.($fakecheck > 0 ? 'PASSED' : 'FAILED').' for: "'.$fulltargetpath, VERBOSE_DEBUG);


				# find out what collection it belongs to now
				# problem: what to do if it is a smb collection, then is it connected?

				#$sql = 'SELECT * FROM collections';
				#$collections = db_query($link, $sql);
				#if ($collections === false) {
				#	cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				#	die();
				#}

				# by default assume that the file has left the collections
				# $id_collections = FILES_ID_COLLECTIONS_NO_COLLECTION;
				# set the base path to a full path - outside any collection
				# $basepath = substr($fulltargetpath, 0, strrpos($fulltargetpath, '/') + 1);

				# walk collections
				# foreach ($collections as $collection) {
					# does this rootpath match the new location?
				#	if ($collection['rootpath'].'/' == substr($fulltargetpath, 0, strlen($collection['rootpath']) + 1)) {
						# then set this collection id
				#		$id_collections = $collection['id'];
						# cut out after the rootpath and the slash and up to and including the last slash for the basepath
				#		$basepath = substr($fulltargetpath, strlen($collection['rootpath']) + 1, strrpos($fulltargetpath, '/') - strlen($collection['rootpath']));
				#		break;
				#	}
				#}

				# set it to moved, also update filename - could repair
				# path="'.dbres($link, $basepath).'",

				# find out what collection it belongs to now
				$identified_collection = identify_collection($fulltargetpath);
				$sql = '
						UPDATE
							files
						SET
							id_collections="'.dbres($link, $identified_collection['id_collections']).'",
							path="'.dbres($link, $identified_collection['basepath']).'",
							moved='.FILES_MOVED_OK.',
							name="'.dbres($link, basename($fulltargetpath)).'",
							existing=1,
							fakecheck='.$fakecheck.'
						WHERE id="'.dbres($link, $item['id']).'"
						';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}

				# make logmessage about it
				logmessage(
					$link,
					LOGMESSAGE_TYPE_RELOCATION,
					array(
						'from' => $fullsourcepath,
						'to' => $fulltargetpath
					),
					false,
					(int)$item['id']
				);
			}
		# failed moving
		} else {
			cl('Failed moving file, source exists or target unexistent'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			# this is a known file in db
			if (isset($item['id'])) {
				$sql = 'UPDATE files SET moved='.FILES_MOVED_FAILED.', existing='.($source_exists ? 1 : 0).' WHERE id="'.dbres($link, $item['id']).'"';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}
			}
			return false;
		}

		# set group if available
		if (isset($item['movetochgrp']) && strlen($item['movetochgrp']) > 0) {
			cl('Trying to set group to '.$item['movetochgrp'], VERBOSE_DEBUG);
			if (chgrp($fulltargetpath, $item['movetochgrp'])) {
				cl('Set chgrp succeeded', VERBOSE_DEBUG);
			} else {
				cl('Failed chgrp'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			}
		}

		# set modes if available
		if (isset($item['movetochmod']) && strlen($item['movetochmod']) == 4) {
			cl('Trying to set modes to '.$item['movetochmod'], VERBOSE_DEBUG);
			# beware, this chmod mad function expects OCTAL values (or decimals ehh), so we convert our '0xxx' string
			# to a decimal one and use that which it understands...
			if (chmod($fulltargetpath, octdec($item['movetochmod']))) {
				cl('Set chmod succeeded', VERBOSE_DEBUG);
			} else {
				cl('Failed chmod'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			}
		}

		# move succeeded
		return true;
	}

	function get_parameters($link) {

		$parameters = array(
			# default parameters
			'email_address' => '',
			'email_enabled' => false,
			'email_last_sent' => '0000-00-00 00:00:00',
			'email_timeout' => 3600 # 1 hour
		);

		$sql = 'SELECT
						*
				FROM
					parameters';
		cl('SQL: '.$sql, VERBOSE_DEBUG);

		$result = db_query($link, $sql);
		if ($result === false) {
			die(json_encode(array(
				'status' => 'error',
				'data' => array(
					'message' => db_error($link)
				)
			)));
		}

		# walk row by row
		foreach ($result as $row) {
			$parameters[$row['parameter']] = $row['value'];
		}
		return $parameters;
	}

	function move_downloaded_files($link_unused) {
		global $link;

		$parameters = get_parameters($link);

		# --- step 0 - preparations

		cl('Retrieving move rules', VERBOSE_DEBUG);
		$sql = 'SELECT * FROM moverules WHERE status=1';
		cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
		$moverules = db_query($link, $sql);
		if ($moverules === false) {
			cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			die();
		}

		# make sure the paths in the rules end with a slash
		foreach ($moverules as $k => $v) {
			if (!strlen(trim($moverules[$k]['movetopath']))) continue;
			$moverules[$k]['movetopath'] = substr($moverules[$k]['movetopath'], -1) === '/' ? $moverules[$k]['movetopath'] : $moverules[$k]['movetopath'].'/';
		}

		cl('Found '.count($moverules).' rules in DB', VERBOSE_DEBUG);
		# no rules, get out
		if (!count($moverules)) return true;

		# --- step A - verify against searches

		cl('Reading download paths', VERBOSE_DEBUG);
		$files = get_filelist();

		# no files left to move, get out
		if ($files === false) {
			cl('Failed retrieving files in download path'.' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			return false;
		}

		# no files in downloaddir?
		if (!count($files)) {
			cl('Download path is empty', VERBOSE_DEBUG);
			return true;
		}

		cl('Found '.count($files).' files in download directories', VERBOSE_DEBUG);


		# walk files in download dirs
		foreach ($files as $file) {

			# fetch files matching in the database using the file names
			cl('Checking file '.$file['fullpath'].' ('.$file['size'].' b)', VERBOSE_DEBUG);


			# get basepath for it
			$filename = basename($file['fullpath']);

			cl('Checking database for unmoved files using filename', VERBOSE_DEBUG);
			# step A - try to match file in previous search-and-download-requests
			# removed AND files.id_clientpumps="'.dbres($link, $file['id_clientpumps']).'"

			$sql = '
				SELECT
					files.id,
					files.id_clientpumps,
					files.name,
					searches.id AS id_searches,
					searches.movetopath,
					searches.movetochgrp,
					searches.movetochmod,
					searches.search
				FROM
					files,
					searches
				WHERE
					files.id_searches=searches.id
					AND files.moved=0
					AND id_collections="'.dbres($link, FILES_ID_COLLECTIONS_DOWNLOAD).'"
					AND LENGTH(searches.movetopath) > 2
					AND files.size = "'.dbres($link, $file['size']).'"
					AND files.name = "'.dbres($link, $filename).'"';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$r = db_query($link, $sql);
			if ($r === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}

			# any matches in db?
			if (count($r)) {
				cl('Matched '.count($r).' entries in DB by filename', VERBOSE_DEBUG);
				# make sure target path ends with /
				$r[0]['movetopath'] = substr($r[0]['movetopath'], -1) === '/' ? $r[0]['movetopath'] : $r[0]['movetopath'].'/';
				if (move_file($file['fullpath'], $r[0]['movetopath'].$filename, $r[0], $link)) {

					# email
					$sql = 'UPDATE searches SET filessincelastmail=filessincelastmail+1 WHERE id="'.dbres($link, $r[0]['id_searches']).'"';
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$result_update_searches = db_query($link, $sql);
					if ($result_update_searches === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						die();
					}

				}
				continue;
			}

			# --- step B - try by hash
			cl('Calculating hash for '.$file['fullpath'], VERBOSE_DEBUG);
			$hash = ed2khash_custom($file['fullpath']);
			if (!$hash || !strlen($hash)) {
				cl('Hash calculation failed for '.$file['fullpath'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				continue;
			}

			cl('Hash for '.$file['fullpath'].': '.$hash, VERBOSE_DEBUG);

			# fetch files matching in the database using the file names
			cl('Checking database for unmoved files using hash', VERBOSE_DEBUG);
			# AND files.id_clientpumps="'.dbres($link, $file['id_clientpumps']).'"
			$sql = 'SELECT
					files.ed2khash,
					files.id,
					files.id_clientpumps,
					files.name,
					searches.search,
					searches.id AS id_searches,
					searches.movetopath,
					searches.movetochgrp,
					searches.movetochmod
				FROM
					files, searches
				WHERE
					files.id_searches=searches.id
					AND files.moved=0
					AND id_collections="'.dbres($link, FILES_ID_COLLECTIONS_DOWNLOAD).'"
					AND LENGTH(searches.movetopath)>2
					AND files.ed2khash = "'.dbres($link, $hash).'"';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$r = db_query($link, $sql);
			if ($r === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}



			# any matches in db?
			if (count($r)) {
				cl('Matched '.count($r).' entries in DB by ed2khash', VERBOSE_DEBUG);
				# make sure target path ends with /
				$r[0]['movetopath'] = substr($r[0]['movetopath'], -1) === '/' ? $r[0]['movetopath'] : $r[0]['movetopath'].'/';
				# try to move file
				if (move_file($file['fullpath'], $r[0]['movetopath'].$filename, $r[0], $link)) {

					# email
					$sql = 'UPDATE searches SET filessincelastmail=filessincelastmail+1 WHERE id="'.dbres($link, $r[0]['id_searches']).'"';
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$result_update_searches = db_query($link, $sql);
					if ($result_update_searches === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						die();
					}

				}

				continue;
			}

			# --- step C - try by move rules in regex

			# walk the rules
			foreach ($moverules as $moverule) {

				cl('Running regex #'.$moverule['id'].': '.$moverule['regex'], VERBOSE_DEBUG);

				# try to run the regex on the filename
				$status = preg_match($moverule['regex'], $filename);

				# regex failed
				if ($status === false) {
					cl('Failed regex #'.$moverule['id'].': '.$moverule['regex'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);

					# half-healing, try to disable this rule
					$sql = 'UPDATE moverules SET status=-1 WHERE id='.$moverule['id'];
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$result = db_query($link, $sql);
					if ($result === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						die();
					}

					# dangerous now - we take next file too to be safe
					continue 2;
				}

				# rule did not match, just go next
				if ($status === 0) {
					cl('Skipping, did not match regex #'.$moverule['id'].': '.$moverule['regex'], VERBOSE_DEBUG);
					continue;
				}

				# we found something
				if ($status === 1) {

					cl('Matched regex #'.$moverule['id'].': '.$moverule['regex'], VERBOSE_DEBUG);

					# give score to this rule
					$sql = 'UPDATE moverules SET matches=matches+1 WHERE id='.$moverule['id'];
					cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
					$result = db_query($link, $sql);
					if ($result === false) {
						cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
						die();
					}

					# set up move
					$fulltargetpath = $moverule['movetopath'] . $filename;
					cl('Target path is '.$fulltargetpath, VERBOSE_DEBUG);
					$filenr = 0;
					while (file_exists($fulltargetpath)) {
						$filenr += 1;
						cl('Target file exists: '.$fulltargetpath, VERBOSE_DEBUG);

						# is there a dot in the filename?
						if (strpos($filename, '.') !== false) {
							# then add the incrementation number right before the dot
							$fulltargetpath = $moverule['movetopath'] . (substr($filename, 0, strpos($filename, '.'))).$filenr.(substr($filename, strpos($filename, '.')));
						# or no dot in filename?
						} else {
							# then just add it to the end of the filename
							$fulltargetpath = $moverule['movetopath'] .$filename.$filenr;
						}

						cl('Retrying with: '.$fulltargetpath, VERBOSE_DEBUG);
					}

					# are we having a full target path to follow?
					if (strlen($fulltargetpath) > 2) {
						if (move_file(
							$file['fullpath'],
							$fulltargetpath,
							array(
								'movetochgrp' => $moverule['movetochgrp'],
								'movetochmod' => $moverule['movetochmod']
							), $link
						)) {

							# email
							$sql = 'UPDATE moverules SET filessincelastmail=filessincelastmail+1 WHERE id="'.dbres($link, $moverule['id']).'"';
							cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
							$result_update_moverules = db_query($link, $sql);
							if ($result_update_moverules === false) {
								cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
								die();
							}

						}
						continue 2; # go next file
					}
				}

			} # for-rules
		} # for-files

		# is there a mailer defined and any addresses to mail to and the last time a mail was sent has passed?
		if (
			# MAILER &&
			(int)$parameters['email_enabled'] === 1 &&
			((int)strtotime($parameters['email_last_sent']) + (int)$parameters['email_timeout']) < time()
		) {

			# check email address
			if (!filter_var($parameters['email_address'], FILTER_VALIDATE_EMAIL)) {
				cl('Skipping invalid mail address: '.$parameters['email_address'], VERBOSE_DEBUG);
				continue;
			}

			# sum the searches
			$sql = 'SELECT IFNULL(SUM(filessincelastmail), 0) AS totalfilessincelastmail FROM searches WHERE filessincelastmail>0';
			$r = db_query($link, $sql);
			if ($r === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}
			$totalfilessincelastmail = (int)$r[0]['totalfilessincelastmail'];

			# sum the move rules
			$sql = 'SELECT IFNULL(SUM(filessincelastmail), 0) AS totalfilessincelastmail FROM moverules WHERE filessincelastmail>0';
			$r = db_query($link, $sql);
			if ($r === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}
			$totalfilessincelastmail += (int)$r[0]['totalfilessincelastmail'];

			# any news?
			if ($totalfilessincelastmail > 0) {

				# clear the counters
				$sql = 'UPDATE searches SET filessincelastmail = 0';
				$r = db_query($link, $sql);
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				if ($r === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}

				$sql = 'UPDATE moverules SET filessincelastmail = 0';
				$r = db_query($link, $sql);
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				if ($r === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}

				# replace the template locations in the mailer string with our versions
				/*
				$cmd = str_replace(
					array(
						'###EMAIL###',
						'###SUBJECT###',
						'###BODY###'
					),
					array(
						escapeshellarg($parameters['email_address']),
						'\''.$totalfilessincelastmail.' new files\'',
						'\''.$totalfilessincelastmail.' new files has arrived.\''
					),
					MAILER
				);
				*/


				# cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
				cl('Sending mail to: '.$parameters['email_address'], VERBOSE_DEBUG_DEEP);
				# try to send the mail
				# exec($cmd, $output, $retval);

				# send mail
				mail(
					# to
					$parameters['email_address'],
					# subject
					$totalfilessincelastmail.' new files',
					# body
					$totalfilessincelastmail.' new files has arrived',
					# headers
					implode("\r\n", array(
						'From: '.MAIL_ADDRESS_FROM
					))
				);


				# check and update the last email sent-parameter
				$r = db_query($link, 'SELECT * FROM parameters WHERE parameter="email_last_sent"');
				# cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
				if ($r === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}

				if (count($r)) {
					$sql = 'UPDATE parameters SET value="'.dbres($link, date('Y-m-d H:i:s')).'" WHERE parameter="email_last_sent"';
				} else {
					$sql = 'INSERT INTO parameters (parameter, value) VALUES("email_last_sent", "'.dbres($link, date('Y-m-d H:i:s')).'")';
				}
				$r = db_query($link, $sql);
				# cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				if ($r === false) {
					cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}

				# make logmessage about it
				logmessage(
					$link,
					LOGMESSAGE_TYPE_EMAIL_SENT,
					array(
						'new_files' => $totalfilessincelastmail,
						'to' => $parameters['email_address']
					),
					false,
					0
				);


			}

		}

		return true;
	}

	# for some reason we need to twin-quote on filenames with ' in as there is a slash in db for this
	#function m-ysql_real_escape_array2($arr) {
	#	foreach ($arr as $key => $value) {
	#		$arr[$key] = '"'.m-ysql_real_escape_string(mysql_real_escape_string($value)).'"';
	#	}
	#	return $arr;
	#}

	# convert object to array - aaron at tekserve dot com 25-Oct-2009 01:16
	function object_to_array($mixed) {
		if(is_object($mixed)) $mixed = (array) $mixed;
		if(is_array($mixed)) {
			$new = array();
			foreach($mixed as $key => $val) {
				$key = preg_replace("/^\\0(.*)\\0/","",$key);
				$new[$key] = object_to_array($val);
			}
		}
		else $new = $mixed;
		return $new;
	}

	# parse an xml response and return a plain array
	function parse_response($xmlstr) {
		# TODO: better way to secure the filenames, possible search for <ed2klink>*</ed2klink> and fix between
		$xmlstr = str_replace('&', '&amp;', $xmlstr);

		# make sure we remove unneccessary tags
		$xmlstr = preg_replace("/<!--.*?-->/ms","",$xmlstr);

		# convert the xml to an object
		$xml = new SimpleXMLElement($xmlstr);

		# convert the object to an array if possible
		$xml = is_object($xml) ? object_to_array($xml) : $xml;
		return $xml;
	}

	# check if host responds to ping
	function pingtest($host) {
		    global $pingresults;

		    if (isset($pingresults[$host])) {
		            cl('Pinged '.$host.' before, reusing that', VERBOSE_DEBUG);
		            return $pingresults[$host];
		    }

		    cl('Pinging '.$host, VERBOSE_DEBUG);
		    # do it quiet, send only 1 packet, wait 3 sec for it to return
		    $cmd = 'ping -w3 -qc1 '.$host.' 2>&- 1>&-';
		    cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
		    $t = microtime();
		    exec($cmd, $output, $retval);
		    $t = microtime() - $t;

		    # remember the response to next time this is run
		    $pingresults[$host] = ($retval === 0);

			if ($retval === 0) {
				cl('Ping response from '.$host.' in '.$t.' seconds', VERBOSE_DEBUG);
			} else {
				cl('No response from '.$host, VERBOSE_DEBUG);
			}

		    return  ($retval === 0);
	}

	function remove_comments($s) {
		$s = explode("\n", $s);

		foreach ($s as $k => $v) {
			$s[$k] = preg_replace('/^\s*\#+.*$/', '',$v);
		}

		return implode("\n", $s);
	}

	# to scan search results for possible downloads
	function scan_results_for_downloads($c, $link, $ses, $blacklist, $id_searches=false, $force=false) {

		# get pump data
		global $clientpumps;

		# get searches by id of the search
		if ($id_searches) {
			$sql = 'SELECT
						*
					FROM
						searches
					WHERE
						id = "'.dbres($link, $id_searches).'"
					';
		# or get searches and order by how long ago the search has been done, plus timeout
		} else {
			$sql = 'SELECT
						*
					FROM
						searches
					WHERE
						status='.SEARCHES_STATUS_ACTIVE.'
						AND resultscans < 2
					ORDER BY (UNIX_TIMESTAMP(executed) + executiontimeout)
					';
		}

		cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
		$searches = db_query($link, $sql);
		if ($searches === false) {
			cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
			die();
		}

		# do not go forward if all searches already have been scanned for downloads
		if (!count($searches)) {
			cl('No unscanned searches found', VERBOSE_DEBUG);
			return false;
		}

		$queued_for_download = 0;

		# fetch search results from all available clients and merge them
		$r = array();
		$scanned_pumps = 0;
		foreach ($clientpumps as $pumpname => $pump) {

			# is this pump not active?
			if (!(int)$pump['data']['status']) {
				cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') is inactive, skipping', VERBOSE_DEBUG);
				continue;
			}

			# request search results
			cl('Requesting search results from '.$pumpname.' (#'.$pump['data']['id'].')', VERBOSE_DEBUG);
			$tmp = $pump['pump']->results();
			# did it fail?
			if ($tmp === false || !is_array($tmp)) {
				cl('Failed requesting search results from '.$pumpname.' (#'.$clientpumps[ $pumpname ]['data']['id'].'): '.$clientpumps[ $pumpname ]['pump']->messages(false, true).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}

			# walk and enrichen the result with the name of the pump
			foreach ($tmp as $k => $notused) {
				$tmp[$k]['pumpname'] = $pumpname;
				$tmp[$k]['id_clientpumps'] = $pump['data']['id'];
			}

			# mash together the previous pump results with the current one
			$r = array_merge($r, $tmp);
			$scanned_pumps++;
		}

		if (!$scanned_pumps) {
			cl('No pumps scanned, skipping further analyzations', VERBOSE_DEBUG);
			return true;
		}

		# no id_searches?
		if ($id_searches === false) {
			# update the result scan counters, making sure we do not run the scans for this search too often
			$sql = 'UPDATE searches SET resultscans=resultscans+1 WHERE status='.SEARCHES_STATUS_ACTIVE.' AND resultscans < 2';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}
		# or do we have id_searches?
		} else {
			# update the result scan counters, making sure we do not run the scans for this search too often
			$sql = 'UPDATE searches SET resultscans=resultscans+1 WHERE id="'.dbres($link, $id_searches).'"';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$result = db_query($link, $sql);
			if ($result === false) {
				cl(db_error($link).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				die();
			}
		}

		# walk the searches
		foreach ($searches as $key => $item) {
			# separate down the words into array items - easier to handle
			$searches[$key]['search']		= explode(' ', $searches[$key]['search']);
			$searches[$key]['sizemin']		= (float)$searches[$key]['sizemin'] * 1024 * 1024; # MB to b
			$searches[$key]['sizemax']		= (float)$searches[$key]['sizemax'] * 1024 * 1024; # MB to b
			# $searches[$key]['extension']	= $searches[$key]['extension'];
			$searches[$key]['type']			= strtolower($searches[$key]['type']);
		}

		# check the response filelist (file represents an array of files...)
		if (!count($r)) {
			cl('No results found - yet, try checking results later', VERBOSE_DEBUG);
			return false;
		}

		# this is important
		cl('Scanning '.count($r).' result rows for downloads', VERBOSE_INFO);

		# walk the results from pump client
		# foreach ($r['filelist']['file'] as $file) {
		foreach ($r as $file) {

			$fileinfo = explode('|', $file['link']);

			/*
			 0: protocol (crap)
			 1: type
			 2: filename
			 3: size?
			 4: ed2khash
			 5: endslash (crap)
			*/

			# incomplete response?
			if (!isset($fileinfo[2])||!isset($fileinfo[3])||!isset($fileinfo[4])) {
				cl('Invalid response - ED2k-link is invalid, contents of it: '.$file['link'].' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
				continue;
			}

			$filesize = (float)$fileinfo[3];
			$filename = $fileinfo[2];
			$filehash = strtoupper($fileinfo[4]);
			$filetype = isset($file['type']) ? strtolower($file['type']) : false;

			cl('Checking "'.$filename.'" ('.$filesize.' b, '.$filehash.')', VERBOSE_DEBUG);

			# walk blacklist first to stop any eventually bad named files
			foreach ($blacklist as $word) {
				# not found?
				if (strpos(strtolower($filename), strtolower($word)) !== false) {
					cl('Skipping, bad blacklisted words found: '.$word, VERBOSE_DEBUG);
					continue 2; # next search
				}
			}

			# walk the searches to see if this file is interesting
			foreach ($searches as $key => $search) {

				cl('Comparing file with search #'.$key, VERBOSE_DEBUG);

				# check minimum size
				if ($search['sizemin'] != 0 && $filesize < $search['sizemin']) {
					cl('Skipping, too small', VERBOSE_DEBUG);
					continue;
				}

				# check maximum size
				if ($search['sizemax'] != 0 && $filesize > $search['sizemax']) {
					cl('Skipping, too big', VERBOSE_DEBUG);
					continue;
				}

				# check extension - cut out the ending of the filename to check extension
				if (strlen($search['extension']) > 0 && strtolower(substr($filename, strlen($filename) - strlen($search['extension']) - 1)) != $search['extension']) {
					cl('Skipping, extension mismatch', VERBOSE_DEBUG);
					continue;
				}

				# mlnet does not provide type in search results
				# to fix this we must extract file extension and go by that instead
				# check type - only works on emule
				if ($filetype !== false && strlen($search['type']) > 0 && $filetype != $search['type']) {
					cl('Skipping, type mismatch', VERBOSE_DEBUG);
					continue;
				}

				# walk the search words to see if they all exist in the filename
				foreach ($search['search'] as $word) {
					# not found?
					if (strpos(strtolower($filename), strtolower($word)) === false) {
						cl('Skipping, search word not found: '.$word, VERBOSE_DEBUG);

						# jump outside to take next file
						continue 2;
					}
				}
				cl('Search words match', VERBOSE_DEBUG);

				# check for previous downloads and existence using name and filesize
				$sql = 'SELECT * FROM files WHERE name="'.dbres($link, $filename).'" AND size="'.dbres($link, $filesize).'"';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_DEBUG);
					die();
				}
				if (count($result) > 0) {
					cl('Skipping, file downloaded before, detected by name + size', VERBOSE_DEBUG);
					continue 2;
				}

				# check for previous downloads and existence using ed2k-hash
				$sql = 'SELECT * FROM files WHERE UPPER(ed2khash)="'.dbres($link, $filehash).'"';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_DEBUG);
					die();
				}
				if (count($result) > 0) {
					cl('Skipping, file downloaded before, detected by ed2khash', VERBOSE_DEBUG);
					continue 2;
				}

				cl('File has not been downloaded before', VERBOSE_DEBUG);

				# store file as downloaded (or at least tried to...)
				$insert_update = array(
					'created'			=> date('Y-m-d H:i:s'),
					'ed2khash'			=> $filehash,
					'existing'			=> 0, # not yet
					'id_clientpumps'	=> $clientpumps[ $file['pumpname'] ]['data']['id'],
					'id_collections'	=> FILES_ID_COLLECTIONS_DOWNLOAD, # downloads,
					'id_searches'		=> $search['id'],
					'name'				=> $filename,
					'size'				=> $filesize,
					# path?
					'verified'			=> 0
				);


				# request download
				cl('Requesting download from pump '.$file['pumpname'].' (#'.$clientpumps[ $file['pumpname'] ]['data']['id'].')', VERBOSE_DEBUG);
				if (!$clientpumps[ $file['pumpname'] ]['pump'] ->download($file['id'])){
					# did it fail? why?
					cl('Failed requesting download from '.$file['pumpname'].' (#'.$clientpumps[ $file['pumpname'] ]['data']['id'].'): '.$clientpumps[ $file['pumpname'] ]['pump']->messages(false, true).' ('.__FILE__.':'.__LINE__.')', VERBOSE_ERROR);
					die();
				}


				$queued_for_download++;

				cl('Storing file in database', VERBOSE_DEBUG);
				$insert_update = dbpia($link, $insert_update);
				$sql = 'INSERT INTO files ('.implode(',', array_keys($insert_update)).') VALUES('.implode(',', $insert_update).')';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_DEBUG);
					die();
				}

				# new file found - make a log message about it
				logmessage(
					$link,
					LOGMESSAGE_TYPE_DOWNLOAD_REQUESTED,
					array(
						'ed2khash' => $filehash,
						'id_searches' => $search['id'],
						'name' => $filename,
						'size' => $filesize
					),
					false,
					db_insert_id($link)
				);

				# update the search counters
				$sql = 'UPDATE searches SET queuedfiles=queuedfiles+1 WHERE id="'.$search['id'].'"';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_DEBUG);
					die();
				}

				# update this client counter too
				$sql = 'UPDATE clientpumps SET queuedfiles=queuedfiles+1 WHERE id="'.$clientpumps[ $file['pumpname'] ]['data']['id'].'"';
				cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
				$result = db_query($link, $sql);
				if ($result === false) {
					cl(db_error($link), VERBOSE_DEBUG);
					die();
				}

			}
		}

		if ($queued_for_download) {
			# this is important
			cl('Queued '.$queued_for_download.' new file(s) for download', VERBOSE_INFO);
		}
	}

	# to split an ed2klink into an array
	function split_ed2klink($ed2klink) {

		# get info for this file
		$fileinfo = explode('|', $ed2klink);

		# 0: protocol (crap)
		# 1: type
		# 2: filename
		# 3: size?
		# 4: ed2khash
		# 5: endslash (crap)

		# incomplete response?
		if (!isset($fileinfo[2])||!isset($fileinfo[3])||!isset($fileinfo[4])) {
			# Invalid response - ED2k-link is invalid, contents of it: '.$file['ed2klink']
			return false;
		}

		return array(
			'filehash' => strtoupper($fileinfo[4]),
			'filename' => $fileinfo[2],
			'filesize' => intval($fileinfo[3])
		);
	}

	# to unmount directory
	function umount($mountpath) {
		# command: umount $SYNC_SOURCE_TEMP_MOUNT  2>&- 1>&-;
		cl('Unmounting '.$mountpath, VERBOSE_DEBUG);
		$cmd = 'umount "'.$mountpath.'"';
		cl('Running: '.$cmd, VERBOSE_DEBUG_DEEP);
		exec($cmd, $output, $retval);
		return  ($retval == 0);
	}

	# quickly mashed together as we need this code both in the action and the view part
	function web_check_results($conn, $r, $link, $show_download) {

		global $clientpumps;
		$already_download = 0;

		$searchresultlist = $r;

		if (!count($searchresultlist)) return array();

		# --- get transferlist

		# fetch search results from pump clients
		$transferlist = array();
		foreach ($clientpumps as $pumpname => $pump) {


			# is this pump not active?
			if (!(int)$pump['data']['status']) {
				cl('Pump '.$pumpname.' (#'.$pump['data']['id'].') is inactive, skipping', VERBOSE_DEBUG);
				continue;
			}

			$rtmp = $pump['pump']->transfers();
			# did it fail?
			if ($rtmp === false) {
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => 'Fetch transfer from '.$pumpname.' ('.$pump['data']['id'].') failed: '.$pump['pump']->messages(false,true)
					)
				)));

			}

			# enrichen data with info about pump name and it's id
			foreach ($rtmp as $k => $v) {
				$rtmp[$k]['pumpname'] = $pumpname;
				$rtmp[$k]['id_clientpumps'] = $pump['data']['id'];
			}

			$transferlist = array_merge($transferlist, $rtmp);
		}

		# $transferlist = isset($r['filelist']['file']) ? $r['filelist']['file'] : array();
		$ed2khashes = array();

		# walk the search result list
		foreach ($searchresultlist as $key => $file) {
			# cast data
			# $searchresultlist[$key] = caster($searchresultlist[$key], array('id'));

			# no link data
			if (!isset($file['link'])) {
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => 'Invalid filelist response - no ED2k-link or type, file structure was: '.var_export($file,true)
					)
				)));
			}


			# split the link into useable parts
			$tmp = split_ed2klink($file['link']);
			if ($tmp === false) continue;

			# prepare for sql below
			$ed2khashes[] = '"'.dbres($link, $tmp['filehash']).'"';
			$searchresultlist[$key]['ed2k'] = $tmp['filehash'];

			# scan the transfer list for existing downloads matching ed2khash
			foreach ($transferlist as $transferfile) {
				if (strtoupper($transferfile['ed2k']) === strtoupper($tmp['filehash'])) {
					# should we hide already downloaded?
					if ($show_download) {
						$searchresultlist[$key]['download'] = (isset($searchresultlist[$key]['download']) ? $searchresultlist[$key]['download'] : '') . 't'; # T for transferlist
					} else {
						$already_download++;
						unset($searchresultlist[$key]);
					}
					break;
				}
			}
		}

		if (count($searchresultlist)) {

			# check kreosot for already downloaded
			$sql = 'SELECT
						ed2khash
					FROM
						music.seen_ed2khashes
					WHERE
						ed2khash IN ('.implode(',', $ed2khashes).')';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$result = db_query($link, $sql);
			if ($result === false) {
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => db_error($link)
					)
				)));
			}

			# any results?
			if (count($result) > 0) {

				foreach ($result as $row) {
					# walk the search result list
					foreach ($searchresultlist as $k => $file) {
						if (isset($searchresultlist[$k]['ed2k']) && strtoupper($searchresultlist[$k]['ed2k']) === strtoupper($row['ed2khash'])) {
							if ($show_download) {
								$searchresultlist[$k]['download'] = (isset($searchresultlist[$k]['download']) ? $searchresultlist[$k]['download'] : '') . 'k'; # K for Kreosot
							} else {
								$already_download++;
								unset($searchresultlist[$k]);
							}
						}
					}
				}
			}

		}

		# anything in the search result list?
		if (count($searchresultlist)) {

			# check emulehelper for already downloaded
			$sql = 'SELECT
						ed2khash
					FROM
						emulehelper.files
					WHERE
						ed2khash IN ('.implode(',', $ed2khashes).')';
			cl('SQL: '.$sql, VERBOSE_DEBUG_DEEP);
			$result = db_query($link, $sql);
			if ($result === false) {
				die(json_encode(array(
					'status' => 'error',
					'data' => array(
						'message' => db_error($link)
					)
				)));
			}


			if (count($result) > 0) {
				foreach ($result as $row) {
					# walk the search result list
					foreach ($searchresultlist as $k => $file) {
						if (strtoupper($searchresultlist[$k]['ed2k']) === strtoupper($row['ed2khash'])) {
							if ($show_download) {
								$searchresultlist[$k]['download'] = (isset($searchresultlist[$k]['download']) ? $searchresultlist[$k]['download'] : '') . 'e'; # E for eMulehelper
							} else {
								$already_download++;
								unset($searchresultlist[$k]);
							}
						}
					} # foreach-searchresltlist
				} # foreach-result
			}
		}

		# remove double ed2khashes
		if (count($searchresultlist)) {
			foreach ($searchresultlist as $k => $file) {
				unset($searchresultlist[$k]['ed2k']);
				# type is used by the scheduled downloader but not the web frontend
				unset($searchresultlist[$k]['type']);
			}
		}

		# are we hiding something and we have some downloaded already?
		if ($show_download === 0 && $already_download) {
			array_push($searchresultlist, array(
				'rowtype' => 'info',
				'content' => 'Already downloaded: '.$already_download
			));
		}

		return $searchresultlist;
	}

	# a custom version of ed2khash to cope with the 2 GB file size limit on 32-bit PHP
	function ed2khash_custom($filename) {
		# is PHP limited?
		if ( (PHP_INT_MAX == 2147483647) && strtolower(PHP_OS) === 'linux') {
			# run rhash instead
			$data = shell_exec('/usr/local/bin/rhash -E '.escapeshellarg($filename));
			# find first space, cut from there
			$data = strtoupper(trim(substr($data, 0, strpos($data, " "))));
			return $data;
		# or can we use the internal hasher?
		} else {
			return ed2khash($filename);
		}
	}

	# --- translation ----

	function is_logged_in() {
		return true;
	}

	# to get the current locale
	function get_current_locale(){
		global $translations;
		return reset($translations['languages'][ $translations['current']['index'] ]['locales']);
	}

	# to get a matching locale translation index, send in locale and get a working translation index in return
	function get_working_locale($langs_available, $try_lang = false) {

		$accept_langs = array();

		# no language to try provided?
		if (!$try_lang) {
			# try with header - or if not there, go en
			$try_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : false;
		}

		# any language to try now?
		if ($try_lang) {
			preg_match_all(
				'/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.\d+))?/i',
				$try_lang,
				$lang_parse
			);

			if (isset($lang_parse[1]) && count($lang_parse[1])) {

				# create a list like 'en-US' => 0.7
				$accept_langs = array_combine($lang_parse[1], $lang_parse[4]);

				# set default to 1 for any without q factor
				foreach ($accept_langs as $k => $v) {
					if ($v === '') {
						$accept_langs[$k] = 1;
					}
				}

				arsort($accept_langs, SORT_NUMERIC);
			}# if match
		} # if-trylang


		# walk the languages - en, sv, es etc...
		foreach (array_keys($accept_langs) as $current_acceptlang) {
			# walk the available languages provided
			foreach ($langs_available as $k => $v) {
				# walk the locales in this provided language
				foreach ($v['locales'] as $k2 => $v2) {
					# compare the language
					if (strtolower($v2) === strtolower($current_acceptlang)) {
						return $k;
					}
				}
			}

			$acceptlang_intro = stristr($current_acceptlang, '-') ? substr($current_acceptlang, 0, strpos($current_acceptlang, '-')) : $current_acceptlang;

			foreach ($langs_available as $k => $v) {
				foreach ($v['locales'] as $k2 => $v2) {
					if (strtolower($v2) === strtolower($acceptlang_intro)) {
						return $k;
					}
				}
			}

			foreach ($langs_available as $k => $v) {
				foreach ($v['locales'] as $availlang) {
					if (strtolower($acceptlang_intro) === strtolower(stristr($availlang, '-') ? substr($availlang, 0, strpos($availlang, '-')) : $availlang)) {
						return $k;

					}
				}
			}
		}

		return 0;
	}

	# to switch locale if possible
	function switch_locale($locale) {
		global $translations;
		# get a working locale index
		$translations['current']['index'] = get_working_locale($translations['languages'], $locale);

		# set this locale
	    $translations['current']['locale'] = reset($translations['languages'][$translations['current']['index']]['locales']);

		return true;
	}

	# to translate string
	function t($s) {
		# get translation data and translations
		global $translations;

		# make sure we have the index
		$tindex = isset($translations['current']['index']) ? $translations['current']['index'] : 0;

		# is this language not present
		if (!isset($translations['languages'][$tindex])) {
			# then get out
			return $s;
		}

		foreach ($translations['languages'][$tindex]['content'] as $sentence) {
			if (
				# are all parts there
				isset($sentence[0], $sentence[1]) &&
				# is the sentence the one we are looking for
				$s === $sentence[0] &&
				# and there is an replacement sentence
				$sentence[1] !== false
			) {
					# then return it
				return $sentence[1];
			}
		}

		if (isset($translations['languages'][$tindex]['content_logged_in'])) {
			foreach ($translations['languages'][$tindex]['content_logged_in'] as $sentence) {
				if (
					# are all parts there
					isset($sentence[0], $sentence[1]) &&
					# is the sentence the one we are looking for
					$s === $sentence[0] &&
					# and there is an replacement sentence
					$sentence[1] !== false
				) {
					# then return it
				return $sentence[1];
				}
			}
		}
		return $s;
	}

	# to translate string
	function get_translation_texts() {
		# get translation data and translations
		global $translations;

		# make sure we have the index
		$tindex = isset($translations['current']['index']) ? $translations['current']['index'] : 0;

		# is this language not present
		if (!isset($translations['languages'][$tindex])) {
			# then get out
			return array();
		}

		return is_logged_in() ? array_merge($translations['languages'][$tindex]['content'], $translations['languages'][$tindex]['content_logged_in']) : $translations['languages'][$tindex]['content'];
	}

	# base structure for translations
	$translations = array(
		'current' => array(
			'index' => 0,
			'locale' => 'en-US'
		),
		'languages' => array(
			array(
				# content for the locale
				'content' => array(),
				'content_logged_in' => array(),
				'locales' => array(
					'en-US'
				)
			)
		)
	);

	function start_translations() {
		global $translations;

		# directory where the translations are located
		$locale_basepath = substr(__FILE__, 0, strrpos(__FILE__, '/') + 1 ).'locales/';

		# scan the directory
		$dircontents = scandir($locale_basepath);

		# walk contents of directory
		foreach ($dircontents as $item) {

			# does this item end with the desired ending?
			if (substr($item, -9) === '.lang.php') {

				require_once($locale_basepath.$item);
			}
		}

		# get the parameters
		$translations['current']['index'] = isset($_REQUEST['translationindex']) ? $_REQUEST['translationindex'] : false;

		# session_start();

		$translations['current']['index'] = !isset($_SESSION['translation_index']) ? get_working_locale($translations['languages']) : $_SESSION['translation_index'];
		$translations['current']['locale'] = reset($translations['languages'][$translations['current']['index']]['locales']);
		$_SESSION['translation_index'] = $translations['current']['index'];
	}


?>
