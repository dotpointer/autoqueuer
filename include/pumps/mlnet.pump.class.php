<?php
	# MLDonkey-Server Client Pump Class - manages the communication between emulehelper and the client

	# changelog
	# 2013-12-20 00:00:00
	# 2013-12-21 00:00:00 - improving, connecting to kreosot
	# 2013-12-22 00:00:00 - improving again
	# 2014-07-16 15:42:54 - taking emulehelper class
	# 2014-07-19 20:05:43 - making it a pump
	# 2014-07-22 00:00:00 - multi-client with clientpumps, search with name+size check
	# 2014-07-23 18:37:10
	# 2015-07-23 01:19:56 - bugfixes for mlnet and utf8 - mlnet sends utf8 text
	# 2015-07-24 23:18:00 - bugfix for 0 in min/max values which were sent to client and stopped searches
	# 2015-08-21 11:24:56 - cleanup
	# 2015-08-22 10:11:50
	# 2016-11-08 23:24:04 - bugfix min max

	# general notice: data from mlnet already is in UTF-8!

	class mlnetPump {

		private $c 			= false;
		private $host 		= 'localhost';
		private $messages	= array();
		private $password 	= '';
		private $port 		= 4080;
		private $ses 		= false;
		private $url 		= '';
		private $username	= '';

		# mlnet-defined search types, key is used
		public	$types = array(
			# arc is missing
			'Audio'		=> 'Audio',
			# 'Col'		=> 'Collection', # this is unique for mlnet
			'Doc'		=> 'Document',
			'Image'		=> 'Image',
			'Pro'		=> 'Program',
			'Video'		=> 'Video'
		);

		# when object is made
		public function __construct($config=array()) {

			# invalid config - then get out now
			if (!is_array($config)) {
				return false;
			}

			$this->host				= isset($config['host']) 			!== false	? $config['host']			: $this->host;
			$this->password			= isset($config['password']) 		!== false	? $config['password']		: $this->password;
			$this->port				= isset($config['port'])			!== false	? $config['port']			: $this->port;
			$this->username			= isset($config['username']) 		!== false	? $config['username']		: $this->username;

			# connection should not be made here, as emh creates object during loading

			return true;
		}

		# when object is destroyed
		public function __destruct() {
			# is there a curl connection?
			if ($this->c) {
				# then close it
				curl_close($this->c);
			}
		}

		# to get an url
		private function getUrl() {
			return 'http://'.$this->username.':'.$this->password.'@'.$this->host.':'.$this->port.'/';
		}

		# as PHP:s built-in array_merge does not do a real merge with overwriting of int-keys, we do it ourself
		private function arrayMergeKeepKeys(/* dynamic */) {
			$result = array();
			foreach (func_get_args() as $arg) {
				if (!is_array($arg)) continue;
				foreach ($arg as $k => $v) {
					$result[$k] = $v;
				}
			}
			return $result;
		}

		# must be tested, id comes from download
		public function cancel($id) {
			# example: http://host:4080/files?cancel=9&selectPriority9=%3D0
			$data = $this->curl(array(
				CURLOPT_URL => $this->getUrl().'files?'.http_build_query(array('cancel' => $id, 'selectPriority9' => '=0'))
			));
			if ($data === false) return false;

			return true;
		}

		# simple curling function to send and receive data
		private function curl($opt=array()) {

			# no curl object?
			if (!$this->c) {
				# then make one
				$this->c = curl_init();
			}

			# default setup
			$defopt = array(
				CURLOPT_COOKIESESSION	=> true,
				CURLOPT_URL				=> '',
				CURLOPT_RETURNTRANSFER 	=> true,	# return web page
				CURLOPT_HEADER         	=> false,	# don't return headers
				CURLOPT_FOLLOWLOCATION 	=> true,	# follow redirects
				CURLOPT_ENCODING       	=> '',		# handle all encodings
				CURLOPT_USERAGENT      	=> 'idfc',	# who am i
				CURLOPT_AUTOREFERER    	=> true,	# set referer on redirect
				CURLOPT_CONNECTTIMEOUT 	=> 120,		# timeout on connect
				CURLOPT_TIMEOUT        	=> 120,		# timeout on response
				CURLOPT_MAXREDIRS      	=> 10,		# stop after 10 redirects
				CURLOPT_POST            => false,	# i am sending post data
				CURLOPT_SSL_VERIFYHOST	=> 0,		# don't verify ssl
				CURLOPT_SSL_VERIFYPEER	=> false,
				CURLOPT_VERBOSE			=> false,       # set to true to echo headers
			);

			# merge defaults and provided parameters to get the best mix
			$opt = $this->arrayMergeKeepKeys($defopt, $opt);

			curl_setopt_array($this->c, $opt);
			$r = curl_exec($this->c);
			if (curl_errno($this->c)) {
				$this->message('cURL-error: '.curl_error($this->c).' ('.curl_errno($this->c).')');
				return false;
			}
			return $r;
		}

		# to make a message
		private function message($s, $level='error') {
			$this->messages[] = array('level' => $level, 'msg' => $s);
			return false;
		}

		# to get messages - empties messages and returns them
		public function messages($levels=false, $mashed=false) {

			$levels = is_array($levels) ? $levels : array('error');

			$tmp = $mashed ? '' : array();
			$first = true;
			foreach ($this->messages as $message) {
				if (in_array($message['level'], $levels)) {
					if ($mashed) {
						# not first?
						if (!$first) {
							# add separator
							$tmp .= "\n";
						}

						$tmp .= $message['msg'];
						$first = false;
					} else {
						$tmp[] = $message;
					}
				}
			}

			$this->messages = [];
			return $tmp;
		}


		# to get search results
		public function results() {
			$data = $this->curl(array(
				CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vr'))
			));
			if ($data === false) return false;

			# example data to match:
			# </tr>
			# <tr class="dl-1"><td class="sr"><a href="ed2k://|file|SomeFile.ext|3244353555|ABCDEFGHIJKLMNOPQRSTUVXYZ1234567|/">Donkey</a></td><td onMouseOver="setTimeout('popLayer(\'Sing Star - ABBA (pw=MamaMia).rar<BR>completesources: 1&lt;br&gt;availability: 1\')',0);setTimeout('hideLayer()',100000);return true;"  onMouseOut="hideLayer();setTimeout('hideLayer()',0);return true;" class="sr"><a href=results?d=555 target="fstatus">SomeFile.ext
			# </a></td><td class="sr ar">3.23G</td>
			#			<td class="sr ar">1</td>
			#			<td class="sr ar">1</td>
			#			<td class="sr"><a href="http://bitzi.com/lookup/ed2k:ABCDEFGHIJKLMNOPQRSTUVXYZ1234567">BI</a></td>
			#			<td class="sr"><a href="http://www.filedonkey.com/url/ABCDEFGHIJKLMNOPQRSTUVXYZ1234567">FD</a></td>
			#			<td class="sr ar"></td>
			#			<td class="sr ar"></td>
			#			<td class="sr ar"></td><td class="sr"></td></tr>

			preg_match_all('/\<a href\=\"(ed2k\:\/\/\|file\|(.*?)\|(\d+)\|([a-zA-Z0-9]+)\|\/)\"\>/', $data, $m);

			$r = array();
			foreach ($m[1] as $k => $name) {
				$r[] = array(
					'ed2k' => (rawurldecode($m[4][$k])),
					'id'   => (rawurldecode($m[1][$k])),
					'link' => (rawurldecode($m[1][$k])),
					'name' => (rawurldecode($m[2][$k])),
					'size' => (rawurldecode($m[3][$k]))
				);
			}

			return $r;

		}

		# to request download
		public function download($ed2klink) {

			$data = $this->curl(array(
				CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'dllink '.$ed2klink))
			));
			if ($data === false) return false;

			# no suitable response texts?
			if (strpos($data, 'Added link') === false && strpos($data, 'File is already in download queue') === false) {
				# then raise error
				$this->message('Download request failed, invalid response: '.var_export($r, true));
				return false;
			}

			# otherwise all ok
			return true;
		}

		# to get transfer list
		public function transfers() {

			$data = $this->curl(array(
				CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vd'))
			));
			if ($data === false) return false;

			# all is well, but there are no search results
			if (strpos($data, 'No files, please use search') !== false) {
				return array();
			}

			preg_match_all(
				# example: onMouseOver="mOvr(this);setTimeout(\'popLayer(\\\'Some.File.ext&lt;br&gt;File#: 66&lt;
				# '/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt/',
				
				# '/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt.*?\"loaded\" style\=\"height:2px\" width\=\"(.*?)\"/',
				'/onMouseOver=\"mOvr\(this\)\;setTimeout\(\'popLayer\(\\\\\'(.*?)&lt;br&gt;File\#: (\d+)&lt.*?\"loaded\" style\=\"height:2px\" width\=\"(\d+)%\"/sim',
				
				
				$data,
				$matches
			);
			
			#var_dump($matches);
			#die();

			# no matches?
			if (!count($matches[0])) {
				# all is well, but there are no results
				# then raise error
				$this->message('Request failed, invalid response: '.var_export($data, true));
				return false;
			}

			$tmp = array();
			foreach ($matches[0] as $key => $notused) {
				$row = array(
					'name' => $matches[1][$key],
					'id' => (int)$matches[2][$key], 
					'completed' => (int)$matches[3][$key],
				);

				# lets walk again and request info page per download, to get ed2klink
				$data = $this->curl(array(
					CURLOPT_URL => $this->getUrl().'submit?'.http_build_query(array('q' => 'vd '.$row['id']))
				));
				if ($data === false) return false;


				# any ed2klink?
				preg_match_all('/\<a href\=\"(ed2k\:\/\/\|file\|(.*?)\|(\d+)\|([a-zA-Z0-9]+)\|\/)\"\>/', $data, $m);
				# did it fail?
				if (!count($m[0])) {
					# then raise error
					$this->message('Failed extracting detailed info about row '.$row['id'].', '.$row['name']);
					return false;
				}

				# a wild guess here that utf8 is already from mlnet here too
				$row['link'] = (rawurldecode($m[1][0]));
				$row['name'] = (rawurldecode($m[2][0]));
				$row['size'] = (rawurldecode($m[3][0]));
				$row['ed2k'] = (rawurldecode($m[4][0]));
				# $row['completed'] = (rawurldecode($m[5][0]));
				$row['sizecompleted'] = (float)rawurldecode($m[3][0]) * ((int)$row['completed']/100);

				$tmp[] = $row;
			}

			return $tmp;
		}

		# to perform a search - searchtext, options can be supplied
		public function search($search, $options = array()) {

			# example: http://host:4080/submit?custom=Complex+Search&keywords=some+keywords&minsize=&minsize_unit=1048576&maxsize=&maxsize_unit=1048576&media=&media_propose=&format=&format_propose=&artist=&album=&title=&bitrate=&network=
			# fail   : http://host:4080/submit?album=&artist=&bitrate=&custom=Complex+Search&format=&format_propose=&keywords=spankingserver&maxsize=&maxsize_unit=1048576&media=&media_propose=Video&minsize=50&minsize_unit=1048576&network=&title=
			# default parameters - NOTE the ORDER of the arguments, wrong order results in 404 not found
			
			# note for mlnet: sending in 0 as min or max results in zero results
			
			$search = array(
				'custom'			=> 'Complex Search',
				'keywords'			=> $search,
				'minsize'			=> isset($options['min']) && (int)$options['min'] !== 0 ? $options['min'] : '',
				'minsize_unit' 		=> 1048576, # MB
				'maxsize'			=> isset($options['max']) && (int)$options['max'] !== 0 ? $options['max'] : '',
				'maxsize_unit'		=> 1048576, # MB
				'media'				=> '',
				'media_propose'		=> '',
				'format'			=> '',
				'format_propose'	=> '',
				'artist'			=> '',
				'album'				=> '',
				'title'				=> '',
				'bitrate'			=> '',
				'network'			=> ''			
			);

			# is type defined?
			if (isset($options['type']) && strlen($options['type'])) {
				# awkward formatting to suit emh - make sure first letter is uppercase
				$options['type'] = ucfirst($options['type']);
				# still not matching?
			 	if (!array_key_exists($options['type'], $this->types)) {
					$this->message('Search request failed, unknown type: '.var_export($search['type'], true));
					return false;
				}

				$search['media_propose'] = $options['type'];
			}

			# submit the search
			$r = $this->curl(array(CURLOPT_URL => $this->getUrl().'submit?'.http_build_query($search)));
			if ($r === false) return false;

			# look for something that indicates a working search
			if (strpos($r, 'Sending query !!!') === false) {
				$this->message('Search request failed, unknown type: '.var_export($r, true));
				return false;
			}

			return $this->results();
		}
	}
?>
