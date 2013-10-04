<?php
	if(isset($_GET['tracefile'])) exit( highlight_file($_GET['tracefile'], true) );

	define('CODE_COVERAGE', 'Code Coverage');
	error_reporting(E_ALL|E_STRICT);


	/**
	 * Get code coverage with Xdebug
	 * @link http://www.xdebug.org/docs/code_coverage
	 *
	 * @return array filename as key and codes as array values
	 */
	function get_code_coverage()
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
		if(!get_value('cc_sort', 'integer', 0)) ksort($coverage);
		return $coverage;
	}

	function object2array($object, $property=null)
	{
		$array = preg_replace('/\w+::__set_state/', '', var_export($object, true));
		eval('$array = ' . $array . ';');
		return is_null($property) ? $array : $array[$property];
	}

	function array2ulli($arr, $path='')
	{
		$fn = __FUNCTION__;
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
						$string.= '<li><span>'.$k.': '.get_class($v).'</span>'.$fn(object2array($v, 'definition'===$k?'definition':null), $path.'/'.$k) .'</li>';
						break;
					default:
						$string.= '<li'.$open.'><span>'.$k.'</span>'.$fn($v, $path.'/'.$k).'</li>';
						break;
				}
			}
		else
			$string.= '<li>'.highlight_string('<'.'?php '."\n".var_export($arr,true), true).'</li>';
		return $string.'</ul>';
	}

	function add2tracer($name, $data=array())
	{
		global $tracer;
		if( $name )
		{
			if(empty($tracer[$name]))
				$tracer[$name] = array();
			foreach($data as $key=>$value)
			{
				$tracer[$name][$key] = $value;
				if($name == CODE_COVERAGE)
				{
					foreach(array(
						'Dao'=>'/sites\/dao\/([a-z0-9_-]+)/i',
						'Module'=>'/sites\/module\/([a-z0-9_-]+)/i',
					) as $match=>$regexp)
					{
						if( preg_match($regexp, $value, $key) )
						{
							$tracer[get_controller().' '.get_action()][$match.': '.$key[1]] = $value;
						}
					}
				}
			}
		}
		elseif( $tracer )
		{
			?>
			<ul id="code-tracer" class="tracer">
				<li class="open"><span>Code trace</span>
					<?= array2ulli($tracer) ?>
				</li>
			</ul>
			<link rel="stylesheet" href="/sites/resources/css/treeview/jquery.treeview.css" />
			<?php	exec('ack jquery-1.*min.js sites/ -l', $jquery_hint);
					$implode_tracer = implode('',$tracer[CODE_COVERAGE]);
					foreach($jquery_hint as $needle)
						if($jquery_exists = strpos($implode_tracer, $needle))
							break;
					if (empty($jquery_exists)): ?>
			<script type="text/javascript" src="/sites/resources/js/jquery-1.7.1.min.js"></script>
			<?php endif; ?>
			<script type="text/javascript" src="/sites/resources/js/jquery.cookie.min.js"></script>
			<script type="text/javascript" src="/sites/resources/js/jquery.treeview.min.js"></script>
			<script type="text/javascript">$(document).ready(function(){$("#code-tracer").treeview({collapsed: true,persist: "cookie"});});</script>
			<?php
		}
	}
	$tracer = array();

	xdebug_start_code_coverage();
	require_once("./common/utilities.php");
	fast_require("Controller", get_framework_common_directory()."/controller-tracer.php");
	add2tracer('Environment', array('_GET'=>$_GET, '_POST'=>$_POST, '_COOKIE'=>$_COOKIE, '_SERVER'=>$_SERVER));
	require_once("./index.php");
?>