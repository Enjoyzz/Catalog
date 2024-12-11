1. Added to doctrine config in `/di/doctrine.php`

```php
use Gedmo\Tree\TreeListener;

$evm = new EventManager();
$treeListener = new TreeListener();
$evm->addEventSubscriber($treeListener);
```
1.2 Added to root composer.json to extra->patches section

```shell
 composer require cweagans/composer-patches:^1.7
```
```json
    "extra": {
        "patches": {
            "gedmo/doctrine-extensions": [
                "modules/catalog/patches/gedmo-doctrine-extensions-src-tool-wrapper-entitywrapper-php.patch"
            ]
        }
    }
```

2. Define in DI `\Psr\EventDispatcher\EventDispatcherInterface`

3. Diff migration, migrate and clear cache-metadata

```shell
composer diff
composer migrate
```

4. Migration function

```shell
./vendor/bin/doctrine-migrations  migrations:generate
```

```php
//for Mysql insert to migration

$this->addSql(<<<SQL
CREATE FUNCTION CONVERT_PRICE(
    `price` INT,
	`main_currency` VARCHAR(3),
	`convert_currency` VARCHAR(3)
) RETURNS double
    DETERMINISTIC
BEGIN
	IF (main_currency = convert_currency)
	THEN
		SET @result = price;
	ELSE
		SET @rate = (SELECT rate FROM catalog_currency_rate WHERE
			main = main_currency
			AND `convert` = convert_currency
		);
		SET @result = price * @rate;
	END IF;
RETURN @result;

END;
SQL);
```

```shell
composer migrate
```

5. Setting elfinder 


# Options
- `disableEditProductCode` **bool**
- `allowedPerPage` **int**
- `productImageStorage` **string**
- `storageUploads` **array**
- `productFileStorage` **string**
- `thumbnailService` **array**
- `allowedPerPage` **int[]**
- `sort` **string**
- `minSearchChars` **int**
- `showSubcategoryProducts` **bool**
- `delimiterOptions` **string**
- `allocatedMemoryDynamically` **bool**
- `currency` **array**
  - `default` **string**
  - `ratio` **array**
- `admin` **array**
  - `template_dir` **string**
  - `searchFields` **array**
  - `editor` **array**
    - `productDescription`
    - `categoryDescription`
    - `categoryShortDescription`
- `moveProductsOutOfStockToEnd` **bool** Показывает товары не в наличии в самом конце списка

