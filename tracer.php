<?php

/**
 * Smartass Code tracer to help with code debuggings
 * 
 * @author chernjie
 * @example: call Tracer::init() at the beginning of script execution
 * @example: call Tracer::out() at the end of script execution
 * @example: call Tracer::add($name, $data) in between scripts
 */
class Tracer
{
	public static $tracer = array();
	const CODE_COVERAGE = 'Code Coverage';
	const CACHE_DIR     = './';

	public static function init()
	{
		if (array_key_exists('tracefile', $_GET)) exit( highlight_file($_GET['tracefile'], true) );
		if (array_key_exists('mirror', $_GET))
		{
			$cache = self::CACHE_DIR . substr(md5($_GET['mirror']), 0, 4) . '.cache';
			file_exists($cache)
				|| file_put_contents($cache, file_get_contents($_GET['mirror']));
			exit( file_get_contents($cache) );
		}
		error_reporting(E_ALL|E_STRICT);
		xdebug_start_code_coverage();
		self::add('Environment', array('_GET'=>$_GET, '_POST'=>$_POST, '_COOKIE'=>$_COOKIE, '_SERVER'=>$_SERVER));
	}
	
	/**
	 * Get code coverage with Xdebug
	 * @link http://www.xdebug.org/docs/code_coverage
	 *
	 * @return array filename as key and codes as array values
	 */
	public static function get_code_coverage()
	{
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
	 * @return unknown
	 */
	private static function object2array($object, $property=null)
	{
		$array = preg_replace('/\w+::__set_state/', '', var_export($object, true));
		eval('$array = ' . $array . ';');
		return is_null($property) ? $array : $array[$property];
	}

	/**
	 * @param array $arr
	 * @param string $path
	 * @return string
	 */
	private static function array2ulli($arr, $path='')
	{
		$string = '<ul>';
		if(is_array( $arr))
			foreach($arr as $k=>$v)
			{
				$open = $k == 'Data'?' class="open"':'';
				switch(true)
				{
					case is_string($v) && file_exists($v):
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
							, self::array2ulli(self::object2array($v, 'definition'===$k?'definition':null), $path.'/'.$k)
						);
						break;
					default:
						$string.= sprintf('<li%s><span>%s</span>%s</li>', $open, $k, self::array2ulli($v, $path.'/'.$k));
						break;
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
		if (empty(self::$tracer[$name]))
			self::$tracer[$name] = array();
		foreach($data as $key=>$value)
		{
			self::$tracer[$name][$key] = $value;
		}
	}

	/**
	 * end tracer and display code coverage
	 */
	public static function out()
	{
		self::add(self::CODE_COVERAGE, self::get_code_coverage());
		if( self::$tracer )
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
				$(document).ready(function(){
					$("#code-tracer").treeview({collapsed: true,persist: "cookie"});
					$('.tracefile').next().hide();
					$('.tracefile').click(function(e){
						$(this).data('tracefile') || $(this).data('tracefile', $(this).next().html());
						$(this).next().toggle().load('', 'tracefile=' + $(this).data('tracefile'));
					});
				});
			</script>
			<?php
		}
	}
}
