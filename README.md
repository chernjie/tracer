tracer
======

PHP Runtime Tracer for debugging

# Usage

1. require or autoload `Tracer` at the top of your execution scripts, usually `index.php`. Then initialize it. Example:
```
require_once 'tracer.php';
Tracer::init();
```

2. Start adding data into `Tracer`
```
Tracer::add('name', 'any type of data');
```

3. Upon page exit, `Tracer::__destruct` will be called to show your desired code trace
