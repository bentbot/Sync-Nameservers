IP=`cat ./lastip.txt`;
#echo $IP;
NEWIP=`curl -L --silent http://cpanel.net/showip.cgi`
#echo $NEWIP;
if [ -z "${NEWIP}" ]; then
echo 'No new IP found. Internet connection ERROR';
echo 'Exiting...';
exit 0;
fi
if [ -z "${IP}" ]; then
echo 'No old IP found. Please add the existing NS IP address to ./lastip.txt';
echo 'Exiting...';
exit 0;
fi
if [ $NEWIP == $IP ]; then
echo $IP;
echo 'OK - DNS up to date.';
echo 'Exiting...';
exit 0;
fi
echo $NEWIP;
echo 'New IP Detected. Reloading DNS...';
php ./autoip.php;
cp /var/named/*.db ./dnsbak2;
cp /var/named/*.db ./dnsbak;
rm /var/cpanel/nameserverips.cache;
sed -i "s/$IP/$NEWIP/g" ./dnsbak/*;
sed -i "s/$IP/$NEWIP/g" /var/cpanel/nameserverips.yaml;
find ./dnsbak/*.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;\s+serial/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
find ./dnsbak/*.db -mtime -1 -exec perl -pi -e 'if (/^\s+(\d{10})\s+;Serial Number/i) { my $i = $1+1; s/$1/$i/;}' '{}' \;
\cp ./dnsbak/*.db /var/named/;
chown named:named /var/named/*.db;
chmod +rwx /var/named/*.db;
rm ./lastip.txt;
echo $NEWIP > ./lastip.txt;
/usr/local/cpanel/scripts/updatenameserverips;
service named restart;
service cpanel restart;
echo 'DNS Reloaded.';
