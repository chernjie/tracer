<?php

class Tracer
{
	public static $tracer = array();
	const CODE_COVERAGE = 'CODE_COVERAGE';

	public static function init()
	{
		if (array_key_exists('tracefile', $_GET)) exit( highlight_file($_GET['tracefile'], true) );
		if (array_key_exists('mirror', $_GET))
		{
			$cache = '.' . DIRECTORY_SEPARATOR . md5($_GET['mirror']) . '.cache';
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
		foreach($coverage as $file => $line):
			if(!file_exists($file)) continue;
			$file_contents = file($file);
			$code = '';
			foreach($line as $lineNumber => $int1)
				if (isset($file_contents[$lineNumber-1]))
				$code.= sprintf('%4d: ',$lineNumber).$file_contents[$lineNumber-1];
			$coverage[$file] = $code;
		endforeach;
		if (array_key_exists('cc_sort', $_GET)) ksort($coverage);
		return $coverage;
	}

	private static function object2array($object, $property=null)
	{
		$array = preg_replace('/\w+::__set_state/', '', var_export($object, true));
		eval('$array = ' . $array . ';');
		return is_null($property) ? $array : $array[$property];
	}

	private static function array2ulli($arr, $path='')
	{
		$fn = __METHOD__;
		$string = '<ul>';
		if(is_array( $arr))
			foreach($arr as $k=>$v)
			{
				$open = $k == 'Data'?' class="open"':'';
				switch(true)
				{
					case is_string($v) && file_exists($v):
						$htmlkey = 'file'.preg_replace('/[^a-z0-9_]/i','',$path.$k);
						$string.= '<li id="'.$htmlkey.'">'.
							'<span onclick="$(\'#'.$htmlkey.' div\').toggle().load(\'\',\'tracefile='.urlencode($v).'\');">'.
								preg_replace( '/\/var\/www\/[^\/]+/i', '', is_numeric($k)?$v:$k.': '.$v ).
							'</span>'.
							'<div style="display:none">'.$v.'</div></li>';
						break;
					case empty($v):
						$string.= '<li><span>'.$k.': '.var_export($v,true).'</span></li>';
						break;
					case is_scalar($v):
						$string.= '<li><span>'.$k.': '.$v.'</span></li>';
						break;
					case is_object($v):
						$string.= '<li><span>'.$k.': '.get_class($v).'</span>'. self::array2ulli(object2array($v, 'definition'===$k?'definition':null), $path.'/'.$k) .'</li>';
						break;
					default:
						$string.= '<li'.$open.'><span>'.$k.'</span>'. self::array2ulli($v, $path.'/'.$k).'</li>';
						break;
				}
			}
		else
			$string.= '<li>'.highlight_string('<'.'?php '."\n".var_export($arr,true), true).'</li>';
		return $string.'</ul>';
	}

	public static function add($name, $data=array())
	{
		if( $name )
		{
			if(empty(self::$tracer[$name]))
				self::$tracer[$name] = array();
			foreach($data as $key=>$value)
			{
				self::$tracer[$name][$key] = $value;
			}
		}
		elseif( self::$tracer )
		{
			self::add(self::CODE_COVERAGE);
			?>
			<ul id="code-tracer" class="tracer">
				<li class="open"><span>Code trace</span>
					<?= self::array2ulli(self::$tracer) ?>
				</li>
			</ul>
			<link rel="stylesheet" href="?mirror=https://raw.github.com/jzaefferer/jquery-treeview/master/jquery.treeview.css" />
			<?php	exec('grep jquery-1.*min.js . -l', $jquery_hint);
					$implode_tracer = implode('', self::$tracer[self::CODE_COVERAGE]);
					foreach($jquery_hint as $needle)
						if($jquery_exists = strpos($implode_tracer, $needle))
							break;
					if (empty($jquery_exists)): ?>
			<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
			<?php endif; ?>
			<script type="text/javascript" src="?mirror=https://raw.github.com/carhartl/jquery-cookie/master/jquery.cookie.js"></script>
			<script type="text/javascript" src="?mirror=https://raw.github.com/jzaefferer/jquery-treeview/master/jquery.treeview.js"></script>
			<script type="text/javascript">$(document).ready(function(){$("#code-tracer").treeview({collapsed: true,persist: "cookie"});});</script>
			<?php
		}
	}
}
