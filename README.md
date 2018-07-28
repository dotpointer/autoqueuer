# Autoqueuer

Manage downloads automatically.

## Purpose

The traditional way to find new content usually consists of doing 
a single regular search, manually traversing the returned search 
results, finding something you want, queueing it for download, 
waiting for it to complete and then enjoying the newly fetched content.

This is all fine and dandy for those spontaneous searches.

And every time you want to check for new content you will 
have to redo your search.

But what if you don't want to spend time redoing your searches?
What if you could subscribe on searches?

This is where Autoqueue comes in.

## What it does

Autoqueue communicates directly with your download application, 
like you do, but automatically.

It carries out user defined searches repeatedly on scheduled 
and randomized basis, analyzes search results, compares with 
previous downloads and criterias, queues matching content for 
download, waits for them to complete and then moves them to 
selected locations. You can even get a mail when new files 
has arrived.

## Benefits

* Do searches on times while you're away from the host to 
  reach sources on other time zones that usually are offline
  when you are online and vice versa

* Subscribe on content by repeating the searches

* Get files sorted after they have completed

* Get mail reports about fetched files

* Get preview images of content being downloaded

* Does not queue already downloaded content

## How it works

The service is executed by a cronjob.

This service (and the GUI to manage it) communicates with your 
download application through the HTTP interface supplied by 
your download application.

# Requirements / tested on

-  MariaDB (or MySQL)
-  PHP 5.6 / 7.0 with cURL, MySQL/MariaDB
-  ffmpeg (to get previews)
-  nginx (or other web server)
-  MLDonkey (or eMuleXtreme, but support is a bit outdated)

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development and testing purposes. See deployment for notes on
how to deploy the project on a live system.

### Prerequisites

What things you need to install the software and how to install them

```
- Debian Linux 9 or similar system
- nginx
- MariaDB (or MySQL)
- PHP
- PHP-FPM
- PHP-MySQLi
- PHP-MBstring
```

Setup the nginx web server with PHP-FPM support and MariaDB/MySQL.

In short: apt-get install nginx mariadb-server php-fpm php-mysqli php-mbstring
and then configure nginx, PHP and setup a user in MariaDB.

### Installing

Head to the nginx document root and clone the repository:

```
cd /var/www/html
git clone https://gitlab.com/dotpointer/autoqueuer.git
cd autoqueuer/
```

Import database structure, located in sql/database.sql

Standing in the project root directory login to the database:

```
mariadb/mysql -u <username> -p

```

If you do not have a user for the web server, then login as root and do
this to create the user named www with password www:

```
CREATE USER 'www'@'localhost' IDENTIFIED BY 'www';
```

Then import the database structure and assign a user to it, replace
www with the web server user in the database system:
```
SOURCE sql/database.sql
GRANT ALL PRIVILEGES ON autoqueuer.* TO 'www'@'localhost';
FLUSH PRIVILEGES;
```

Fill in the configuration in include/setup.php.

Head to the page and setup your download client program (pump).

You also need to add a cron job to perform the searches regularly.

If you run as root, open /etc/crontab add add something like this to run 
it every 15 minutes (everything should go on one line, remove - "\"):

0,15,30,45 * * * * root /usr/bin/php /var/www/html/autoqueuer/worker.php \
--move --search

## Authors

* **Robert Klebe** - *Development* - [dotpointer](https://gitlab.com/dotpointer)

See also the list of
[contributors](https://gitlab.com/dotpointer/autoqueuer/contributors)
who participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details.

Contains dependency files that may be licensed under their own respective
licenses.
