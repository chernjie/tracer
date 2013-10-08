tracer
======

PHP Runtime Tracer for debugging

### Usage

1. require or autoload `Tracer` at the top of your execution scripts, usually `index.php`. Then initialize it. <br /> Example:
```
require_once 'tracer.php';
Tracer::init();
```

2. Start adding data into `Tracer`
```
Tracer::add('name', 'any type of data');
```

3. Upon page exit, `Tracer::__destruct` will be called to show your desired code trace. <br /> Example: <br />
<img src="http://content.screencast.com/users/chernjie/folders/Jing/media/d2759ee5-679b-4cf2-92ea-0d53f971ff9c/00000009.png" width="290" />

### Requirement

1. [xdebug](http://xdebug.org/) for code coverage [OPTIONAL]

### Alternatives

Other xdebug tracer tools you could consider:

1. https://github.com/troelskn/php-tracer-weaver
2. https://github.com/dainbrump/Tracer
