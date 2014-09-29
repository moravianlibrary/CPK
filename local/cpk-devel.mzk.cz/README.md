Installation
------------

Create cache folder & its subfolders and allow server write there (sudo needed):
```
mkdir -p cache/configs && sudo chown www-data:www-data cache -R
```

Make server configuration file:
```
cp httpd-vufind.conf.example httpd-vufind.conf
cd ../..
export CWD=$(pwd)
sed "s_/path/to/your/VuFind/_$CWD_g" local/cpk-devel.mzk.cz/httpd-vufind.conf
```

Link your server to this folder (sudo needed):
```
sudo ln -sf $CWD/local/cpk-devel.mzk.cz/httpd-vufind.conf/etc/apache2/conf.d/vufind.conf
```

Now configure your local settings in config/vufind -> config.local.ini:
```
cd local/cpk-devel.mzk.cz/config/vufind
cp config.local.example.ini config.local.ini
```
Open it in your favorite editor & set local values (database, solr & XCNCIP2 are mandatory)
