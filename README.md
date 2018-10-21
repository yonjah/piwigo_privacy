# piwigio_privacy
A plugin to increase the privacy of Piwigo gallery

Piwigo privacy will validate users before allowing
access to Piwigo images and other uploaded files.

It has two modes of operations
- Naive - all images are directed to `get.php` where access is analyzed derivative are created and files are served
- Advanced - Using a custom web server configuration (see [piwigo-nginx-site](piwigo-nginx-site) for NGINX sample) `get.php` will validate files and create derivative but will pass the actual serving of files to the web server.

*Notice* that with both modes you need to make sure your static file folders are blocked from public web access
You should also disable `i.php` script since it sends files back to the client without validating the permissions.
You can look at [piwigo-nginx-site](piwigo-nginx-site) which does all of that.

## Install
Clone the repository to your in your Piwigo plugins folder (should create the path `plugins/piwigo_privacy`)
in Piwigo config file (`local/config/config.inc.php`) set `derivative_url_style` config to `1` -

```php
$conf['derivative_url_style']=1;
```

Go to Piwigo admin panel and under plugins management activate `piwigo_privacy`

If you want to use *advance mode* with *NGINX* also set the config -

```php
$conf['piwigo_privacy_redirect_header'] = 'X-Accel-Redirect';
```

Using a different server that supports `X-send-files` might be possible by settings the headers accordingly but this plugin was only tested with NGINX. if you get it to work with other servers a pull request with a sample configuration would be appreciated.

If your file names or paths contain spaces you might need to allow whitespace in file names

_Please Note_ that some web apps might be volnurabe to code and command injections by passing weird formatted file names
I am currently not aware of any such vulnerabilities in this plugin or in piwigo but allowing whitespaces might increase
the likelihood of such attack (so only enable this config if the plugin is blocking access to such images where it shouldn't)

```php
$conf['piwigo_privacy_allow_whitespaces'] = true;
```


## Disclaimer
This plugin is a lot more complex than my initial implementation.

I can assume many performance improvements can be made since performance wasn't one of my main goals in implementing it.

Though I hope it improves the privacy of you Piwigo install you should be aware that Piwigo security implementations are very naive and proper Documentation is scares, I tried to do my best so this plugin won't introduce any new security issues and hopefully help to mitigate any existing ones but it still might be possible to bypass this plugin and get access to images (With my previous implementation for example I was completely unaware that Piwigo built in `i.php` does not validate user permissions when serving images).


_For more information about the idea behind this plugin and the previous implementation check out my blog post about [Securing Private Piwigo Albums](https://ca.non.co.il/index.php/securing-private-piwigo-albums/)_
