<?php
/**
 * This sniff prohibits the use of Perl style hash comments.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Your Name <you@domain.net>
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

namespace PHP_CodeSniffer\Standards\Localizer\Sniffs;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class CollectStringsSniff implements Sniff
{
	private $replaces=[];
	private $unknownStrings = null;

	public $locFn;
	public $locFn2;
	public $storage="localize.json";

	public function __construct(){
		$this->load($this->storage);
	}

	public function __destruct(){
		$this->save($this->storage, $this->unknownStrings);
	}
    
    public function load($file){
    	$fields = json_decode(file_get_contents($file), true) ?: [];
    	foreach($fields as $k=>$v){
    		if($k === $v || $v === null){
    			unset($fields[$k]);
    		}
    	}
		$this->replaces = $fields;
    }

    public function save($file, $overrides){
    	touch($file);
    	$old = json_decode(file_get_contents($file), true) ?: [];
    	file_put_contents($file, json_encode($old + $overrides, JSON_PRETTY_PRINT));
    }


    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array(int)
     */
    public function register()
    {
        $this->firstToken = 0;
        return [
        	T_CONSTANT_ENCAPSED_STRING, 
        	T_DOUBLE_QUOTED_STRING,
        ];
    }//end register()

    /**
     * Processes this sniff, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being checked.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
    	if($stackPtr < $this->firstToken){ return; }

    	$string = "";
    	$args = [];
		$state = 'next';

        $tokens = $phpcsFile->getTokens();
		$depth = 0;
		$delim = null;

		for($offset = 0;($stackPtr + $offset < count($tokens) ) && $state != 'end'; $offset++){
			$nextToken = $tokens[$offset + $stackPtr];
			$this->debug(PHP_EOL.$state . ': '. $nextToken['type'] , 3);

			if($state == 'collect'){
				switch($nextToken['type']){
					case 'T_OPEN_PARENTHESIS':
					case 'T_OPEN_CURLY_BRACKET':
					case 'T_OPEN_SQUARE_BRACKET':
						$depth++;
					break;
					case 'T_CLOSE_PARENTHESIS':
					case 'T_CLOSE_CURLY_BRACKET':
					case 'T_CLOSE_SQUARE_BRACKET':
						$depth--;
					break;
				}

				if($depth == 0){
					switch($nextToken['type']){
						case 'T_STRING_CONCAT':
							$string .= '%s';
							$args[] = $collector;
							$state = 'next';
						break;
						case 'T_CLOSE_CURLY_BRACKET':
						case 'T_CLOSE_SQUARE_BRACKET':
						case 'T_SEMICOLON':
						case 'T_COMMA':
							$string .= '%s';
							$args[] = trim($collector);
							$state = 'end';
						break;
						default:
							$collector .= $nextToken['content'];
					}
				}else if($depth < 0){
					$string .= '%s';
					$args[] = trim($collector);
					$state = 'end';
				}else{
					$collector .= $nextToken['content'];
				}
				$this->debug(' ~'.$depth.'~> ' . $state ." $collector" , 3);
			}else{
				switch($state .'-'.$nextToken['type']){
					case 'find-T_STRING_CONCAT':
						$state = 'next';
					break;
					case 'next-T_CONSTANT_ENCAPSED_STRING':
						$str = $nextToken['content'];
						if($delim == null){
							$delim = $nextToken['content'][0];
							$str = substr($nextToken['content'],1);
						}
						$str = str_replace(['\\'.$delim, '\\\\', '%'], [$delim, '\\', '%%'],$str);
						if(substr($nextToken['content'],-1) === $delim){
							$str = substr($nextToken['content'],0, -1);
							$delim = null;
							$state = 'find';
						}
						$string .= $str;
					break;
					case 'next-T_DOUBLE_QUOTED_STRING':
						$str = $nextToken['content'];
						if($delim == null){
							$delim = $nextToken['content'][0];
							$str = substr($nextToken['content'],1);
						}
						$str = str_replace(['\\'.$delim, '\\\\', '%'], [$delim, '\\', '%%'],$str);
						if(substr($nextToken['content'],-1) === $delim){
							$str = substr($nextToken['content'],0, -1);
							$delim = null;
							$state = 'find';
						}
						$str = preg_replace_callback('/(?:\{\$|\$\{)([^}]+)\}|\$([a-zA-Z0-9_]+)/',function ($matches) use(&$args) {$args[] = '$'.($matches[1]?:$matches[2]);return "%s";},$str);
						$string .= $str;
					break;
					case 'next-T_OPEN_PARENTHESIS':
						$depth++;
					//falltrough
					case 'next-T_STRING':
					case 'next-T_LINE':
					case 'next-T_VARIABLE':
					case 'next-T_DOLLAR':
					case 'next-T_LNUMBER':
						$collector = "";

						$collector .= $nextToken['content'];
						$state = 'collect';
					break;
					case 'next-T_WHITESPACE':
					case 'find-T_WHITESPACE':
					break;
					default:
						$state = 'end';
					break;
				}
				$this->debug(' ==> ' . $state, 3);
			}
		}
		$this->debug(PHP_EOL.'Found string on ['.$stackPtr.'-'.($stackPtr + $offset - 1).']:'.$string.PHP_EOL, 3);

		$this->firstToken = $stackPtr + $offset - 1;

		if( isset($tokens[$stackPtr - 2]['content']) && 
			$tokens[$stackPtr - 2]['type'] == 'T_STRING' && 
			($tokens[$stackPtr - 2]['content'] == $this->locFn ||
			$tokens[$stackPtr - 2]['content'] == $this->locFn2)
		){
			$this->debug('## skip,  Already localized: '.$string.PHP_EOL, 2);
			return;
		}

		if( empty($args) && trim($string) != '' && preg_match('/^[A-Z0-9_]+$/',$string) ){
			$this->debug('## skip, empty or constant: '.$string.PHP_EOL, 2);
			return;
		}
		if( preg_match('/\bSELECT\b.+\bFROM\b/i',$string) ||
			preg_match('/\bINSERT\b.+\bINTO\b/i',$string)  ||
			preg_match('/\bDELETE\b.+\bFROM\b/i',$string)  ||
			preg_match('/\bUPDATE\b.+\bSET\b/i',$string) 
		){
			$this->debug('## skip, looks like a query: '.$string.PHP_EOL, 2);
			return;
		}

		if(!$this->canReplace($string)){
	    	$expression = $string;
	    	$phpcsFile->addError($string, $stackPtr, 'Localizable');
	    	
	    	$this->unknownStrings[$string] = null;
	    	return;
	    }

	    $expression = $this->replace($string);
    	$expression = str_replace(['\\', '"', '$'],['\\\\', '\\"', '\$'],$expression);
    	$expression = '"'.$expression.'"';
    	if( !empty($args)  ){
    		$expression = ($this->locFn2 ?: 'sprintf').'('.$expression.', '.implode(', ',$args). ' )';
    	}else{
    		if($this->locFn){
    			$expression = $this->locFn."(".$expression.")";
    		}
    	}
    	$fix = $phpcsFile->addFixableError($string, $stackPtr, 'Localizable');

        if ($fix === true) {
            $phpcsFile->fixer->beginChangeset();

			$phpcsFile->fixer->replaceToken($stackPtr, $expression);
            for ($i = $stackPtr+1; $i < $stackPtr+$offset-1; $i++) {
                $phpcsFile->fixer->replaceToken($i, '');
            }

            $phpcsFile->fixer->endChangeset();
        }


    }//end process()

    protected function canReplace($str){
    	return isset($this->replaces[$str]);
    }

	protected function replace($str){
		return $this->replaces[$str];
	}
	protected function debug($str, $lvl){
		if(PHP_CODESNIFFER_VERBOSITY >= $lvl)
			echo $str;
	}

}//end class
