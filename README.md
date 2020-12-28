Smart assets loader (compatible with Nette framework)
=====================================================

![Integrity check](https://github.com/baraja-core/assets-loader/workflows/Integrity%20check/badge.svg)

Asset loader is a simple library for automatically loading styles and scripts into your application.

This package solves:

- Registration and loading of global styles and scripts
- Definition of specific styles and scripts for specific pages so that a minimum of data is always transferred
- Automatic compilation of styles and scripts directly on the server via PHP to maintain maximum performance and ease of use
- Automatic cache management and invalidation

This whole package (as well as the rest of the []Baraja ecosystem](https://github.com/baraja-core)) has been designed for simple and elegant use. It solves most complex issues internally, so you can easily build large applications effortlessly.

📦 Installation & Basic Usage
-----------------------------

This package can be installed using [Package Manager](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you will have to install the package manually using this guide.

A model configuration can be found in the `common.neon` file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
$ composer require baraja-core/assets-loader
```

If the automatic installation fails or is not available, register the extension in your `common.neon` file:

```yaml
extensions:
   assetsLoader: Baraja\AssetsLoader\LoaderExtension
```

In the project's `common.neon` you have to define basic project assets. A fully working example of configuration can be found in the `common.neon` file inside this package. You can define the configuration simply using `assetsLoader` extension.

**Important:** Verify that your project `www/.htaccess` does not block the return of` css` and `js` files from PHP.

Basic usage
-----------

All styles and scripts are divided into 2 categories:

- Global (available for all pages or for a group of pages)
- Local (only available for one specific page / route)

Place the styles and scripts in the project directory `www/assets`. The internal structure can be arbitrary.

Within your project `common.neon` file, simply define the location of each asset.

> **TIP:**
>
> Assets can also be read via a URL from a CDN server. In this case, the CDN paths will be listed directly in the source code.
>
> This type of asset loading is suitable for files that do not change their content over time because they are not managed by the Assets loader.

Example of a basic definition:

```yaml
assetsLoader:
   routing:
      *:
         - https://unpkg.com/bootstrap/dist/css/bootstrap.min.css
         - css/global.css
         - js/global.js
      Front:Homepage:default:
         - js/welcome-form.js
      Front:Contact:default:
         - css/contact.css
         - js/contact.js
      Service:*:
         - css/service.css
```

Enforce format specification
----------------------------

**Important:**

The format of the file is derived automatically according to the suffix (for example `.css`). If for any reason (for example, when loading fonts from a CDN) you cannot specify a format, you can pass it as a key and value.

For example (the key is the path to the asset, the value of the field is a supported format):

```yaml
assetsLoader:
   routing:
      *:
         'https://fonts.googleapis.com/css?family=Hind+Siliguri|Poppins:700&display=swap': css
```

Routing rule
------------

The routing rule is specified either as an absolute routing path (based on [Nette routing](https://github.com/nette/routing) rules), but an asterisk symbolic path can also be used.

The `*` rule always matches everything (all modules, presenters and actions).

The `Front:*` rule matches all presenters and actions within the same module.

The `Front:Service:*` rule matches all actions within the same presenter.

The `Front:Service:default` rule matches only one specific action.

Query parameters in the URL and other parameters do not affect routing rules. Only the static route from the Nette router is evaluated. Routing information is cached to maintain the best performance.

Operation in the presenter
--------------------------

In BasePresenter, create an instance of the service, pass it to the template, where it can be easily rendered. The whole logic was kept so that it was enough to simply register the service and the internal logic worked automatically.

```php
abstract class BasePresenter extends \App\Presenters\BasePresenter
{
   /** @inject */
   public Api $assetsLoader;

   public function startup(): void
   {
      parent::startup();
      $this->template->assetsLoader = $this->assetsLoader->getHtmlInit($this->getAction(true));
   }
```

The `getHtmlInit()` method automatically returns the entire rendered header content as HTML. The `$this->getAction(true)` method is available directly in the Presenter and returns the current route as an absolute path.

Then simply type the contents of the header in `@layout.latte`:

```html
<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      {$assetsLoader ?? ''|noescape}
```

The rest works automatically.

Asset minification and compilation
----------------------------------

The package automatically compiles and minimizes all assets on the output.

Before returning an HTTP response, caching HTTP headers and other logic are automatically set to optimize retrieval. At the same time, the package contains ready-made automatic minifiers (services implementing the `Baraja\AssetsLoader\Minifier\AssetMinifier` interface), which can reduce the data size of CSS and JS files.

A modified version of the [JShrink](https://github.com/tedious/JShrink) library for PHP is used for minification, so you don't need any other applications on the server.

Cache handling
--------------

Before returning the rendered HTML to the header, the library automatically detects the time of the last change to any returned file. According to this change, a checksum is then calculated, which is passed as a query parameter with the version.

Change detection is performed in each request and only meta information from the filesystem is read (super fast method), therefore even if the file is changed directly on the server (or by some script), the cache is automatically invalidated immediately.

Adding a query parameter to the URL will cause a new asset to be downloaded (because the browser will not have the contents of the original file in its internal cache) and the assets will behave the original way again.

**Warning:** File content change detection is not performed for absolute URLs because real-time change cannot be detected.
