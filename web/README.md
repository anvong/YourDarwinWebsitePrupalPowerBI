Pre-requires:

1. MySQL 8.0.31 or higher
2. PHP 8.1.13 or higher
3. Composer 2.1.14 or higher
4. Drupal 9.5.5 or higher


## Installation
1. Create database: yourdarwindb
2. Import the database from the file: database_backup\yourdarwindb_NNN.sql (NNN is version note)
3. Pull the source code - develop branch into wahtever folder you want
4. Create a virtual host for the folder, for example: http://yourdarwindev.com. Then restart Wampp or Xampp
6. Open the file: web\config\settings.local.php and change the database connection information to match your local environment
7. At the root folder of the project, run the command: composer install to install all the dependencies
8. Access  http://yourdarwindev.com on your browser

## How to use drupal content creation with admin role
1. Access  http://yourdarwindev.com/user/login on your browser
2. Login with the account: admin/nimda@Cdu2023
3. Create content by clicking on Content -> Add content -> Basic page (static page) or Article (blog post). You can set URL alias for the page, for example: /about-us for about us page
4. Create menu by clicking on Structure -> Menu -> Add menu link. For example, you can create a menu link for the about us page with the title: About us


## Clear cached data

On admin page, go to Configuration -> Development -> Performance. Then click on the button: Clear all caches


## How to set a static page as home page

On admin page, go to Configuration -> System -> Site information. Then set the Home page to the static page you want to set as home page. For example: /home