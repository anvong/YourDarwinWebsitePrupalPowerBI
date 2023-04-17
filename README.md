Localhost setup guideline: https://charlesdarwinuni.sharepoint.com/:w:/r/teams/Prt583398/Shared%20Documents/General/DevelopmentEnv/00_DevelopmentEnvironmentSetup.docx?d=w449a62dee02242b5961c0b4aed36c6cc&csf=1&web=1&e=qhfJxh

Inside this document, I also add some guideline for the backup and restore of the database in mysql termnial.

This document is the guideline for project team: • Localhost setup for drupal • MYSQL Database create/backup/restore

Localhost runs on local PC: XAMPP https://www.apachefriends.org/download.html
Chose PHP 8.2

Install composer: https://getcomposer.org/download/
Download > Install > Restart PC

Create Drupal project from composer:
composer create-project drupal/recommended-project yourdarwin

At the first time, you may face the Drupal project creation with the message below

The error points that the XAMPP server is not enabled the extension named “gd” Solutions: Open PHP.ini file in C:\xampp\php\php.ini

Drupal installed by composer:

Run your website “yourdarwin”

Create a mysql database named “yourdarwin” Start XAMPP’s Apache and mysql service Go to browser and navigate to the link: http://localhost
Go to phpMyAdmin to create a database

Then go to http://localhost/my_drupal/yourdarwin then select web to have the database configuration

Chose continue any way to skip the warning:

My local environment: User: admin Password: nimda@Cdu2023 Email: s354803@students.cdu.edu.au

Final step: check your web

 

Create a new database: Using CMD in Admin mode, change directory to c:\xampp\mysql\bin Then run mysql -u root -p<ROOT_PASSWORD> Notes: password could be blank in xampp
Then run this commend to create a database named “yourdarwin”, run command create database yourdarwin;

Mysql backup: Using CMD in Admin mode, change directory to c:\xampp\mysql\bin Then run: mysqldump -u root -p yourdarwin > c:\ yourdarwin.sql
Database name is yourdarwin Backup file is c:\ yourdarwin.sql

MySQL Restore (entire database)
Go to mysql/bin location by CMD admin mode then run the command below: mysql -u root -p yourdarwin < c:\yourdarwin.sql