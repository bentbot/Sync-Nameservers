# Sync Nameservers

__Warning::__ Please continue just if you agree that running this tool can either automatically fix DNS resolution errors *or* it may completely screw-up / destroy your DNS records, leaving visitors permanantly unable to access any site hosted on your cPanel / WHM server! Without a proper backup all active DNS records could be destroyed, in a power outtage or application crash, leaving your server temporarily unavailable.

## Version 2

1. 
   - Setup by running the command as root on your WHM cPanel server. 
       - `[root@cpanel]$ mkdir dnsbak;mkdir dnsbak2;chmod +rw ./*.txt;chmod -fR +rwx ./dnsb*;chmod +x ./dnsu*;`
   - Add a cronjob to run each & every minute throughout the day. 
   - Point it to the absolute path of the `update_dns.sh` script in this repository.
 ````
  [user@cpanel]$ pwd ./dnsupdate.sh
  [root@cpanel]$ sudo crontab -e
  * * * * * /srv/scripts/dnsupdate.sh > /dev/null 2>&1
  crontab: installing new crontab
````

2. 
   - Open the file `./autoip.php` and change the needed configuration variables.
   - Change the NAME.COM API credentials to relect your own ns1 & ns2 domain registrar account.
   - If needed, replace the bottom section of the file with your own API script, one that suits your registrar.
   - Optional: Disable either NS1 / NS2 to make this server behave as a singular nameserver =S


3. Done! When your non-static IP changes your server will now know the new one. 

## Troubleshooting

Open cPanel WHM and double-check the following IP settings, making sure they are set to the current IP address you see when the script runs.

__Troubleshooting Procedure__

  - Try running manually: `[root@cpanel]$ ./dnsupdate.sh` Copy the IP new address.
  - Log in to WHM and browse to __Basic WebHost Manager® Setup__ > Find: '__ip__'
      - Change the address in the first box: __Basic Config__ > __IP__ To the new IP if different.
      - Click the __Configure Address Records__ in the __Nameservers__ section at the bottom.
      - Paste the IP into the __NS1 IPv4 A record__ input box. Press __Configure Address Records__.
      - Optional: __Configure Address Records__ again for __NS2__ if the IP address for NS2 isn't the same address.
  - If you are still unable to resolve your domains, continue to __Restore the Backup__:
          - Check the folders `./dnsbak` for the NS records prior to IP address modification. (NOT NEW)
          - The folder `./dnsbak2` contains the up-to-date NS records which should include the new IP address.
          - Compare the Name.db docs for flaws in syntax such as a missing IP address in the 'A record' entries.
  - If you are still unable to resolve the error, use WHM to __Reset All DNS Zones__. Resulting in a __loss of all__ customized records in WHM. Including but not limited to: MX Mail settings, external NS records, A redirects, custom TXT records, and more... Only Reset All DNS zones in an emergency. Use the `./dnsbak` folders to scrape custom records and restore them to each `/var/named/domain.tld.db` files. 
  - Set the global TTL (Time to Live) and the one in zones to a short interval. Like to 60 or 300.
          - 1. Use SSH `[root@cpanel]$ nano /var/named/domain.tld.db`
          - 2. Login to WHM and go to __Basic WebHost Manager® Setup__ > Find: '__ttl__'
  - When finished with a change, make sure to run: `[root@cpanel]$ service named restart` and *clear your browser cache.*

## Version 1

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
5. At the very bottom of `ipaddr.php`, change the argument to 'false' to only update NS recoreds remotely.
