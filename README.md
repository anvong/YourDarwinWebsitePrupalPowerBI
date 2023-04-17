"# yourdarwinwebsite" 

this is a test site using drupal 9 php 8.1.3 and mysql database

1.	Create a new database:
Using CMD in Admin mode, change directory to c:\xampp\mysql\bin
Then run
mysql -u root -p<ROOT_PASSWORD>
Notes: password could be blank in xampp  

Then run this commend to create a database named “yourdarwindb”, run command
 
CREATE DATABASE yourdarwindb CHARACTER SET utf8 COLLATE utf8_general_ci;

2.	Mysql backup:
Using CMD in Admin mode, change directory to c:\xampp\mysql\bin
Then run:
mysqldump -u root -p  yourdarwindb > c:\ yourdarwin.sql
 
Database name is yourdarwindb
Backup file is c:\ yourdarwindb.sql
