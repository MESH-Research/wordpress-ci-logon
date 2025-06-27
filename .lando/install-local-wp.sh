#!/bin/sh
cd .lando
rm -rf wordpress
curl -O https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
rm latest.tar.gz
cd ..