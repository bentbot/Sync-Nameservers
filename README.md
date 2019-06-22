# SyncNameservers
Automatically synchronize your nameservers to your WHM/cPanel server on a dynamic / non-static IP address.

## Instructions:

1. Modify the `ip_addr.php` file to include your WHM/cPanel API & Name.Com API keys.
2. Change the domain and nameservers to your own when in `ip_addr.php`
3. Add the following to your Cron tab:
````
*/2 * * * * php /home/<host>/scripts/ip_addr.php
@reboot php /home/<host>/scripts/ip_addr.php
````
