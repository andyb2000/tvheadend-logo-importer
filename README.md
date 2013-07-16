tvheadend-logo-importer
=======================

Automagic logo import tool for TVHeadend

This is a very simple PHP5 script that will read from your TVHeadend channels folder
and then query the XMLTV webpages to try and retrieve channel icons.

To run, simply call the script
./tvheadend-logo-importer.php5

(You will need php5-cli installed)

If it cannot determine your TVHeadend home folder you must pass it as the first parameter:
./tvheadend-logo-importer.php5 /home/tvheadend/.hts/tvheadend/

The script will be DEFAULT not update existing icons that are setup. It will only add
new icons. To change that behaviour edit the script and change the line:

$channel_permit_update=0;

To

$channel_permit_update=1;

