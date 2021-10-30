#find /srv/scripts/dnsbak/YOUR_MAIN_DOMAIN.com.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;Serial Number/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
IP=`cat /srv/scripts/lastip.txt`;
#echo $IP;
NEWIP=`curl -L --silent http://cpanel.net/showip.cgi`
#echo $NEWIP;
FILE=/var/named/YOUR_MAIN_DOMAIN.com.db;
if [ ! -f "$FILE" ]; then
echo 'Restoring DNS Mainfile...';
sed -i "s/PUBLICIPADDR/$NEWIP/g" /srv/scripts/sc;
\cp /srv/scripts/sc /var/named/YOUR_MAIN_DOMAIN.com.db;
fi
if [ -z "${NEWIP}" ]; then
echo 'No new IP found. Internet connection ERROR';
echo 'Exiting...';
exit 0;
fi
if [ -z "${IP}" ]; then
echo 'No old IP found. Please add the existing NS IP address to /srv/scripts/lastip.txt';
echo 'Exiting...';
exit 0;
fi
if [ $NEWIP == $IP ]; then
echo $IP;
echo 'OK - DNS up to date.';
service named restart;
echo 'Exiting...';
exit 0;
fi
echo $NEWIP;
echo 'New IP Detected. Reloading DNS...';
#sed -i "s/PUBLICIPADDR/$NEWIP/g" /srv/scripts/sc;
#\cp /srv/scripts/sc /var/named/YOUR_MAIN_DOMAIN.com.db;
php /srv/scripts/namedotcomapi/autoip.php;
cp /var/named/*.db /srv/scripts/dnsbak2;
cp /var/named/*.db /srv/scripts/dnsbak;
rm /var/cpanel/nameserverips.cache;
sed -i "s/$IP/$NEWIP/g" /srv/scripts/dnsbak/*;
sed -i "s/$IP/$NEWIP/g" /var/cpanel/nameserverips.yaml;
find /srv/scripts/dnsbak/*.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;\s+serial/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
find /srv/scripts/dnsbak/*.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;Serial Number/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
find /srv/scripts/dnsbak/YOUR_MAIN_DOMAIN.com.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;Serial Number/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
\cp /srv/scripts/dnsbak/*.db /var/named/;
chown named:named /var/named/*.db;
chmod +rwx /var/named/*.db;
rm /srv/scripts/lastip.txt;
echo $NEWIP > /srv/scripts/lastip.txt;
/usr/local/cpanel/scripts/updatenameserverips 2>&1;
service named restart;
service cpanel restart;
echo 'DNS Reloaded.';
echo 'Waiting 1m...';
sleep 1m;
echo 'Rebooting Nameservers';
service named restart;
