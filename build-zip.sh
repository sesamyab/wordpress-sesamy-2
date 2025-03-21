#!/bin/bash

rm -rf dist
npm install
npm run build

mkdir sesamy-wordpress
# The --delete flag will delete anything in destination that no longer exists in source
rsync -rc --exclude-from="./.distignore" "./" sesamy-wordpress/ --delete --delete-excluded

cd sesamy-wordpress
composer update --no-dev --prefer-dist
rm -rf composer.json composer.lock

cd ..

zip -r "sesamy-wordpress.zip" sesamy-wordpress

# Clean up
rm -rf sesamy-wordpress
