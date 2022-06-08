#!/bin/sh
if [ ! -d ${VOL}/.git ];
then
	echo "Initial setup..."
	if [ -z ${GIT_TAG} ]; then
	  #git clone $REPO_URL ${VOL} # Errors out due to directory not being empty
		git init
		git remote add origin $REPO_URL
		git pull
		git checkout master -f
	else
	  #git clone -b $GIT_TAG $REPO_URL ${VOL}
		git init
		git remote add origin $REPO_URL
		git pull
		git checkout $GIT_TAG -f
	fi
	mkdir ${VOL}/sk
	chown -Rf nginx:nginx ${VOL}
fi

if [ -f ${VOL}/includes/config/settings.php ] ;
then
	echo "Teampass is ready."
	rm -rf ${VOL}/install
else
	echo "Teampass is not configured yet. Open it in a web browser to run the install process."
	echo "Use ${VOL}/sk for the absolute path of your saltkey."
	echo "When setup is complete, restart this image to remove the install directory."
fi

# Pass off to the image's script
exec /start.sh

