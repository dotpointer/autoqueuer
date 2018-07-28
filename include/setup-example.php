<?php
 
  # setup file

  # changelog
  # 2017-09-19 20:48:00 - initial version, extracted from functions
  # 2018-07-11 18:42:40
  # 2018-07-13 19:31:26 - indentation change, tab to 2 spaces

  # project name
  define('PROJECT_FILENAME', 'autoqueuer');
  define('PROJECT_TITLE', 'Autoqueuer');

  # database setup
  define('DATABASE_HOST', 'localhost');
  define('DATABASE_USERNAME', 'www');
  define('DATABASE_PASSWORD', 'www');
  define('DATABASE_NAME', PROJECT_FILENAME);

  # file to store eMule web session id in, must be writable
  define('FILE_SESSION', '/var/cache/'.PROJECT_FILENAME.'.emule.session');

  # tree root where to mount samba shares - ends with a slash
  define('MOUNT_ROOTPATH', '/mnt/'.PROJECT_FILENAME.'/');

  # folder to store preview files, must be read and writeable by www-data user
  define('PREVIEW_DIR', '/examplehost/download/'.PROJECT_FILENAME.'-preview/');

  # eMule setup - eMule MUST have special XML-template loaded as web interface
  # define('EMULEHOST', 'hostname');
  # define('EMULEWEBURL', 'http://'.EMULEHOST.':4711/');

  # bad words detected in filenames
  $blacklist = array('.exe','.zip', '.com', '.rar');

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

  # compose a random string here of more than 16 characters
  $password_salt = '';

  # use this to create a user to login with
  #$editusers = array(
  #	array(
  #		'username' => 'test',
  #		'password' => 'test'
  #	)
  #);
?>
