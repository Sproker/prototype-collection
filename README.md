# prototype-collection
This repository contains all of the files used in the development of "Prototyping an Online Tool for Assessment of Computational Thinking".

Used Drupal version: https://www.drupal.org/project/drupal/releases/9.5.5

Used XAMPP version: https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.0.28/xampp-windows-x64-8.0.28-0-VS16-installer.exe/download

Used Docker Desktop: https://www.docker.com/products/docker-desktop/


All created/modified modules are located in the "modules" folder.
Other Drupal modules can be installed with composer, via copying the composer.json from this project to the Drupal folder.
Drupal can be pre-configured via copying the settings.php from this project to "{{ your_drupal_folder_name }}/sites/default" directory. However, the "$settings['config_sync_directory']" value should be copied from the settings.php file, which was created during the installation of Drupal.

Modified Learning Locker is located in the "LRS" folder.

By using the mentioned files and following the steps described in the thesis, it is possible to recreate the application.