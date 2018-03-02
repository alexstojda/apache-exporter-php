# apache-exporter-php

PHP port of [apache_exporter][]. Slightly more convenient, since it does not
require running the additional exporter process.

## Installation

```bash
$ composer require building5/apache-exporter-php
```

## Usage

Be sure to have the Apache server-status page enabled and configured.

Enable the status module using `a2enmod status`. It probably already has
a `status.conf` configured in `/etc/apache2/mods-enabled`, but if not, you'll
need that, too

```
<IfModule mod_status.c>
	<Location /server-status>
		SetHandler server-status
		Require local
	</Location>
	ExtendedStatus On

	<IfModule mod_proxy.c>
		# Show Proxy LoadBalancer status in mod_status
		ProxyStatus On
	</IfModule>
</IfModule>
```

You can test the status page by running `curl http://localhost/server-status?auto`
on the web server to see if it's service the status page.

### Simple usage

In your PHP application, or in whatever webroot you choose, create a
`metrics.php` script that calls `ApacheExporter\Exporter::simple()`. The use
of `.htaccess` to restrict access, add passwords, etc. is left as an exercise.

```php
<?php
ApacheExporter\Exporter::simple();
```

### Non-simple usage

If you have other stats you'd like to report, you can use the
`ApacheExporter\Exporter::export()` method to export stats to the registry
of your own choosing.

```php
<?php
use ApacheExporter\Exporter;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;

$registry = new CollectorRegistry(new APC());
$renderer = new RenderTextFormat();

Exporter::export($registry);

// You could register/set other stats, but with an APC backend, you'll usually
// do that from within your application. You don't want your metrics route to
// have to interact with other services or do a lot of work.

header('Content-type: ' . RenderTextFormat::MIME_TYPE);
echo $renderer->render($registry->getMetricFamilySamples());
```

# License

`apache-exporter-php` is licensed under the MIT license.

 [apache_exporter]: https://github.com/Lusitaniae/apache_exporter
