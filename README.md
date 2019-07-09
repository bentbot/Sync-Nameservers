# Sync Nameservers
AKA: __ipaddr.php__

Automatically synchronizes cPanel & your ns1/ns2 nameservers with your WHM/cPanel server on a dynamic / non-static IP address.

This is a simple script to update cPanel with the correct new IP address when it has changed or you are not able to host on a static IP.
It also works with NAME.COM domain name registrar to update your service's nameservers to your new public IP address.

In the script there is an array of subdomains containing what are cPanel's default records. These are updated to the correct public IP address after you have configured the script. Additional subdomains can be added there to create new zones or update custom zones.

## Instructions:

1. Modify the `ip_addr.php` file to include your WHM/cPanel API & Name.Com API keys.
2. Change the domain and nameservers to your own when in `ip_addr.php`
3. Login to your cPanel installation as 'root' or another user and upload the script to a non-public dir.
4. Add the following to your Cron tab:
````
*/2 * * * * php /root/<path>/ip_addr.php
@reboot php /root/<path>/ip_addr.php
````
