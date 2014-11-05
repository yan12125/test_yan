# Introduction
This is Chanllenge 2147483647, a spammer on facebook

# Prerequisites

* a web server supports PHP. Apache 2.4.x is recommended
* A MySQL database
* Windows is theoretically possible, but Linux is recommended

# Installation
This project uses bower to control javascript libraries and composer to control php libraries. To install all required libraries, use the following commands: 
<pre><code>$ bower install
$ composer install
</code></pre>

# Database settings

1. First, create a database named test_yan
2. Import test_yan.sql. Please mail me for the file.
3. Create a user test_yan and grant him at least SELECT, INSERT, UPDATE, and DELETE permissions

# Troubleshoot

* If you see the following error:  
```
Cannot load from mysql.proc. The table is probably corrupted
```
Use the following command to fix it:  
```
mysql_upgrade
```
Prepend the command with su or sudo if you are not root
