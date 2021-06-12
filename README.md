# authneucore

DokuWiki [Neucore](https://github.com/bravecollective/neucore) auth plugin.

## Install

```
cd /path/to/lib/plugins
git clone https://github.com/bravecollective/authneucore.git
cd authneucore
composer install
cp config/config.dist.php config/config.php
```

Adjust values in config/config.php:
- Redirect URL is https://your.domain/lib/plugins/authneucore/core_success.php
- Use a SQLite database, e.g. `sqlite:/path/to/auth.sqlite`
