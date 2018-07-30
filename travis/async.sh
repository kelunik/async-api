#!/usr/bin/env bash

mkdir ext-async
curl -LSs https://github.com/concurrent-php/ext-async/archive/loop.tar.gz | sudo tar -xz -C "ext-async" --strip-components 1
pushd ext-async
phpize
./configure
make install
echo "extension=async.so" >> "$(php -r 'echo php_ini_loaded_file();')"
popd
