<?php
# control panel

# tactic for direct communication with eMule is to
# send as much raw data as possible directly to the client
# casting and removal of data is ok though

# changelog
# 2014-07-12 16:56:57 - updating for v2
# 2014-07-24 12:53:08
# 2015-07-27 02:24:00 - adding email
# 2015-08-21 11:59:47 - cleanup
# 2015-08-23 12:54:18
# 2016-02-23 13:42:47 - replacing email parameter with multiple email parameters
# 2016-02-23 15:44:14 - translations
# 2016-03-05 22:16:09 - cleanup
# 2017-07-31 14:22:52 - adding nickname
# 2017-09-10 23:45:00 - newline removed
# 2017-09-12 22:27:00 - dropping project name in file
# 2017-09-22 00:08:00 - adding redownload
# 2018-03-22 01:53:00 - adding version parameter and no-referrer meta tag
# 2018-07-11 18:37:00 - adding login
# 2018-07-12 19:53:00 - updating jquery from 1.8.3 to 3.3.1
# 2018-07-13 19:31:26 - indentation change, tab to 2 spaces
# 2018-11-16 21:20:00 - adding sendmail setting
# 2018-12-20 18:25:00 - moving translation to Base translate

session_start();

require_once('include/functions.php');

start_translations(dirname(__FILE__).'/include/locales/');

$opacity_lower_level = 0.4;

# parameters
$action 					= isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
$email_address 				= isset($_REQUEST['email_address']) ? $_REQUEST['email_address'] : '';
$email_enabled 				= isset($_REQUEST['email_enabled']) ? $_REQUEST['email_enabled'] : '';
$email_timeout 				= isset($_REQUEST['email_timeout']) ? $_REQUEST['email_timeout'] : '';
$executiontimeout 			= isset($_REQUEST['executiontimeout']) ? $_REQUEST['executiontimeout'] : 21600; # 6 hrs
$executiontimeoutbase 		= isset($_REQUEST['executiontimeoutbase']) ? $_REQUEST['executiontimeoutbase'] : 21600; # 6 hrs
$executiontimeoutrandbase	= isset($_REQUEST['executiontimeoutrandbase']) ? $_REQUEST['executiontimeoutrandbase'] : 10800; # 3 hrs
$extension 					= isset($_REQUEST['extension']) ? $_REQUEST['extension'] : '';
$filehash 					= isset($_REQUEST['filehash']) ? $_REQUEST['filehash'] : '';
$find 						= isset($_REQUEST['find']) ? $_REQUEST['find'] : '';
$format 					= isset($_REQUEST['format']) ? $_REQUEST['format'] : '';
$host 						= isset($_REQUEST['host']) ? $_REQUEST['host'] : '';
$id 						= isset($_REQUEST['id']) ? $_REQUEST['id'] : false;
$id_clientpumps 			= isset($_REQUEST['id_clientpumps']) ? $_REQUEST['id_clientpumps'] : false;
$id_files 					= isset($_REQUEST['id_files']) ? $_REQUEST['id_files'] : array();
$id_searches 				= isset($_REQUEST['id_searches']) ? $_REQUEST['id_searches'] : false;
$logintype					= isset($_REQUEST['logintype']) ? $_REQUEST['logintype'] : false;
$method 					= isset($_REQUEST['method']) ? $_REQUEST['method'] : '';
$movetopath 				= isset($_REQUEST['movetopath']) ? $_REQUEST['movetopath'] : '';
$nickname 					= isset($_REQUEST['nickname']) ? $_REQUEST['nickname'] : '';
$password 					= isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
$password_retype			= isset($_REQUEST['password_retype']) ? $_REQUEST['password_retype'] : '';
$path_incoming 				= isset($_REQUEST['path_incoming']) ? $_REQUEST['path_incoming'] : '';
$port 						= isset($_REQUEST['port']) ? $_REQUEST['port'] : 0;
$redownload					= isset($_REQUEST['redownload']) ? $_REQUEST['redownload'] : false;
$sendmail 					= isset($_REQUEST['sendmail']) ? $_REQUEST['sendmail'] : false;
$search 					= isset($_REQUEST['search']) ? $_REQUEST['search'] : false;
$show_download 				= isset($_REQUEST['show_download']) ? (int)$_REQUEST['show_download'] : 0;
$sizemax 					= isset($_REQUEST['sizemax']) ? $_REQUEST['sizemax'] : 0;
$sizemin 					= isset($_REQUEST['sizemin']) ? $_REQUEST['sizemin'] : 0;
$status 					= isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
$type 						= isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$username 					= isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
$version					= isset($_REQUEST['version']) ? (int)$_REQUEST['version'] : 1;
$view 						= isset($_REQUEST['view']) ? $_REQUEST['view'] : '';

if ($view === '' && !is_logged_in()) {
  $view = 'login';
}

# get actions
require_once('index_action.php');

# get views
require_once('index_view.php');

$clientpumptypes = array();
# walk client pumps to get pump types, used in select box
foreach ($clientpumpclasses as $k => $v) {
  $clientpumptypes[$k] = $k;
}

?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <meta name="referrer" content="no-referrer" />

  <title><?php echo is_logged_in() ? PROJECT_TITLE : t('Login'); ?></title>

  <link rel="stylesheet" href="include/screen.css" type="text/css" media="screen" />

  <script type="text/javascript" src="include/jquery-3.3.1.min.js"></script>
  <script type="text/javascript" src="include/highcharts.js"></script>
  <script type="text/javascript" src="include/exporting.js"></script>

  <script type="text/javascript">
    var
<?php
if (is_logged_in()) {
?>
      clientpumptypes = <?php echo json_encode($clientpumptypes)?>,
      methods = <?php echo json_encode($methods)?>,
      types = <?php echo json_encode($types)?>,
<?php
}
?>
      view = '<?php echo $view?>';
  </script>
  <script type="text/javascript" src="include/load.php?nocache=<?php echo time();?>"></script>
<?php
if (is_logged_in()) {
?>
  <script type="text/javascript">
    e.logmessage_type_descriptions_short = <?php echo json_encode($logmessage_type_descriptions_short)?>;
  </script>
<?php
}
?>
</head>
<body>
  <div id="main">
<?php
if (is_logged_in()) {
?>
    <div id="header">
      <img src="img/pumpjack.png" id="logo" alt="<?php echo PROJECT_TITLE ?>" />
      <h1>
        <a href="?"><?php echo PROJECT_TITLE ?></a>
      </h1>
      <div id="subtitle"><?php echo t('Control Panel'); ?></div>
    </div>
<?php
}
?>
    <div id="menu">
      <ul>
<?php
if (is_logged_in()) {
?>
        <li>
          <a href="?view=quickfind"><?php echo t('Find'); ?></a>
        </li>
        <li>
          <a href="?view=transfers"><?php echo t('Transfers'); ?></a>
        </li>
        <li>
          <a href="?view=searches"><?php echo t('Schedule'); ?></a>
        </li>
        <li>
          <a href="?view=clientpumps"><?php echo t('Pumps'); ?></a>
        </li>
        <li>
          <a href="?view=log"><?php echo t('Log'); ?></a></li>
        <li>
          <a href="?view=latest_queued"><?php echo t('Queued'); ?></a>
        </li>
        <li>
          <a href="?view=dumped"><?php echo t('Dump'); ?></a>
        </li>
        <li>
          <a href="?view=parameters"><?php echo t('Parameters'); ?></a>
        </li>
        <li>
          <a href="?view=logout"><?php echo t('Logout'); ?></a>
        </li>
<?php
} else {
?>
        <li>
          <a href="?view=login"><?php echo t('Login'); ?></a>
        </li>
<?php
}
?>
      </ul>

<?php
if (is_logged_in()) {
?>
      <div id="findbox">
        <input type="text" id="find" name="find" placeholder="<?php echo t('Find in file database'); ?>">
      </div>
<?php
}
?>
      <div class="clear_both"></div>
    </div>
    <div id="content"></div>
  </div>
</body>
</html>
