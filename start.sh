#!/bin/bash
ROOTTP="/teampass"
echo "Checking Teampass Files"
if [ -f $ROOTTP/www/index.php ] ;
then
	echo "Found Teampass in $ROOTTP.. Checking if it is configured ..." 
	if [ -f $ROOTTP/www/includes/settings.php ] ;
	then
		echo "Teampass seems to be configured ... good"
		rm -rf $ROOTTP/install
	else
		echo "Teampass will prompt for install parameters"
	fi
else
	echo "Seems it is the first time this Teampass is installed let's preconfigure files"
	mkdir -p $ROOTTP/{www,sk}
	cp -Rf ${ROOTTP}init/* $ROOTTP/www
	chown -Rf www-data.www-data $ROOTTP 
fi

/usr/sbin/apache2ctl -D FOREGROUND &

tail -f /var/log/apache2/*log
