#!/bin/bash

set -ex

rsync -iavzrhu --delete --checksum \
	-e 'ssh -p 2222 -i ~/.ssh/keys/transgression-backup' \
	src/ transgression@central:/var/www/html/wp-content/themes/transgression "$@"

rsync -iavzrhu --delete --checksum \
	-e 'ssh -p 2222 -i ~/.ssh/keys/transgression-backup' \
	plugin/ transgression@central:/var/www/html/wp-content/plugins/transgression "$@"
