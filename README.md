# The migration extension for Codeception (Yii2)

[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://raw.githubusercontent.com/iripvanwinkle/codeception-migration/master/LICENSE.md) [![Yii2](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat-square)](http://www.yiiframework.com/)

## Install

Via Composer

```bash
$ composer require iripvanwinkle/codeception-migration
```
## Config

 * `configFile` *required* - the path to the application config file. File should be configured for test environment and return configuration array.
 * `migrationPath` *required* - the path to your migrations folder. May use yii2 alias.
 * `entryUrl` - initial application url (default: http://localhost/index-test.php).
 * `entryScript` - front script title (like: index-test.php). If not set - taken from entryUrl.
 
## Usage

You can use this extension by setting params in your codeception.yml:

```yaml
  extensions:
      enabled:
          - Codeception\Extension\Migration
      config:
          Codeception\Extension\Migration:
              configFile: <path to the application config file>
              migrationPath: <path to your migrations (may use yii2 alias)>
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
