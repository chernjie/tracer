<?php

/**
 * Smartass Code tracer to help with code debuggings
 *
 * @author chernjie
 * @example: call Tracer::init() at the beginning of script execution
 * @example: call Tracer::add($name, $data) in between scripts
 */
class Tracer
{
	public static $tracer = array();
	const CODE_COVERAGE = 'Code Coverage';
	const CACHE_DIR     = './';
	private $classes = array();

	private static $instance = null;
	public static function instance()
	{
		if (empty(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}

	public static function init()
	{
		if (array_key_exists('tracefile', $_GET)) exit( highlight_file($_GET['tracefile'], true) );
		if (array_key_exists('mirror',    $_GET)) exit( self::getCacheFile($_GET['mirror'])      );
		self::instance()->classes = get_declared_classes();
		error_reporting(E_ALL|E_STRICT);
		function_exists('xdebug_start_code_coverage') && xdebug_start_code_coverage();
		self::add('Environment', array('_GET'=>$_GET, '_POST'=>$_POST, '_COOKIE'=>$_COOKIE, '_SERVER'=>$_SERVER));
	}

	public static function getCacheFile($file)
	{
		$cache = self::CACHE_DIR . substr(md5($file), 0, 4) . '.cache';
		file_exists($cache) || file_put_contents($cache, file_get_contents($file));
		switch (substr($file, strrpos($file, '.')))
		{
			case 'js':  case '.js':
				header('Content-Type: application/javascript');
				break;
			case 'css': case '.css':
				header('Content-Type: text/css');
				return preg_replace('/(:|[\s]+)(url\([\'"]?)(.*)([\'"]?\))/'
					, '\1\2?mirror=' . substr($file, 0, strrpos($file, '/')) . '/\3\4'
					, file_get_contents($cache));
				break;
			case 'gif': case '.gif':
				header('Content-Type: image/gif');
				break;
		}
		return file_get_contents($cache);
	}

	/**
	 * Get code coverage with Xdebug
	 * @link http://www.xdebug.org/docs/code_coverage
	 *
	 * @return array filename as key and codes as array values
	 */
	public static function get_code_coverage()
	{
		if (! function_exists('xdebug_get_code_coverage'))
			return array('xdebug is not installed, no code coverage');
		$coverage = xdebug_get_code_coverage();
		foreach ($coverage as $file => $line)
		{
			if (! file_exists($file)) continue;
			/**
			$file_contents = file($file);
			$code = '';
			foreach ($line as $lineNumber => $int1)
			{
				if (isset($file_contents[$lineNumber-1]))
				{
					$code.= sprintf('%4d: ',$lineNumber);
					$code.= $file_contents[$lineNumber-1];
				}
			}
			$coverage[$file] = highlight_string($code, true);
			/**/
			$key = preg_replace('/\/vagrant\//', '', $file);
			unset($coverage[$file]);
			$coverage[$key] = $file;
		}
		if (array_key_exists('cc_sort', $_GET)) ksort($coverage);
		return $coverage;
	}

	/**
	 * @param stdClass $object
	 * @param string $property
	 * @return array
	 * @todo not all objects are the same even if they are instantiated from the same class
	 */
	private static function object2array($object, $_classes = array(), $_level = 0)
	{
		if (! is_object($object)) return $object;
		// $array = preg_replace('/\w+::__set_state/', '', var_export($object, true));
		// eval('$array = ' . $array . ';');
		$array = array();
		$class = get_class($object);
		array_push($_classes, $class);
		$reflected = new ReflectionClass($object);
		$props = $reflected->getProperties();
		foreach ($props as $prop)
		{
			$prop->setAccessible(true);
			$name = $prop->getName();
			$value = $prop->getValue($object);
			if (is_object($value))
			{
				$name .= ':' . get_class($value);
				$value = in_array(get_class($value), $_classes) || $_level > 10
					? get_class($value)
					: self::object2array($value, $_classes, $_level + 1);
			}
			switch (true)
			{
				case $prop->isPrivate():
					$name .= ':private';
					break;
				case $prop->isProtected():
					$name .= ':protected';
					break;
				case $prop->isPublic():
					break;
				case $prop->isStatic():
					$name .= ':static';
					break;
				default:
					$name .= '?';
					break;
			}
			$array[$name] = $value;
		}
		return $array;
	}

	/**
	 * @param array $arr
	 * @param string $path
	 * @return string
	 */
	private static function array2ulli($arr, $path='')
	{
		$string = '<ul>';
		if (count(explode('/', $path)) > 5)
		{
			$string.= '<li style="color:red;">Nesting level too deep</li>';
		}
		else if (is_array($arr))
		{
			foreach($arr as $k=>$v)
			{
				$open = $k == 'Data'?' class="open"':'';
				switch(true)
				{
					case is_string($v) && is_file($v):
						$string.= sprintf('<li><span class="tracefile">%s</span><div>%s</div></li>', $k, $v);
						break;
					case empty($v):
						$string.= sprintf('<li><span>%s: %s</span></li>', $k, var_export($v, true));
						break;
					case is_scalar($v):
						$string.= sprintf('<li><span>%s: %s</span></li>', $k, $v);
						break;
					case is_object($v):
						$string.= sprintf('<li><span>%s: %s</span>%s</li>', $k, get_class($v)
							, self::array2ulli(self::object2array($v), $path.'/'.$k)
						);
						break;
					default:
						$string.= sprintf('<li%s><span>%s</span>%s</li>', $open, $k, self::array2ulli($v, $path.'/'.$k));
						break;
				}
			}
		}
		else
			$string.= '<li>'.highlight_string('<'.'?php '."\n".var_export($arr,true), true).'</li>';
		return $string.'</ul>';
	}

	/**
	 * @param string $name
	 * @param mixed $data
	 */
	public static function add($name, $data=array())
	{
		$name = (string) $name;
		if (empty(self::$tracer[$name]))
			self::$tracer[$name] = array();
		foreach($data as $key=>$value)
		{
			self::$tracer[$name][$key] = $value;
		}
	}

	/**
	 * @return time since REQUEST_TIME in microseconds
	 */
	public static function since()
	{
		return number_format(microtime(true) - $_SERVER['REQUEST_TIME'], 3);
	}

	/**
	 * end tracer and display code coverage
	 */
	public function __destruct()
	{
		self::add('Declared Classes', array_diff(get_declared_classes(), $this->classes));
		self::add(self::CODE_COVERAGE, self::get_code_coverage());
		if ( PHP_SAPI == 'cli' )
		{
			array_walk_recursive(self::$tracer, array(__CLASS__, 'object2array'));
			echo json_encode(self::$tracer);
		}
		else
		{
			?>
			<ul id="code-tracer" class="tracer">
				<li class="open"><span>Code trace</span>
					<?php echo self::array2ulli(self::$tracer) ?>
				</li>
			</ul>
			<link rel="stylesheet" href="?mirror=https://raw.github.com/jzaefferer/jquery-treeview/master/jquery.treeview.css" />
		    <script type="text/javascript">window.jQuery || document.write('<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"><\/script>')</script>
			<script type="text/javascript" src="?mirror=https://raw.github.com/carhartl/jquery-cookie/master/jquery.cookie.js"></script>
			<script type="text/javascript" src="?mirror=https://raw.github.com/jzaefferer/jquery-treeview/master/jquery.treeview.js"></script>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("#code-tracer").treeview({collapsed: true,persist: "cookie"});
					jQuery('.tracefile').next().hide();
					jQuery('.tracefile').click(function(e){
						jQuery(this).data('tracefile') || jQuery(this).data('tracefile', jQuery(this).next().html());
						jQuery(this).next().toggle().load('', 'tracefile=' + jQuery(this).data('tracefile'));
					});
					jQuery('#code-tracer > li > ul > li > ul > li').each(function(i, el){
						var len = jQuery(this).find('> ul > li').length;
						len > 3 && jQuery(this).find('span:first').html(jQuery(this).find('span:first').html() + " (" + len + ")");
					});
					// Magento specific rules;
					jQuery('body').css('overflow', 'auto');
					jQuery('[onclick*="profiler_section"]').click(function(e){ e.preventDefault(); $(this).next().slideToggle('slow'); });
				});
			</script>
			<?php
		}
	}
}
