# piwigio-privacy
A small script and nginx configuration to increase the privacy of piwigo gallery

This repository contains a small script that will validate users before allowing
access to static files, so using it will actually protect your files from access by
anyone who is not allowed to view them.
unlike `action.php` this script will not serve the files back to the client
and instead use nginx for serving the file and works both on the original and derivatives.

_For more information check out my blog post about [Securing Private Piwigo Albums](https://ca.non.co.il/index.php/securing-private-piwigo-albums/)_

## file description
`auth.php` the auth validation script
`piwigo-nginx-site` file contains very basic nginx site configuration with auth_request redirects to the auth.php script
`default.png` a basic image that will be served instead of forbidden images

## Install
Since default nginx build does not enable the `auth_request` feature
the hardest thing in the installation process will be to compile [nginx source](http://nginx.org/en/download.html)
The actual process will be different depending on your distro and installed
modules but here is the flags I used -

```shell
./configure \
	--user=www-data                       \
	--group=www-data                      \
	--prefix=/etc/nginx                   \
	--sbin-path=/usr/sbin/nginx           \
	--conf-path=/etc/nginx/nginx.conf     \
	--pid-path=/run/nginx.pid             \
	--lock-path=/run/nginx.lock           \
	--error-log-path=/var/log/nginx/error.log \
	--http-log-path=/var/log/nginx/access.log \
	--with-http_gzip_static_module        \
	--with-http_stub_status_module        \
	--with-http_ssl_module                \
	--with-pcre                           \
	--with-file-aio                       \
	--with-http_realip_module             \
	--without-http_scgi_module            \
	--without-http_uwsgi_module           \
	--with-http_auth_request_module
```

You might need to install some libs (I was missing libssl-dev for openssl and libpcre3-dev for PCRE)
but if all goes well you can compile and install
```shell
make
sudo make install
```

copy `auth.php` and  `default.png` to the root of your `piwigo` folder
edit your nginx site file (usually under /etc/nginx/sites-available) to [auth_request](http://nginx.org/en/docs/http/ngx_http_auth_request_module.html)
requests to /upload and derivatives file locations, you can use `piwigo-nginx-site` file as is but you'll probably want
your actual nginx conf to be a bit more complex than that

restart nginx and you should be good to go

## Why this is not a plugin
I was trying to see how to achieve the same result as a plugin but that seems a bit useless since any way
I'll need to have a script that nginx can directly access for `auth_request` and since any way we need to change
nginx configuration I didn't see much point in having this as a plugin.