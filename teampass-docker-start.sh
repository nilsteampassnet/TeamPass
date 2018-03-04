#!/bin/sh
if [ ! -d ${VOL}/www ];
then
	echo "Initial setup..."
	git clone $REPO_URL ${VOL}/www
	mkdir ${VOL}/sk
	chown -Rf nginx:nginx ${VOL}
fi

if [ -f ${VOL}/www/includes/config/settings.php ] ;
then
	echo "Teampass is ready."
	rm -rf ${VOL}/www/install
else
	echo "Teampass is not configured yet. Open it in a web browser to run the install process."
	echo "Use ${VOL}/sk for the absolute path of your saltkey."
	echo "When setup is complete, restart this image to remove the install directory."
fi

# Pass off to the image's script
exec /start.sh