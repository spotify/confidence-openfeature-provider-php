A very basic PHP provider for Confidence.

There is currently no error or type checking done and no unit testing.

Usage:
* Add your client ID into the `example.php` file (and update the flag and flag type if necessary).
* Run the script: `php example.php`

Developer Setup:
- Install PHP: `brew install php`
- Install Composer: https://getcomposer.org/download/ (see below for instructions as of writing this)
- Install project dependencies: `composer install`

You should now be able to run the example script.

Composer setup:
```php
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

sudo mv composer.phar /usr/local/bin/composer
```