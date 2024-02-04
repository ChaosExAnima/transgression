#!/bin/bash

set -ex

yarn js

rsync -iavzrhu --delete --checksum --exclude='*.ts' \
	-e 'ssh -p 2222 -i ~/.ssh/keys/transgression-backup' \
	theme/ transgression@central:/var/www/html/wp-content/themes/transgression "$@"

rsync -iavzrhu --delete --checksum --exclude='*.ts' \
	-e 'ssh -p 2222 -i ~/.ssh/keys/transgression-backup' \
	plugin/ transgression@central:/var/www/html/wp-content/plugins/transgression "$@"
