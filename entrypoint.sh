#!/bin/bash

composer install
/wait

vendor/bin/phinx migrate --environment=production
php -S 0.0.0.0:5000